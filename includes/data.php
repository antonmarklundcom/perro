<?php

declare(strict_types=1);

function dataset_path(string $name): string
{
    $allowed = ['dogs', 'submissions', 'reports', 'moderation', 'settings', 'security', 'archive', 'notifications'];
    if (!in_array($name, $allowed, true)) {
        throw new InvalidArgumentException('Dataset no permitido.');
    }
    return PERRO_DATA . '/' . $name . '.json';
}

function read_dataset(string $name): array
{
    dataset_path($name);
    if (isset($GLOBALS['perro_datasets'][$name])) return $GLOBALS['perro_datasets'][$name];
    return with_data_read_lock(static function () use ($name): array {
        $json = @file_get_contents(dataset_path($name));
        $decoded = $json === false ? null : json_decode($json, true);
        if (!is_array($decoded) || !array_is_list($decoded)
            || array_filter($decoded, static fn($record): bool => !is_array($record) || !is_string($record['id'] ?? null))) {
            throw new RuntimeException('No se puede leer el archivo de registros: ' . $name);
        }
        $GLOBALS['perro_dataset_reads'][$name] = ($GLOBALS['perro_dataset_reads'][$name] ?? 0) + 1;
        return $GLOBALS['perro_datasets'][$name] = array_map(static fn(array $record): array => normalize_record($name, $record), $decoded);
    });
}

function normalize_record(string $dataset, array $record): array
{
    if (!in_array($dataset, ['dogs', 'submissions', 'reports'], true)) return $record;
    unset($record['needs_repair']);
    $defaults = array_fill_keys(['name', 'city', 'department', 'slug', 'description', 'approximate_age', 'age_group', 'sex', 'size', 'breed_label', 'compatibility', 'health_information', 'vaccination_status', 'sterilization_status', 'adoption_requirements', 'last_location', 'incident_date', 'submitter_name', 'email', 'whatsapp', 'reference', 'contact_name', 'contact_whatsapp', 'internal_note', 'contact', 'reason', 'dog_name', 'dog_id'], '');
    foreach ($defaults as $key => $value) if (!is_string($record[$key] ?? null)) $record[$key] = $value;
    $record['name'] = $record['name'] ?: 'Sin nombre';
    $record['listing_type'] = in_array($record['listing_type'] ?? '', ['adoption', 'lost', 'found'], true) ? $record['listing_type'] : 'adoption';
    $record['status'] = is_string($record['status'] ?? null) ? $record['status'] : ($dataset === 'reports' ? 'open' : 'pending');
    $record['adoption_status'] = is_string($record['adoption_status'] ?? null) ? $record['adoption_status'] : 'available';
    $record['photos'] = array_values(array_filter(is_array($record['photos'] ?? null) ? $record['photos'] : [], static fn($p): bool => is_string($p) && preg_match('/^[a-z0-9_-]+\.(?:jpe?g|png|webp)$/Di', $p) === 1));
    foreach (['source_submission_id', 'published_dog_id'] as $key) if (isset($record[$key]) && !is_string($record[$key])) unset($record[$key]);
    if (!is_array($record['consents'] ?? null)) $record['consents'] = [];
    $requiredDates = match ($dataset) { 'dogs'=>['created_at', 'published_at', 'updated_at', 'last_confirmed_at', 'expires_at'], 'submissions'=>['created_at', 'updated_at'], default=>['created_at'] };
    foreach (['created_at', 'published_at', 'updated_at', 'last_confirmed_at', 'expires_at'] as $key) {
        if (!is_string($record[$key] ?? null) || strtotime($record[$key]) === false) {
            if (in_array($key, $requiredDates, true)) $record['needs_repair'] = true;
            $record[$key] = date(DATE_ATOM, 0);
        }
    }
    // Invalid contacts never become public links; private originals remain available for correction.
    $record['contact_whatsapp'] = valid_whatsapp($record['contact_whatsapp']);
    return $record;
}

function clear_dataset_cache(): void
{
    $GLOBALS['perro_datasets'] = $GLOBALS['perro_indexes'] = $GLOBALS['perro_public'] = [];
    unset($GLOBALS['perro_report_counts'], $GLOBALS['perro_hint_index']);
}

