<?php

declare(strict_types=1);

function dataset_path(string $name): string
{
    $allowed = ['dogs', 'submissions', 'reports', 'moderation', 'settings', 'security'];
    if (!in_array($name, $allowed, true)) {
        throw new InvalidArgumentException('Dataset no permitido.');
    }
    return PERRO_DATA . '/' . $name . '.json';
}

function read_dataset(string $name): array
{
    return with_data_lock(static function () use ($name): array {
        $json = @file_get_contents(dataset_path($name));
        $decoded = $json === false ? null : json_decode($json, true);
        if (!is_array($decoded) || !array_is_list($decoded)
            || array_filter($decoded, static fn($record): bool => !is_array($record) || !is_string($record['id'] ?? null))) {
            throw new RuntimeException('No se puede leer el archivo de registros: ' . $name);
        }
        return $decoded;
    });
}

// Serialize reads and mutations, including multi-file commits. A durable journal
// is replayed before any reader sees data after a failed/interrupted commit.
function with_data_lock(callable $operation): mixed
{
    static $depth = 0;
    if ($depth > 0) {
        return $operation();
    }
    $handle = @fopen(PERRO_DATA . '/application.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('No se pudo bloquear el almacenamiento.');
    }
    $depth++;
    try {
        $journal = PERRO_DATA . '/transaction.json';
        if (is_file($journal)) {
            $pending = json_decode((string) @file_get_contents($journal), true);
            if (!is_array($pending) || !$pending) throw new RuntimeException('Diario de guardado inválido.');
            foreach ($pending as $name => $records) {
                if (!is_array($records) || !write_dataset($name, $records)) {
                    throw new RuntimeException('No se pudo recuperar el guardado pendiente.');
                }
            }
            if (!@unlink($journal)) throw new RuntimeException('No se pudo finalizar la recuperación.');
        }
        return $operation();
    } finally {
        $depth--;
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function commit_datasets(array $datasets): bool
{
    return with_data_lock(static function () use ($datasets): bool {
        foreach ($datasets as $name => $records) dataset_path($name);
        $journal = PERRO_DATA . '/transaction.json';
        $temp = $journal . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($datasets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($temp, $json, LOCK_EX) === false || !@rename($temp, $journal)) {
            @unlink($temp);
            return false;
        }
        foreach ($datasets as $name => $records) {
            if (!write_dataset($name, $records)) throw new RuntimeException('Guardado pendiente de recuperación.');
        }
        if (!@unlink($journal)) throw new RuntimeException('No se pudo confirmar el guardado.');
        return true;
    });
}

function write_dataset(string $name, array $records): bool
{
    $file = dataset_path($name);
    $temp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($temp, $json . "\n", LOCK_EX) === false) {
        @unlink($temp);
        return false;
    }
    if (DIRECTORY_SEPARATOR === '\\' && is_file($file)) {
        @unlink($file);
    }
    $saved = @rename($temp, $file);
    if (!$saved) @unlink($temp);
    return $saved;
}

function find_record(string $dataset, string $id): ?array
{
    foreach (read_dataset($dataset) as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }
    return null;
}

function save_record(string $dataset, array $record): bool
{
    return with_data_lock(static function () use ($dataset, $record): bool {
        $records = read_dataset($dataset);
        $found = false;
        foreach ($records as $index => $existing) {
            if ($existing['id'] === $record['id']) {
                $records[$index] = $record;
                $found = true;
                break;
            }
        }
        if (!$found) $records[] = $record;
        return commit_datasets([$dataset => $records]);
    });
}

function public_dogs(?string $listingType = null): array
{
    $dogs = array_filter(read_dataset('dogs'), static function (array $dog) use ($listingType): bool {
        if (($dog['status'] ?? '') !== 'published') {
            return false;
        }
        if (in_array($dog['adoption_status'] ?? '', ['adopted', 'reunited'], true)) return false;
        if (!empty($dog['expires_at']) && strtotime((string) $dog['expires_at']) < time()) {
            return false;
        }
        return $listingType === null || ($dog['listing_type'] ?? 'adoption') === $listingType;
    });
    usort($dogs, static fn(array $a, array $b): int => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
    return array_values($dogs);
}

function public_dog_by_slug(string $slug): ?array
{
    foreach (public_dogs() as $dog) {
        if (($dog['slug'] ?? '') === $slug) {
            return $dog;
        }
    }
    return null;
}

function record_moderation(string $action, string $recordId, string $note = ''): void
{
    save_record('moderation', [
        'id' => random_id('mod-'),
        'action' => $action,
        'record_id' => $recordId,
        'note' => $note,
        'admin' => 'admin',
        'created_at' => now_iso(),
    ]);
}

function save_submission_images(string $submissionId, ?array &$errors = null): array
{
    global $config;
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) {
        return [];
    }
    $files = $_FILES['photos'];
    $indexes = array_keys(array_filter($files['error'] ?? [], static fn($error): bool => $error !== UPLOAD_ERR_NO_FILE));
    if (!$indexes) return [];
    if (!function_exists('imagecreatefromstring')) {
        $errors[] = 'No pudimos procesar las fotos. Avisale al equipo para que habilite GD en el servidor.';
        return [];
    }
    if (count($indexes) > (int) $config['max_uploads']) {
        $errors[] = 'Podés adjuntar hasta ' . $config['max_uploads'] . ' fotos.';
        return [];
    }
    $target = PERRO_UPLOADS . '/' . basename($submissionId);
    if (!is_dir($target) && !@mkdir($target, 0755, true)) {
        $errors[] = 'No pudimos preparar el guardado de fotos. Intentá nuevamente.';
        return [];
    }
    $saved = [];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    foreach ($indexes as $i) {
        $error = (int) ($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $temp = (string) ($_FILES['photos']['tmp_name'][$i] ?? '');
        $size = (int) ($_FILES['photos']['size'][$i] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > (int) $config['max_upload_bytes'] || !is_uploaded_file($temp)) {
            $errors[] = 'Una foto no llegó completa o supera el límite de 5 MB. Seleccioná las fotos nuevamente.';
            break;
        }
        $details = @getimagesize($temp);
        $mime = is_array($details) ? (string) ($details['mime'] ?? '') : '';
        $width = (int) ($details[0] ?? 0);
        $height = (int) ($details[1] ?? 0);
        $memoryLimit = ini_bytes((string) ini_get('memory_limit'));
        $estimated = memory_get_usage(true) + ($width * $height * 8) + (1800 * 1800 * 8) + 16 * 1024 * 1024;
        if (!in_array($mime, $allowed, true) || $width < 1 || $height < 1 || $width * $height > 8000000
            || ($memoryLimit > 0 && $estimated > $memoryLimit)) {
            $errors[] = 'Usá fotos JPG, PNG o WebP de hasta 8 megapíxeles. Reducí las fotos grandes antes de enviar.';
            break;
        }
        $sourceBytes = @file_get_contents($temp);
        $source = $sourceBytes !== false ? @imagecreatefromstring($sourceBytes) : false;
        if ($source === false) {
            $errors[] = 'Una foto no es una imagen válida. Seleccioná otra foto.';
            break;
        }
        $scale = min(1, 1800 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $clean = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($clean, 255, 255, 255);
        imagefill($clean, 0, 0, $white);
        imagecopyresampled($clean, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $filename = bin2hex(random_bytes(10)) . '.jpg';
        $written = @imagejpeg($clean, $target . '/' . $filename, 86);
        imagedestroy($source);
        imagedestroy($clean);
        if ($written) {
            $saved[] = $filename;
        } else {
            $errors[] = 'No pudimos guardar una foto. Intentá nuevamente.';
            break;
        }
    }
    if ($errors) {
        foreach ($saved as $filename) @unlink($target . '/' . $filename);
        return [];
    }
    return $saved;
}

function allow_public_request(string $namespace, int $limit): bool
{
    return with_data_lock(static function () use ($namespace, $limit): bool {
        $control = find_record('security', 'public-rate') ?? ['id' => 'public-rate', 'secret' => bin2hex(random_bytes(32)), 'buckets' => []];
        $buckets = array_filter($control['buckets'], static fn(array $bucket): bool => ($bucket['started'] ?? 0) > time() - 3600);
        $key = $namespace . ':' . hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $control['secret']);
        $bucket = $buckets[$key] ?? ['started' => time(), 'count' => 0];
        if ($bucket['count'] >= $limit) return false;
        $bucket['count']++;
        $buckets[$key] = $bucket;
        if (count($buckets) > 5000) $buckets = array_slice($buckets, -5000, null, true);
        $control['buckets'] = $buckets;
        if (!save_record('security', $control)) throw new RuntimeException('No se pudo guardar el control de solicitudes.');
        return true;
    });
}

function remove_upload_folder(string $id): void
{
    $directory = PERRO_UPLOADS . '/' . basename($id);
    if (!is_dir($directory)) {
        return;
    }
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($directory);
}
