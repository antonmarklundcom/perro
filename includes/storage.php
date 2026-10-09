<?php
declare(strict_types=1);

const PERRO_STORAGE_SCHEMA = 2;

function storage_dataset_names(): array
{
    return ['dogs', 'submissions', 'reports', 'moderation', 'settings', 'security', 'archive', 'notifications', 'owner_actions', 'cleanup'];
}

function storage_validate_records(string $name, mixed $rows): void
{
    if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('No se puede leer el archivo de registros: ' . $name);
    $ids = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !is_string($row['id'] ?? null) || $row['id'] === '') throw new RuntimeException('Registro inválido: ' . $name);
        if (isset($ids[$row['id']])) throw new RuntimeException('Identificador duplicado: ' . $name);
        $ids[$row['id']] = true;
    }
}

function storage_write_json(string $file, array $value): bool
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $temp = $file . '.tmp-' . bin2hex(random_bytes(6));
    $handle = @fopen($temp, 'xb');
    if (!$handle) return false;
    $bytes = $json . "\n"; $offset = 0; $ok = true;
    try {
        while ($offset < strlen($bytes)) {
            $written = @fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) { $ok = false; break; }
            $offset += $written;
        }
        $ok = $ok && @fflush($handle);
        if ($ok && function_exists('fsync')) $ok = @fsync($handle);
    } finally { fclose($handle); }
    // Never unlink the current dataset to make replacement succeed.
    if (!$ok || !@rename($temp, $file)) { @unlink($temp); return false; }
    return true;
}

// Caller holds application.lock. Recovery validates the complete journal first.
function storage_recover_transaction(): void
{
    $journal = PERRO_DATA . '/transaction.json';
    if (!is_file($journal)) return;
    $pending = json_decode((string) @file_get_contents($journal), true);
    if (!is_array($pending) || !$pending) throw new RuntimeException('Diario de guardado inválido.');
    foreach ($pending as $name => $rows) {
        if (!in_array($name, storage_dataset_names(), true)) throw new RuntimeException('Dataset del diario inválido.');
        storage_validate_records($name, $rows);
    }
    foreach ($pending as $name => $rows) {
        if (!storage_write_json(PERRO_DATA . '/' . $name . '.json', $rows)) throw new RuntimeException('No se pudo recuperar el guardado pendiente.');
    }
    if (!@unlink($journal)) throw new RuntimeException('No se pudo finalizar la recuperación.');
    if (function_exists('clear_dataset_cache')) clear_dataset_cache();
}

function storage_read_state(string $file): ?array
{
    if (!file_exists($file) && !is_link($file)) return null;
    $state = is_file($file) && !is_link($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($state) || !is_int($state['schema_version'] ?? null) || $state['schema_version'] < 1 || $state['schema_version'] > PERRO_STORAGE_SCHEMA) throw new RuntimeException('Estado del almacenamiento inválido o incompatible.');
    return $state;
}

// This beacon survives loss of storage/data, unlike the inner schema marker.
function storage_read_installation(string $root): ?array
{
    $file = $root . '/storage/installation.json';
    if (!file_exists($file) && !is_link($file)) return null;
    $marker = is_file($file) && !is_link($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($marker) || ($marker['format'] ?? '') !== 'perro-installation-v1'
        || ($marker['schema_version'] ?? null) !== PERRO_STORAGE_SCHEMA
        || !in_array($marker['phase'] ?? '', ['initializing', 'installed'], true)
        || !is_string($marker['initialized_at'] ?? null) || strtotime($marker['initialized_at']) === false) throw new RuntimeException('Marcador de instalación inválido o incompatible.');
    return $marker;
}

function storage_write_installation(string $phase, string $initialized): void
{
    if (!storage_write_json(PERRO_STORAGE . '/installation.json', ['format'=>'perro-installation-v1', 'schema_version'=>PERRO_STORAGE_SCHEMA, 'phase'=>$phase, 'initialized_at'=>$initialized])) throw new RuntimeException('No se pudo registrar la instalación.');
}

