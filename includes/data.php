<?php

declare(strict_types=1);

function dataset_path(string $name): string
{
    $allowed = ['dogs', 'submissions', 'reports', 'moderation', 'settings'];
    if (!in_array($name, $allowed, true)) {
        throw new InvalidArgumentException('Dataset no permitido.');
    }
    return PERRO_DATA . '/' . $name . '.json';
}

function read_dataset(string $name): array
{
    $file = dataset_path($name);
    $handle = @fopen($file, 'rb');
    if ($handle === false) {
        return [];
    }
    flock($handle, LOCK_SH);
    $json = stream_get_contents($handle) ?: '[]';
    flock($handle, LOCK_UN);
    fclose($handle);
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function write_dataset(string $name, array $records): bool
{
    $file = dataset_path($name);
    $temp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($temp, $json . "\n", LOCK_EX) === false) {
        @unlink($temp);
        return false;
    }
    if (DIRECTORY_SEPARATOR === '\\' && is_file($file)) {
        @unlink($file);
    }
    return @rename($temp, $file);
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
    $lockHandle = @fopen(dataset_path($dataset) . '.lock', 'c+');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
        if (is_resource($lockHandle)) fclose($lockHandle);
        return false;
    }
    $records = read_dataset($dataset);
    $found = false;
    foreach ($records as $index => $existing) {
        if (($existing['id'] ?? '') === ($record['id'] ?? '')) {
            $records[$index] = $record;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $records[] = $record;
    }
    $saved = write_dataset($dataset, $records);
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    return $saved;
}

function public_dogs(?string $listingType = null): array
{
    $dogs = array_filter(read_dataset('dogs'), static function (array $dog) use ($listingType): bool {
        if (($dog['status'] ?? '') !== 'published') {
            return false;
        }
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

function save_submission_images(string $submissionId): array
{
    global $config;
    if (!function_exists('imagecreatefromstring')) {
        return [];
    }
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) {
        return [];
    }
    $target = PERRO_UPLOADS . '/' . $submissionId;
    @mkdir($target, 0755, true);
    $saved = [];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $count = min(count($_FILES['photos']['name']), (int) $config['max_uploads']);
    for ($i = 0; $i < $count; $i++) {
        $error = (int) ($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $temp = (string) ($_FILES['photos']['tmp_name'][$i] ?? '');
        $size = (int) ($_FILES['photos']['size'][$i] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > (int) $config['max_upload_bytes'] || !is_uploaded_file($temp)) {
            continue;
        }
        $details = @getimagesize($temp);
        $mime = is_array($details) ? (string) ($details['mime'] ?? '') : '';
        $width = (int) ($details[0] ?? 0);
        $height = (int) ($details[1] ?? 0);
        if (!in_array($mime, $allowed, true) || $width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
            continue;
        }
        $sourceBytes = @file_get_contents($temp);
        $source = $sourceBytes !== false ? @imagecreatefromstring($sourceBytes) : false;
        if ($source === false) {
            continue;
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
        }
    }
    return $saved;
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
