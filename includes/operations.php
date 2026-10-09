<?php

declare(strict_types=1);
require_once __DIR__ . '/reliability.php';

function operation_routes(string $path): void
{
    if ($path === 'health') {
        header('Cache-Control: no-store'); header('Content-Type: application/json; charset=utf-8');
        with_data_read_lock(static function (): void {
            foreach (storage_dataset_names() as $name) read_dataset($name);
            foreach ([PERRO_DATA, PERRO_UPLOADS] as $directory) {
                $probe = $directory . '/.health-' . bin2hex(random_bytes(6));
                $handle = @fopen($probe, 'x');
                if (!$handle) throw new RuntimeException('Almacenamiento no escribible.');
                fclose($handle);
                if (!@unlink($probe)) throw new RuntimeException('No se pudo limpiar la prueba de almacenamiento.');
            }
        });
        echo '{"status":"ok"}'; exit;
    }
    if (preg_match('#^ficha/([a-z0-9-]+)$#iD', $path, $matches)) {
        foreach (public_dogs() as $dog) if (strcasecmp(listing_share_code($dog), $matches[1]) === 0) {
            header('Location: /perro/' . $dog['slug'], true, 302); exit;
        }
        render_error_page('Ficha no disponible', 'El aviso puede haber vencido o terminado en una adopción. Buscá otros avisos vigentes.', 404); exit;
    }
    if (preg_match('#^contactar/([a-z0-9-]+)$#D', $path, $matches)) {
        $dog = public_dog_by_slug($matches[1]);
        if (!$dog) { http_response_code(404); exit; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
        // One aggregate counter, with no visitor identifier, cookie or IP stored.
        $dog = with_data_lock(static function () use ($dog): ?array {
            $current = find_record('dogs', $dog['id']);
            if ($current && public_dog_by_slug($current['slug'])) {
                $current['contact_clicks'] = (int) ($current['contact_clicks'] ?? 0) + 1;
                if (!save_record('dogs', $current)) throw new RuntimeException('No se pudo registrar el contador agregado.');
                return $current;
            }
            return null;
        });
        if (!$dog) { http_response_code(404); exit; }
        header('Cache-Control: no-store'); header('Location: ' . listing_contact_url($dog), true, 302); exit;
    }
    if ($path === 'admin/delete' && method_is_post()) {
        require_admin_capability('permanent_delete'); require_csrf();
        if (!checked('delete_confirm')) { set_flash('error', 'Confirmá la supresión después de verificar la solicitud.'); redirect('admin'); }
        $id = text('id', 100);
        delete_listing_data($id);
        $pending = array_filter(read_dataset('cleanup'), static fn(array $j): bool => ($j['status'] ?? '') === 'pending');
        set_flash($pending ? 'warning' : 'success', $pending ? 'Se eliminaron los datos del aviso. Hay limpieza de archivos pendiente: el responsable técnico debe revisarla.' : 'Se eliminaron los datos y archivos del aviso. Revisá por separado los respaldos y las copias externas.'); redirect('admin');
    }
}

function photo_variant(string $file, int $width): string
{
    if (!in_array($width, [480, 960], true) || !function_exists('imagecreatefromstring')) return $file;
    $variant = $file . '.w' . $width . '.jpg';
    if (is_file($variant)) return $variant;
    $details = @getimagesize($file);
    if (!$details || $details[0] * $details[1] > 8000000) return $file;
    $image = @imagecreatefromstring((string) file_get_contents($file));
    if (!$image) return $file;
    $scale = min(1, $width / imagesx($image)); $height = max(1, (int) round(imagesy($image) * $scale));
    $canvas = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), $height);
    $temp = $variant . '.tmp-' . bin2hex(random_bytes(4));
    try {
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, imagesx($canvas), $height, imagesx($image), imagesy($image));
        if (@imagejpeg($canvas, $temp, 78) && @rename($temp, $variant)) return $variant;
        return $file;
    } finally { imagedestroy($image); imagedestroy($canvas); @unlink($temp); }
}

function prepare_photo_variants(string $file): void
{
    foreach ([480, 960] as $width) photo_variant($file, $width);
}

function delete_photo_files(string $file): void
{
    foreach ([$file, $file . '.w480.jpg', $file . '.w960.jpg'] as $path) if (is_file($path)) @unlink($path);
}

function serve_photo(string $file, bool $admin): never
{
    // Authentication and session updates are complete; image work must not hold the session lock.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $width = (int) query_value('w', 4);
    if (in_array($width, [480, 960], true)) $file = photo_variant($file, $width);
    $details = @getimagesize($file);
    header('Content-Type: ' . ($details['mime'] ?? 'image/jpeg'));
    if ($admin) header('Cache-Control: no-store');
    else {
        // Revalidate before reuse, so revocation is checked even for a cached file.
        header('Cache-Control: private, no-cache, max-age=86400');
        $etag = '"' . hash_file('sha256', $file) . '"'; header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
    }
    header('Content-Length: ' . filesize($file));
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($file);
    exit;
}

