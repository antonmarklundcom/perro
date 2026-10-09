<?php
declare(strict_types=1);

function run_cleanup_jobs(int $limit = 20): array
{
    return with_data_lock(static function () use ($limit): array {
        $jobs = read_dataset('cleanup'); $done = 0; $failed = 0; $ordered = []; $retry = [];
        foreach ($jobs as $job) {
            if (($job['status'] ?? '') !== 'pending' || $done + $failed >= $limit) { $ordered[] = $job; continue; }
            $job['attempts'] = (int) ($job['attempts'] ?? 0) + 1;
            try {
                if (!is_array($job['folders'] ?? null)) throw new RuntimeException('Trabajo de limpieza inválido.');
                foreach ($job['folders'] as $folder) {
                    if (!is_string($folder)) throw new RuntimeException('Carpeta de limpieza inválida.');
                    remove_upload_folder($folder);
                }
                $cache = PERRO_STORAGE . '/cache';
                if (is_link($cache)) throw new RuntimeException('Caché fuera del almacenamiento.');
                foreach (glob($cache . '/share-*.jpg') ?: [] as $file) {
                    if (!@unlink($file)) throw new RuntimeException('No se pudo limpiar una imagen compartida.');
                }
                $job['status'] = 'done'; $job['completed_at'] = now_iso(); unset($job['error'], $job['folders']); $done++; $ordered[] = $job;
            } catch (Throwable $error) { $job['error'] = 'filesystem_cleanup_failed'; $failed++; $retry[] = $job; }
        }
        // Failed attempts yield to unattempted jobs on the next bounded run.
        $jobs = array_merge($ordered, $retry);
        if ($done + $failed && !commit_datasets(['cleanup'=>$jobs])) throw new RuntimeException('No se pudo registrar la limpieza.');
        return ['completed'=>$done, 'failed'=>$failed, 'pending'=>count(array_filter($jobs, static fn(array $j): bool => ($j['status'] ?? '') === 'pending'))];
    });
}

function render_reliability_status(): void
{
    $pending = count(array_filter(read_dataset('cleanup'), static fn(array $j): bool => ($j['status'] ?? '') === 'pending'));
    $failed = count(array_filter(read_dataset('notifications'), static fn(array $j): bool => ($j['status'] ?? '') === 'failed'));
    if (!$pending && !$failed) return;
    echo '<div class="shell"><div class="notice notice-warning" role="status"><strong>Operación por revisar</strong><p>Limpiezas pendientes: ' . $pending . '. Alertas que agotaron reintentos: ' . $failed . '. El responsable técnico debe revisar los comandos privados de mantenimiento.</p></div></div>';
}

function claim_notification(): ?array
{
    return with_data_lock(static function (): ?array {
        $items = read_dataset('notifications'); $now = time(); $changed = false;
        foreach ($items as &$item) {
            $pending = ($item['status'] ?? '') === 'pending' && (int) ($item['retry_after'] ?? 0) <= $now;
            $expired = ($item['status'] ?? '') === 'processing' && (int) ($item['lease_until'] ?? 0) <= $now;
            if (!$pending && !$expired) continue;
            if ((int) ($item['attempts'] ?? 0) >= 5) { $item['status'] = 'failed'; $item['error'] = 'retry_limit'; unset($item['claim'], $item['lease_until']); $changed = true; continue; }
            $item['status'] = 'processing'; $item['claim'] = bin2hex(random_bytes(16)); $item['lease_until'] = $now + 300;
            $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
            if (!commit_datasets(['notifications'=>$items])) throw new RuntimeException('No se pudo reservar una alerta.');
            return $item;
        }
        unset($item);
        if ($changed && !commit_datasets(['notifications'=>$items])) throw new RuntimeException('No se pudo registrar el estado de alertas.');
        return null;
    });
}

function finish_notification(array $claimed, bool $ok): bool
{
    return with_data_lock(static function () use ($claimed, $ok): bool {
        $item = find_record('notifications', $claimed['id']);
        if (!$item || ($item['status'] ?? '') !== 'processing' || !hash_equals((string) ($item['claim'] ?? ''), (string) $claimed['claim'])) return false;
        unset($item['claim'], $item['lease_until']);
        if ($ok) { $item['status'] = 'sent'; $item['sent_at'] = now_iso(); unset($item['error'], $item['retry_after']); }
        else {
            $item['status'] = (int) $item['attempts'] >= 5 ? 'failed' : 'pending';
            $item['error'] = 'transport_failed'; $item['retry_after'] = time() + min(3600, 60 * (2 ** max(0, (int) $item['attempts'] - 1)));
        }
        return save_record('notifications', $item);
    });
}