// Only our explicitly marked, empty initialization may fill missing datasets.
// Uploaded files, unknown paths, journals and nonempty records are never reset.
function storage_assert_empty_initialization(bool $resume): void
{
    $storageEntries = @scandir(PERRO_STORAGE); $uploadEntries = @scandir(PERRO_UPLOADS); $dataEntries = @scandir(PERRO_DATA);
    if ($storageEntries === false || $uploadEntries === false || $dataEntries === false) throw new RuntimeException('No se puede verificar que el almacenamiento nuevo esté vacío.');
    foreach ($storageEntries as $entry) {
        if (in_array($entry, ['.', '..'], true)) continue;
        $file = PERRO_STORAGE . '/' . $entry;
        if (is_link($file)) throw new RuntimeException('Hay almacenamiento existente que requiere revisión.');
        if (in_array($entry, ['data', 'uploads'], true) && is_dir($file)) continue;
        if ($entry === '.htaccess' && is_file($file)) continue;
        if ($resume && $entry === 'installation.json') continue;
        if ($resume && preg_match('/^installation\.json\.tmp-[a-f0-9]{12}$/D', $entry) && is_file($file)) {
            $temporary = json_decode((string) file_get_contents($file), true);
            if (is_array($temporary) && ($temporary['format'] ?? '') === 'perro-installation-v1' && ($temporary['schema_version'] ?? null) === PERRO_STORAGE_SCHEMA && in_array($temporary['phase'] ?? '', ['initializing', 'installed'], true)) continue;
        }
        throw new RuntimeException('Hay almacenamiento existente que requiere revisión.');
    }
    foreach ($uploadEntries as $entry) {
        if (in_array($entry, ['.', '..'], true)) continue;
        $file = PERRO_UPLOADS . '/' . $entry;
        if ($entry !== '.gitkeep' || !is_file($file) || is_link($file) || filesize($file) !== 0) throw new RuntimeException('Hay fotos existentes; no se puede inicializar el almacenamiento.');
    }
    $datasets = array_map(static fn(string $name): string => $name . '.json', storage_dataset_names());
    foreach ($dataEntries as $entry) {
        if (in_array($entry, ['.', '..'], true)) continue;
        $file = PERRO_DATA . '/' . $entry;
        if (!is_file($file) || is_link($file)) throw new RuntimeException('Ruta de inicialización inválida.');
        if ($entry === 'application.lock' && filesize($file) === 0) continue;
        $original = preg_replace('/\.tmp-[a-f0-9]{12}$/D', '', $entry);
        if ($resume && $original === 'storage-state.json') {
            $state = storage_read_state($file);
            if (($state['schema_version'] ?? null) === PERRO_STORAGE_SCHEMA) continue;
        }
        if ($resume && in_array($original, $datasets, true)) {
            $rows = json_decode((string) file_get_contents($file), true);
            storage_validate_records($original, $rows);
            if ($rows === []) continue;
        }
        throw new RuntimeException('No se puede reanudar una inicialización con datos existentes.');
    }
}

// Read-only startup invariants for a restored installation; executes no code
// from the archive and does not create a beacon, lock, schema or empty dataset.
function storage_assert_installed(string $root, bool $requireBeacon = true): void
{
    foreach (['storage', 'storage/data', 'storage/uploads'] as $directory) if (!is_dir($root . '/' . $directory)) throw new RuntimeException('Falta una carpeta del almacenamiento instalado.');
    $marker = storage_read_installation($root);
    if (($requireBeacon && !$marker) || ($marker && $marker['phase'] !== 'installed')) throw new RuntimeException('La instalación no está completa.');
    $state = storage_read_state($root . '/storage/data/storage-state.json');
    if (($state['schema_version'] ?? null) !== PERRO_STORAGE_SCHEMA) throw new RuntimeException('Falta el estado del almacenamiento instalado.');
    foreach (storage_dataset_names() as $name) {
        $file = $root . '/storage/data/' . $name . '.json';
        if (!is_file($file)) throw new RuntimeException('Falta un dataset del almacenamiento instalado: ' . $name);
        storage_validate_records($name, json_decode((string) file_get_contents($file), true));
    }
}