function queue_notification(string $event, string $reference): void
{
    try {
        if (!save_record('notifications', ['id'=>random_id('notify-'), 'event'=>$event, 'reference'=>$reference, 'created_at'=>now_iso(), 'status'=>'pending'])) throw new RuntimeException('No se pudo guardar la alerta.');
    } catch (Throwable $error) { error_log('Perro: alerta pendiente; revisá el panel y la configuración del envío. ' . get_class($error)); }
}

function notification_text(array $item): string
{
    return ($item['event'] === 'report' ? 'Nuevo reporte sobre ficha ' : 'Nueva solicitud ') . $item['reference'] . "\n" . app_url('admin?section=' . ($item['event'] === 'report' ? 'reportes' : 'solicitudes'));
}

function dispatch_notifications(callable $sender): int
{
    $sent = 0;
    for ($i = 0; $i < 50; $i++) {
        $item = claim_notification();
        if ($item === null) break;
        try { $ok = $sender(notification_text($item)); } catch (Throwable $error) { $ok = false; }
        if (finish_notification($item, $ok) && $ok) $sent++;
    }
    return $sent;
}

function archived_records(string $q = ''): array
{
    return array_values(array_filter(read_dataset('archive'), static fn(array $entry): bool => admin_search_matches(normalize_record($entry['dataset'], $entry['record']), $q)));
}

function render_archived_record(array $entry): void
{
    if ($entry['dataset'] === 'moderation') {
        $event = $entry['record'];
        echo '<article class="admin-card"><div><h3>Actividad archivada: ' . h($event['action'] ?? '') . '</h3><p>Registro ' . h($event['record_id'] ?? '') . ' · ' . h(format_date($event['created_at'] ?? '')) . '</p></div></article>';
        return;
    }
    $record = normalize_record($entry['dataset'], $entry['record']);
    ?><article class="admin-card"><div><span class="status-pill">Archivo · <?= h(record_status_label($record['status'])) ?></span><h3><?= h($record['name'] ?? $record['dog_name']) ?></h3><p><?= h($record['city']) ?> · Ref. <?= h($record['reference']) ?> · <?= h($record['whatsapp']) ?></p><p>Contacto privado: <?= h($record['submitter_name']) ?> · <?= h($record['email']) ?></p><p><?= h($record['internal_note']) ?></p><small>Archivado el <?= h(format_date($entry['archived_at'])) ?></small></div><?php if (admin_has_capability('permanent_delete')): ?><form method="post" action="/admin/delete"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($record['id']) ?>"><label class="check"><input type="checkbox" name="delete_confirm" value="1" required> Verifiqué la solicitud y confirmo eliminar sus datos y fotos.</label><button class="button button-danger" type="submit">Eliminar datos</button></form><?php endif; ?></article><?php
}

function delete_listing_data(string $id): void
{
    $folders = with_data_lock(static function () use ($id): array {
        $datasets = [];
        foreach (['dogs', 'submissions', 'reports', 'moderation', 'archive', 'notifications', 'owner_actions', 'cleanup'] as $name) $datasets[$name] = read_dataset($name);
        $ids = [$id]; $folders = []; $references = [];
        $listings = array_merge($datasets['dogs'], $datasets['submissions'], array_column($datasets['archive'], 'record'));
        // Resolve both directions, including archived source records.
        do {
            $before = count($ids);
            foreach ($listings as $record) if (in_array($record['id'] ?? '', $ids, true) || in_array($record['source_submission_id'] ?? '', $ids, true) || in_array($record['published_dog_id'] ?? '', $ids, true)) {
                foreach (['id', 'source_submission_id', 'published_dog_id'] as $key) if (!empty($record[$key])) $ids[] = $record[$key];
            }
            $ids = array_values(array_unique($ids));
        } while (count($ids) > $before);
        if (count($ids) === 1 && !array_filter($listings, static fn(array $r): bool => ($r['id'] ?? '') === $id)) throw new RuntimeException('Ficha no encontrada para supresión.');
        foreach ($listings as $record) if (in_array($record['id'] ?? '', $ids, true)) {
            $folders[] = $record['source_submission_id'] ?? $record['id'];
            if (!empty($record['reference'])) $references[] = $record['reference'];
            if (!empty($record['slug'])) $references[] = listing_share_code($record);
        }
        foreach ($datasets as $name=>&$records) $records = array_values(array_filter($records, static function (array $r) use ($ids, $references): bool {
            $r = $r['record'] ?? $r;
            if (!empty($r['event']) && in_array($r['reference'] ?? '', $references, true)) return false;
            foreach (['id', 'dog_id', 'record_id', 'source_submission_id', 'published_dog_id'] as $key) if (in_array($r[$key] ?? '', $ids, true)) return false;
            return true;
        }));
        unset($records);
        $datasets['moderation'][] = ['id'=>random_id('mod-'), 'action'=>'data_deleted', 'record_id'=>'', 'note'=>'', 'admin'=>current_admin_account_id(), 'created_at'=>now_iso()];
        $datasets['cleanup'][] = ['id'=>random_id('cleanup-'), 'folders'=>array_values(array_unique($folders)), 'created_at'=>now_iso(), 'status'=>'pending', 'attempts'=>0];
        if (!commit_datasets($datasets)) throw new RuntimeException('No se pudo guardar la supresión.');
        return array_unique($folders);
    });
    run_cleanup_jobs();
}
