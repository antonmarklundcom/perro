<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
require PERRO_ROOT . '/includes/data.php';
require PERRO_ROOT . '/includes/render.php';
require PERRO_ROOT . '/includes/moderation.php';
require PERRO_ROOT . '/includes/sharing.php';
require PERRO_ROOT . '/includes/workflows.php';
require PERRO_ROOT . '/includes/operations.php';

$options = getopt('', ['archive', 'retention', 'apply', 'days:', 'backup-dir:', 'notifications', 'digest', 'backfill', 'scrub-notes', 'audit-phones']);
$days = max(180, (int) ($options['days'] ?? 180));
if (isset($options['audit-phones'])) {
    $issues = with_data_read_lock(static function (): array {
        $issues = [];
        foreach (['dogs'=>'contact_whatsapp', 'submissions'=>'whatsapp'] as $name=>$field) {
            read_dataset($name); // Validate the source before inspecting original, unnormalized values.
            $records = json_decode((string) file_get_contents(dataset_path($name)), true);
            foreach ($records as $r) {
                $value = $r[$field] ?? '';
                if ($value === '') continue;
                $digits = is_string($value) ? preg_replace('/\D/', '', $value) : '';
                if (!is_string($value) || valid_whatsapp($value) === '' || str_starts_with($digits, '5950') || strlen($digits) === 13) {
                    $issues[] = ['dataset'=>$name, 'id'=>$r['id'], 'field'=>$field, 'reason'=>'Revisá el contacto original con el responsable; corregí desde el editor.'];
                }
            }
        }
        return $issues;
    });
    echo json_encode(['mode'=>'read-only', 'contact_issues'=>$issues], JSON_UNESCAPED_UNICODE) . "\n";
}
if (isset($options['archive'])) {
    $ids = with_data_lock(static function () use ($days, $options): array {
        $dogs = read_dataset('dogs'); $submissions = read_dataset('submissions'); $archive = read_dataset('archive');
        $reports = read_dataset('reports'); $events = read_dataset('moderation'); $notifications = read_dataset('notifications');
        $ids = []; $sourceIds = [];
        foreach ($dogs as $dog) {
            $closed = in_array($dog['status'], ['expired', 'removed'], true) || in_array($dog['adoption_status'], ['adopted', 'reunited'], true) || ($dog['status'] === 'published' && strtotime($dog['expires_at']) <= time());
            if ($closed && max(strtotime($dog['updated_at']), strtotime($dog['expires_at'])) < time() - $days * 86400) {
                $ids[] = $dog['id']; if (!empty($dog['source_submission_id'])) $sourceIds[] = $dog['source_submission_id'];
            }
        }
        foreach ($submissions as $s) if ($s['status'] === 'rejected' && strtotime($s['updated_at']) < time() - $days * 86400) $sourceIds[] = $s['id'];
        $extraIds = [];
        foreach (['reports'=>$reports, 'moderation'=>$events] as $dataset=>$records) foreach ($records as $r) {
            if (strtotime($r['created_at'] ?? '') < time() - $days * 86400 && ($dataset !== 'reports' || ($r['status'] ?? '') === 'resolved')) $extraIds[] = $r['id'];
        }
        if (isset($options['apply'])) {
            foreach (['dogs'=>$dogs, 'submissions'=>$submissions] as $dataset=>$records) foreach ($records as $record) if (in_array($record['id'], $dataset === 'dogs' ? $ids : $sourceIds, true)) $archive[] = ['id'=>'archive-' . $dataset . '-' . $record['id'], 'dataset'=>$dataset, 'record'=>$record, 'archived_at'=>now_iso()];
            foreach (['reports'=>$reports, 'moderation'=>$events] as $dataset=>$records) foreach ($records as $r) {
                if (strtotime($r['created_at'] ?? '') >= time() - $days * 86400 || ($dataset === 'reports' && ($r['status'] ?? '') !== 'resolved')) continue;
                if ($dataset === 'moderation') $r['note'] = '';
                $archive[] = ['id'=>'archive-' . $dataset . '-' . $r['id'], 'dataset'=>$dataset, 'record'=>$r, 'archived_at'=>now_iso()];
            }
            $archivedIds = array_column($archive, 'id');
            if (!commit_datasets([
                'dogs'=>array_values(array_filter($dogs, static fn(array $r): bool => !in_array($r['id'], $ids, true))),
                'submissions'=>array_values(array_filter($submissions, static fn(array $r): bool => !in_array($r['id'], $sourceIds, true))),
                'reports'=>array_values(array_filter($reports, static fn(array $r): bool => !in_array('archive-reports-' . $r['id'], $archivedIds, true))),
                'moderation'=>array_values(array_filter($events, static fn(array $r): bool => !in_array('archive-moderation-' . $r['id'], $archivedIds, true))),
                'notifications'=>array_values(array_filter($notifications, static fn(array $r): bool => $r['status'] !== 'sent' || strtotime($r['sent_at'] ?? '') >= time() - 14 * 86400)),
                'archive'=>$archive,
            ])) throw new RuntimeException('No se pudo guardar el archivo.');
        }
        return array_merge($ids, $sourceIds, $extraIds);
    });
    echo json_encode(['mode'=>isset($options['apply']) ? 'applied' : 'dry-run', 'archive_ids'=>$ids], JSON_UNESCAPED_UNICODE) . "\n";
}
if (isset($options['retention'])) {
    $ids = [];
    foreach (read_dataset('archive') as $entry) {
        $r = normalize_record($entry['dataset'], $entry['record']);
        if (in_array($entry['dataset'], ['dogs', 'submissions'], true) && max(strtotime($r['updated_at']), strtotime($r['expires_at'])) < time() - $days * 86400) $ids[] = $r['source_submission_id'] ?? $r['id'];
    }
    // Only remove uploaded pixels; records remain searchable until a verified deletion request.
    foreach (array_unique($ids) as $id) {
        echo 'Uploads ' . $id . (isset($options['apply']) ? ' removed' : ' would be removed') . "\n";
        if (isset($options['apply'])) remove_upload_folder($id);
    }
    if (isset($options['apply'])) foreach (glob(PERRO_STORAGE . '/cache/share-*.jpg') ?: [] as $file) @unlink($file);
}
if (isset($options['backfill'])) {
    foreach (array_merge(read_dataset('dogs'), read_dataset('submissions')) as $record) foreach ($record['photos'] as $photo) {
        $file = PERRO_UPLOADS . '/' . basename($record['source_submission_id'] ?? $record['id']) . '/' . $photo;
        if (is_file($file)) prepare_photo_variants($file);
    }
    echo "Image variants prepared.\n";
}
if (isset($options['backup-dir'])) {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('La copia requiere la extensión ZIP.');
    $directory = (string) $options['backup-dir'];
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('No se pudo crear el destino.');
    $directory = realpath($directory); $root = realpath(PERRO_ROOT);
    if ($directory === false || $root === false || str_starts_with(strtolower($directory . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))) throw new RuntimeException('El respaldo debe quedar fuera de la raíz pública de Perro.');
    $backup = $directory . '/perro-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
    with_data_lock(static function () use ($backup): void {
        $zip = new ZipArchive();
        if ($zip->open($backup, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('No se pudo abrir la copia.');
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PERRO_STORAGE, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(PERRO_STORAGE) + 1));
                if (str_starts_with($relative, 'cache/') || str_ends_with($relative, '.lock') || str_contains($relative, '.tmp-')) continue;
                if (!$zip->addFile($file->getPathname(), 'storage/' . $relative)) throw new RuntimeException('No se pudo copiar un archivo.');
            }
        } finally { if (!$zip->close()) throw new RuntimeException('No se pudo completar la copia.'); }
    });
    $backups = glob($directory . '/perro-backup-*.zip') ?: []; rsort($backups);
    foreach (array_slice($backups, 14) as $old) if (!@unlink($old)) throw new RuntimeException('No se pudo rotar una copia antigua.');
    echo $backup . "\n";
}
if (isset($options['notifications']) || isset($options['digest'])) {
    $recipient = filter_var(getenv('PERRO_NOTIFY_EMAIL') ?: '', FILTER_VALIDATE_EMAIL);
    $sender = filter_var(getenv('PERRO_NOTIFY_FROM') ?: '', FILTER_VALIDATE_EMAIL);
    if (!$recipient || !$sender) throw new RuntimeException('Configurá PERRO_NOTIFY_EMAIL y PERRO_NOTIFY_FROM antes de habilitar envíos.');
    if (isset($options['notifications'])) {
        $sent = dispatch_notifications(static fn(string $body): bool => mail($recipient, 'Perro: revisión pendiente', $body, 'From: ' . $sender));
        echo $sent . " notifications sent.\n";
    }
    if (isset($options['digest'])) {
        $pending = count(array_filter(read_dataset('submissions'), static fn(array $s): bool => $s['status'] === 'pending'));
        $reports = count(array_filter(read_dataset('reports'), static fn(array $r): bool => $r['status'] === 'open'));
        $confirm = count(array_filter(read_dataset('dogs'), 'needs_confirmation'));
        if (!mail($recipient, 'Perro: resumen diario', "Pendientes: $pending\nReportes: $reports\nPor confirmar: $confirm\n" . app_url('admin'), 'From: ' . $sender)) throw new RuntimeException('No se pudo enviar el resumen.');
    }
}
if (isset($options['scrub-notes'])) {
    $count = with_data_lock(static function () use ($options): int {
        $events = read_dataset('moderation'); $archive = read_dataset('archive'); $count = 0;
        foreach ($events as &$event) if (!empty($event['note'])) { $count++; $event['note'] = ''; }
        unset($event);
        foreach ($archive as &$entry) if ($entry['dataset'] === 'moderation' && !empty($entry['record']['note'])) { $count++; $entry['record']['note'] = ''; }
        unset($entry);
        if (isset($options['apply']) && !commit_datasets(['moderation'=>$events, 'archive'=>$archive])) throw new RuntimeException('No se pudo depurar la bitácora.');
        return $count;
    });
    echo $count . (isset($options['apply']) ? ' historical notes scrubbed' : ' historical notes would be scrubbed') . "\n";
}
if (!$options) echo "Use --archive [--apply] [--days=180], --retention [--apply], --backup-dir=PATH, --backfill, --audit-phones, --scrub-notes [--apply], --notifications or --digest.\n";