function storage_prepare(): void
{
    $stateFile = PERRO_DATA . '/storage-state.json'; $marker = storage_read_installation(PERRO_ROOT);
    $legacyEvidence = is_file($stateFile) || is_file(PERRO_DATA . '/transaction.json')
        || array_filter(storage_dataset_names(), static fn(string $name): bool => file_exists(PERRO_DATA . '/' . $name . '.json'));
    if (($marker && $marker['phase'] === 'installed') || $legacyEvidence) {
        if (!is_dir(PERRO_DATA) || !is_dir(PERRO_UPLOADS)) throw new RuntimeException('Falta una carpeta del almacenamiento instalado.');
    }
    if ($marker && $marker['phase'] === 'installed' && !is_file($stateFile)) throw new RuntimeException('Falta el estado del almacenamiento instalado.');
    foreach ([PERRO_STORAGE, PERRO_DATA, PERRO_UPLOADS] as $directory) {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('No se pudo preparar el almacenamiento.');
    }
    $handle = @fopen(PERRO_DATA . '/application.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('No se pudo bloquear el almacenamiento.');
    try {
        $marker = storage_read_installation(PERRO_ROOT); $state = storage_read_state($stateFile);
        $names = storage_dataset_names();
        if ($marker && $marker['phase'] === 'installed') {
            if (($state['schema_version'] ?? null) !== PERRO_STORAGE_SCHEMA) throw new RuntimeException('Falta el estado del almacenamiento instalado.');
            storage_recover_transaction();
            foreach ($names as $name) if (!is_file(PERRO_DATA . '/' . $name . '.json')) throw new RuntimeException('Falta un dataset del almacenamiento instalado: ' . $name);
            return;
        }
        $legacy = !$marker && ($state !== null || is_file(PERRO_DATA . '/transaction.json')
            || array_filter($names, static fn(string $name): bool => file_exists(PERRO_DATA . '/' . $name . '.json')));
        if ($legacy) {
            storage_recover_transaction();
            $required = ($state['schema_version'] ?? 1) === PERRO_STORAGE_SCHEMA ? $names : array_slice($names, 0, 8);
            // Validate every existing dataset before adding the beacon or files.
            foreach ($required as $name) if (!is_file(PERRO_DATA . '/' . $name . '.json')) throw new RuntimeException('Falta un dataset del almacenamiento instalado: ' . $name);
            foreach ($names as $name) if (is_file(PERRO_DATA . '/' . $name . '.json')) storage_validate_records($name, json_decode((string) file_get_contents(PERRO_DATA . '/' . $name . '.json'), true));
        } else {
            storage_assert_empty_initialization($marker !== null);
            if (!$marker) {
                storage_write_installation('initializing', date(DATE_ATOM));
                $marker = storage_read_installation(PERRO_ROOT);
            }
        }
        foreach ($names as $name) {
            $file = PERRO_DATA . '/' . $name . '.json';
            if (!is_file($file) && !storage_write_json($file, [])) throw new RuntimeException('No se pudo inicializar un dataset nuevo.');
        }
        if (($state['schema_version'] ?? null) !== PERRO_STORAGE_SCHEMA) {
            $state = ['schema_version'=>PERRO_STORAGE_SCHEMA, 'initialized_at'=>$state['initialized_at'] ?? $marker['initialized_at'] ?? date(DATE_ATOM), 'upgraded_at'=>date(DATE_ATOM)];
            if (!storage_write_json($stateFile, $state)) throw new RuntimeException('No se pudo registrar la versión del almacenamiento.');
        }
        storage_write_installation('installed', $marker['initialized_at'] ?? $state['initialized_at'] ?? date(DATE_ATOM));
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

// Independent of bootstrap: never creates directories, locks or missing datasets.
function storage_doctor(string $root): array
{
    $data = $root . '/storage/data'; $uploads = $root . '/storage/uploads';
    $result = ['mode'=>'read-only', 'schema_version'=>null, 'installation_phase'=>null, 'counts'=>[], 'issues'=>[]];
    $issue = static function (string $dataset, string $code, ?string $id = null) use (&$result): void {
        $entry = ['dataset'=>$dataset, 'code'=>$code]; if ($id !== null) $entry['id'] = $id; $result['issues'][] = $entry;
    };
    $handle = is_file($data . '/application.lock') ? @fopen($data . '/application.lock', 'r') : false;
    if ($handle && !flock($handle, LOCK_SH)) { fclose($handle); throw new RuntimeException('No se pudo bloquear la lectura del diagnóstico.'); }
    try {
        if (!$handle) $issue('storage', 'missing_lock');
        foreach ([$data, $uploads] as $directory) if (!is_dir($directory)) $issue('storage', 'missing_' . basename($directory) . '_directory');
        try {
            $marker = storage_read_installation($root);
            $result['installation_phase'] = $marker['phase'] ?? 'legacy';
            if ($marker && $marker['phase'] !== 'installed') $issue('storage', 'incomplete_installation');
        } catch (Throwable $error) { $issue('storage', 'invalid_installation_marker'); }
        $state = is_file($data . '/storage-state.json') ? json_decode((string) file_get_contents($data . '/storage-state.json'), true) : null;
        $version = is_array($state) ? ($state['schema_version'] ?? null) : null;
        $result['schema_version'] = $version;
        if (!is_file($data . '/storage-state.json')) $issue('storage', 'unversioned');
        elseif (!is_int($version) || $version < 1 || $version > PERRO_STORAGE_SCHEMA) $issue('storage', 'incompatible_version');
        if (($marker['phase'] ?? '') === 'installed' && $version !== PERRO_STORAGE_SCHEMA) $issue('storage', 'missing_installed_state');
        if (is_file($data . '/transaction.json')) $issue('storage', 'pending_transaction');
        $tables = [];
        foreach (storage_dataset_names() as $name) {
            $file = $data . '/' . $name . '.json';
            if (!is_file($file)) {
                if (!in_array($name, ['cleanup', 'owner_actions'], true) || $version === PERRO_STORAGE_SCHEMA) $issue($name, 'missing_dataset');
                continue;
            }
            $rows = json_decode((string) file_get_contents($file), true);
            try { storage_validate_records($name, $rows); }
            catch (Throwable $error) { $issue($name, str_contains($error->getMessage(), 'duplicado') ? 'duplicate_id' : 'invalid_records'); continue; }
            $tables[$name] = array_column($rows, null, 'id'); $result['counts'][$name] = count($rows);
        }
        foreach (['dogs', 'submissions'] as $name) foreach ($tables[$name] ?? [] as $row) {
            foreach (['created_at', 'updated_at'] as $field) if (!is_string($row[$field] ?? null) || strtotime($row[$field]) === false) $issue($name, 'invalid_' . $field, $row['id']);
            foreach (['source_submission_id'=>'submissions', 'published_dog_id'=>'dogs'] as $field=>$target) if (!empty($row[$field]) && !isset($tables[$target][$row[$field]])) $issue($name, 'missing_' . $field, $row['id']);
            if ($name === 'dogs') foreach (['published_at', 'expires_at', 'last_confirmed_at'] as $field) if (!is_string($row[$field] ?? null) || strtotime($row[$field]) === false) $issue($name, 'invalid_' . $field, $row['id']);
            foreach (is_array($row['photos'] ?? null) ? $row['photos'] : [] as $photo) {
                $folder = $row['source_submission_id'] ?? $row['id'];
                if (!is_string($photo) || !is_string($folder) || preg_match('/^[a-z0-9_-]+$/Di', $folder) !== 1 || preg_match('/^[a-z0-9_-]+\.(?:jpe?g|png|webp)$/Di', $photo) !== 1 || !is_file($uploads . '/' . $folder . '/' . $photo)) $issue($name, 'missing_or_invalid_photo', $row['id']);
            }
        }
        $archived = [];
        foreach ($tables['archive'] ?? [] as $entry) {
            if (!in_array($entry['dataset'] ?? '', ['dogs', 'submissions', 'reports', 'moderation'], true) || !is_array($entry['record'] ?? null) || !is_string($entry['record']['id'] ?? null)) { $issue('archive', 'invalid_archived_record', $entry['id']); continue; }
            $archived[$entry['dataset']][$entry['record']['id']] = $entry['record'];
        }
        foreach ($tables['reports'] ?? [] as $row) if (!empty($row['dog_id']) && !isset($tables['dogs'][$row['dog_id']]) && !isset($archived['dogs'][$row['dog_id']])) $issue('reports', 'missing_dog', $row['id']);
        foreach ($tables['owner_actions'] ?? [] as $row) if (!empty($row['dog_id']) && !isset($tables['dogs'][$row['dog_id']]) && !isset($archived['dogs'][$row['dog_id']])) $issue('owner_actions', 'missing_dog', $row['id']);
        foreach (['dogs', 'submissions'] as $name) foreach ($archived[$name] ?? [] as $row) foreach (['source_submission_id'=>'submissions', 'published_dog_id'=>'dogs'] as $field=>$target) if (!empty($row[$field]) && !isset($tables[$target][$row[$field]]) && !isset($archived[$target][$row[$field]])) $issue('archive', 'missing_' . $field, $row['id']);
        $result['ok'] = !$result['issues'];
        return $result;
    } finally { if ($handle) { flock($handle, LOCK_UN); fclose($handle); } }
}