function with_data_read_lock(callable $operation): mixed
{
    if (($GLOBALS['perro_lock_depth'] ?? 0) > 0) return $operation();
    $handle = @fopen(PERRO_DATA . '/application.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_SH)) throw new RuntimeException('No se pudo leer el almacenamiento.');
    try {
        if (is_file(PERRO_DATA . '/transaction.json')) {
            flock($handle, LOCK_UN); fclose($handle); $handle = null;
            return with_data_lock($operation);
        }
        return $operation();
    } finally { if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); } }
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
    $GLOBALS['perro_lock_depth'] = $depth;
    clear_dataset_cache();
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
        $GLOBALS['perro_lock_depth'] = $depth;
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
    if ($saved) {
        $GLOBALS['perro_datasets'][$name] = array_map(static fn(array $r): array => normalize_record($name, $r), array_values($records));
        unset($GLOBALS['perro_indexes'][$name]);
        $GLOBALS['perro_public'] = [];
        unset($GLOBALS['perro_report_counts'], $GLOBALS['perro_hint_index']);
    }
    return $saved;
}

function find_record(string $dataset, string $id): ?array
{
    if (!isset($GLOBALS['perro_indexes'][$dataset])) $GLOBALS['perro_indexes'][$dataset] = array_column(read_dataset($dataset), null, 'id');
    return $GLOBALS['perro_indexes'][$dataset][$id] ?? null;
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
    $key = $listingType ?? 'all';
    if (isset($GLOBALS['perro_public'][$key])) return $GLOBALS['perro_public'][$key];
    $dogs = array_filter(read_dataset('dogs'), static function (array $dog) use ($listingType): bool {
        if (($dog['status'] ?? '') !== 'published') {
            return false;
        }
        if (in_array($dog['adoption_status'] ?? '', ['adopted', 'reunited'], true)) return false;
        if (empty($dog['expires_at']) || strtotime((string) $dog['expires_at']) <= time()) {
            return false;
        }
        return $listingType === null || ($dog['listing_type'] ?? 'adoption') === $listingType;
    });
    usort($dogs, static function (array $a, array $b) use ($listingType): int {
        if ($listingType === 'adoption' && ($a['adoption_status'] === 'reserved') !== ($b['adoption_status'] === 'reserved')) return $a['adoption_status'] === 'reserved' ? 1 : -1;
        return strtotime($b['published_at']) <=> strtotime($a['published_at']);
    });
    return $GLOBALS['perro_public'][$key] = array_values($dogs);
}

function public_dog_by_slug(string $slug): ?array
{
    if (!isset($GLOBALS['perro_public']['slugs'])) $GLOBALS['perro_public']['slugs'] = array_column(public_dogs(), null, 'slug');
    return $GLOBALS['perro_public']['slugs'][$slug] ?? null;
}

function rate_address(): string
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $packed = @inet_pton($address);
    if ($packed !== false && strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") return inet_ntop(substr($packed, 12));
    return $packed !== false && strlen($packed) === 16 ? bin2hex(substr($packed, 0, 8)) . '/64' : $address;
}

function record_moderation(string $action, string $recordId, string $note = ''): void
{
    save_record('moderation', [
        'id' => random_id('mod-'),
        'action' => $action,
        'record_id' => $recordId,
        // Free-text notes belong to the deletable record, never the permanent activity log.
        'note' => '',
        'admin' => ($_SESSION['perro_admin'] ?? false) === true ? current_admin_account_id() : 'system',
        'created_at' => now_iso(),
    ]);
}

function jpeg_orientation(string $file): int
{
    if (!function_exists('exif_read_data')) return 1;
    $exif = @exif_read_data($file, 'IFD0', true, false);
    $orientation = $exif['IFD0']['Orientation'] ?? 1;
    return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
}

function rotate_photo_image(GdImage &$image, int $clockwise): bool
{
    if ($clockwise === 0) return true;
    $rotated = @imagerotate($image, -$clockwise, imagecolorallocate($image, 255, 255, 255));
    if ($rotated === false) return false;
    imagedestroy($image);
    $image = $rotated;
    return true;
}

function orient_photo_image(GdImage &$image, int $orientation): bool
{
    if (in_array($orientation, [2, 5, 7], true) && !imageflip($image, IMG_FLIP_HORIZONTAL)) return false;
    if ($orientation === 4 && !imageflip($image, IMG_FLIP_VERTICAL)) return false;
    return rotate_photo_image($image, [3=>180, 5=>270, 6=>90, 7=>90, 8=>270][$orientation] ?? 0);
}

