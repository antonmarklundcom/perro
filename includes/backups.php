<?php
declare(strict_types=1);

function create_storage_backup(string $directory): string
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('La copia requiere la extensión ZIP.');
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('No se pudo crear el destino.');
    $directory = realpath($directory); $root = realpath(PERRO_ROOT);
    if (!$directory || !$root || str_starts_with(strtolower($directory . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))) throw new RuntimeException('El respaldo debe quedar fuera de la raíz pública de Perro.');
    $backup = $directory . '/perro-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
    try {
        with_data_lock(static function () use ($backup): void {
            $zip = new ZipArchive();
            if ($zip->open($backup, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('No se pudo abrir la copia.');
            $manifest = ['format'=>'perro-storage-backup-v1', 'schema_version'=>PERRO_STORAGE_SCHEMA, 'release'=>perro_release_id(), 'created_at'=>now_iso(), 'counts'=>[], 'files'=>[]];
            try {
                foreach (storage_dataset_names() as $name) $manifest['counts'][$name] = count(read_dataset($name));
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PERRO_STORAGE, FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (!$file->isFile() || $file->isLink()) continue;
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(PERRO_STORAGE) + 1));
                    if (str_starts_with($relative, 'cache/') || str_ends_with($relative, '.lock') || str_contains($relative, '.tmp-')) continue;
                    $name = 'storage/' . $relative;
                    if (!$zip->addFile($file->getPathname(), $name)) throw new RuntimeException('No se pudo copiar un archivo.');
                    $manifest['files'][$name] = ['sha256'=>hash_file('sha256', $file->getPathname()), 'bytes'=>$file->getSize()];
                }
                ksort($manifest['files']);
                if (!$zip->addFromString('storage-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) throw new RuntimeException('No se pudo guardar el manifiesto.');
            } finally { if (!$zip->close()) throw new RuntimeException('No se pudo completar la copia.'); }
        });
        @chmod($backup, 0600);
        if (file_put_contents($backup . '.sha256', hash_file('sha256', $backup) . "\n", LOCK_EX) === false) throw new RuntimeException('No se pudo guardar el checksum.');
        @chmod($backup . '.sha256', 0600);
    } catch (Throwable $error) { @unlink($backup); @unlink($backup . '.sha256'); throw $error; }
    $backups = glob($directory . '/perro-backup-*.zip') ?: []; rsort($backups);
    foreach (array_slice($backups, 14) as $old) {
        if (!@unlink($old)) throw new RuntimeException('No se pudo rotar una copia antigua.');
        if (is_file($old . '.sha256') && !@unlink($old . '.sha256')) throw new RuntimeException('No se pudo rotar un checksum antiguo.');
    }
    return $backup;
}

// Does not load/bootstrap the installation or execute anything from the archive.
function verify_storage_backup(string $archive, string $destination, string $sourceRoot): array
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('La verificación requiere ZIP.');
    $sourceRoot = realpath($sourceRoot); $parent = realpath(dirname($destination));
    if (!$sourceRoot || !$parent || is_link($destination) || (file_exists($destination) && !is_dir($destination))) throw new RuntimeException('Destino de verificación inválido.');
    $checked = $parent . DIRECTORY_SEPARATOR . basename($destination);
    if (str_starts_with(strtolower($checked . DIRECTORY_SEPARATOR), strtolower($sourceRoot . DIRECTORY_SEPARATOR))) throw new RuntimeException('Verificá la copia fuera de la instalación de origen.');
    if (is_dir($destination) && count(scandir($destination)) !== 2) throw new RuntimeException('La verificación requiere una carpeta vacía.');
    if (!is_dir($destination) && !mkdir($destination, 0700)) throw new RuntimeException('No se pudo crear el destino privado.');
    $destination = realpath($destination);
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('No se pudo abrir el respaldo.');
    try {
        if ($zip->numFiles > 100000) throw new RuntimeException('Demasiados archivos en la copia.');
        $raw = $zip->getFromName('storage-manifest.json');
        if ($raw === false || strlen($raw) > 16000000) throw new RuntimeException('Falta un manifiesto verificable.');
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'perro-storage-backup-v1' || ($manifest['schema_version'] ?? null) !== PERRO_STORAGE_SCHEMA || !is_array($manifest['files'] ?? null) || !is_array($manifest['counts'] ?? null)) throw new RuntimeException('Manifiesto incompatible.');
        $names = []; $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); $name = $stat['name'];
            if (isset($names[$name])) throw new RuntimeException('Nombre duplicado en la copia.');
            $names[$name] = true;
            if ($name === 'storage-manifest.json') continue;
            if (!str_starts_with($name, 'storage/') || str_contains($name, '\\') || str_contains($name, "\0") || array_intersect(explode('/', $name), ['..', '.', '']) || !isset($manifest['files'][$name])) throw new RuntimeException('Ruta inválida en la copia.');
            $opsys = 0; $attributes = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('No se permiten enlaces en la copia.');
            if ($stat['size'] > 536870912 || ($total += $stat['size']) > 5368709120) throw new RuntimeException('Copia demasiado grande para esta verificación.');
            $entry = $manifest['files'][$name];
            if (!is_array($entry) || !is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256']) || ($entry['bytes'] ?? null) !== $stat['size']) throw new RuntimeException('Metadatos de archivo inválidos.');
        }
        if (count($names) !== count($manifest['files']) + 1) throw new RuntimeException('La lista de archivos no coincide.');
        foreach ($manifest['files'] as $name => $entry) {
            if (!isset($names[$name])) throw new RuntimeException('Falta un archivo de la copia.');
            $target = $destination . '/' . $name;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) throw new RuntimeException('No se pudo preparar la extracción.');
            $input = $zip->getStream($name); $output = @fopen($target, 'xb');
            if (!$input || !$output) throw new RuntimeException('No se pudo extraer un archivo.');
            $hash = hash_init('sha256'); $bytes = 0;
            try {
                while (!feof($input)) {
                    $chunk = fread($input, 65536);
                    if ($chunk === false) throw new RuntimeException('No se pudo leer un archivo de la copia.');
                    $bytes += strlen($chunk); hash_update($hash, $chunk);
                    if ($bytes > $entry['bytes'] || fwrite($output, $chunk) !== strlen($chunk)) throw new RuntimeException('Extracción incompleta.');
                }
            } finally { fclose($input); fclose($output); }
            @chmod($target, 0600);
            if ($bytes !== $entry['bytes'] || !hash_equals($entry['sha256'], hash_final($hash))) throw new RuntimeException('Checksum de archivo incorrecto.');
        }
        $counts = [];
        foreach (storage_dataset_names() as $name) {
            $file = $destination . '/storage/data/' . $name . '.json';
            if (!is_file($file)) throw new RuntimeException('Falta un dataset respaldado.');
            $rows = json_decode((string) file_get_contents($file), true); storage_validate_records($name, $rows);
            $counts[$name] = count($rows);
            if (($manifest['counts'][$name] ?? null) !== $counts[$name]) throw new RuntimeException('No coincide el conteo respaldado.');
        }
        $doctor = storage_doctor($destination);
        $issues = array_values(array_filter($doctor['issues'], static fn(array $i): bool => $i['code'] !== 'missing_lock'));
        return ['ok'=>!$issues, 'mode'=>'isolated-restore-verification', 'schema_version'=>$manifest['schema_version'], 'release'=>$manifest['release'] ?? null, 'files'=>count($manifest['files']), 'counts'=>$counts, 'issues'=>$issues, 'archive_sha256'=>hash_file('sha256', $archive)];
    } finally { $zip->close(); }
}