// Always flatten/re-encode pixels into JPEG, dropping EXIF and retaining the upload size limit.
function write_clean_photo(GdImage $source, string $destination): bool
{
    $width = imagesx($source); $height = imagesy($source);
    $scale = min(1, 1800 / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $clean = @imagecreatetruecolor($newWidth, $newHeight);
    if (!$clean) return false;
    try {
        imagefill($clean, 0, 0, imagecolorallocate($clean, 255, 255, 255));
        return imagecopyresampled($clean, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)
            && @imagejpeg($clean, $destination, 86) && is_file($destination) && filesize($destination) > 0;
    } finally { imagedestroy($clean); }
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
        $filename = bin2hex(random_bytes(10)) . '.jpg';
        $destination = $target . '/' . $filename;
        $partial = $destination . '.tmp';
        try {
            $oriented = $mime !== 'image/jpeg' || orient_photo_image($source, jpeg_orientation($temp));
            $written = $oriented && write_clean_photo($source, $partial) && @rename($partial, $destination);
        } finally { imagedestroy($source); @unlink($partial); }
        if ($written) {
            prepare_photo_variants($destination);
            $saved[] = $filename;
        } else {
            $errors[] = 'No pudimos guardar una foto. Intentá nuevamente.';
            break;
        }
    }
    if ($errors) {
        foreach ($saved as $filename) delete_photo_files($target . '/' . $filename);
        return [];
    }
    return $saved;
}

function public_request_control(string $namespace, int $limit): ?array
{
        $control = find_record('security', 'public-rate') ?? ['id' => 'public-rate', 'secret' => bin2hex(random_bytes(32)), 'buckets' => []];
        $buckets = array_filter($control['buckets'], static fn(array $bucket): bool => ($bucket['started'] ?? 0) > time() - 3600);
        $key = $namespace . ':' . hash_hmac('sha256', rate_address(), $control['secret']);
        $globalKey = $namespace . ':global';
        $globalBucket = $buckets[$globalKey] ?? ['started' => time(), 'count' => 0];
        if ($globalBucket['count'] >= 1000) return null;
        $bucket = $buckets[$key] ?? ['started' => time(), 'count' => 0];
        if ($bucket['count'] >= $limit) return null;
        $bucket['count']++;
        $buckets[$key] = $bucket;
        $globalBucket['count']++;
        $buckets[$globalKey] = $globalBucket;
        if (count($buckets) > 5000) $buckets = array_slice($buckets, -5000, null, true);
        $control['buckets'] = $buckets;
        return $control;
}

function allow_public_request(string $namespace, int $limit): bool
{
    return with_data_lock(static function () use ($namespace, $limit): bool {
        $control = public_request_control($namespace, $limit);
        if ($control === null) return false;
        if (!save_record('security', $control)) throw new RuntimeException('No se pudo guardar el control de solicitudes.');
        return true;
    });
}

function save_public_record(string $dataset, array $record, string $namespace, int $limit): string
{
    return with_data_lock(static function () use ($dataset, $record, $namespace, $limit): string {
        $control = public_request_control($namespace, $limit);
        if ($control === null) return 'limited';
        $security = read_dataset('security');
        $security = array_values(array_filter($security, static fn(array $r): bool => $r['id'] !== 'public-rate'));
        $security[] = $control;
        $records = read_dataset($dataset); $records[] = $record;
        return commit_datasets(['security'=>$security, $dataset=>$records]) ? 'saved' : 'failed';
    });
}

function remove_upload_folder(string $id): void
{
    if (!preg_match('/^[a-z0-9_-]+$/Di', $id)) throw new InvalidArgumentException('Carpeta de fotos inválida.');
    $directory = PERRO_UPLOADS . '/' . $id;
    if (!is_dir($directory)) {
        return;
    }
    $resolved = realpath($directory); $root = realpath(PERRO_UPLOADS);
    if (!$resolved || !$root || dirname($resolved) !== $root || is_link($directory)) throw new RuntimeException('Carpeta de fotos fuera del almacenamiento.');
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file)) {
            if (!@unlink($file)) throw new RuntimeException('No se pudo eliminar una foto.');
        }
    }
    if (!@rmdir($directory)) throw new RuntimeException('No se pudo eliminar la carpeta de fotos.');
}
