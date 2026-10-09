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

function storage_prepare(): void
{
    $stateFile = PERRO_DATA . '/storage-state.json';
    if (is_file($stateFile) && (!is_dir(PERRO_DATA) || !is_dir(PERRO_UPLOADS))) throw new RuntimeException('Falta una carpeta del almacenamiento instalado.');
    foreach ([PERRO_STORAGE, PERRO_DATA, PERRO_UPLOADS] as $directory) {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('No se pudo preparar el almacenamiento.');
    }
    $handle = @fopen(PERRO_DATA . '/application.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('No se pudo bloquear el almacenamiento.');
    try {
        $state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
        if ($state !== null && (!is_array($state) || !is_int($state['schema_version'] ?? null) || $state['schema_version'] < 1 || $state['schema_version'] > PERRO_STORAGE_SCHEMA)) throw new RuntimeException('Versión de almacenamiento incompatible.');
        if (is_file($stateFile) && $state === null) throw new RuntimeException('Estado del almacenamiento inválido.');
        storage_recover_transaction();
        $names = storage_dataset_names(); $base = array_slice($names, 0, 8);
        $present = array_values(array_filter($base, static fn(string $name): bool => is_file(PERRO_DATA . '/' . $name . '.json')));
        $fresh = $state === null && !$present && !(glob(PERRO_DATA . '/*.json') ?: []);
        if (!$fresh) {
            $required = ($state['schema_version'] ?? 1) === PERRO_STORAGE_SCHEMA ? $names : $base;
            foreach ($required as $name) if (!is_file(PERRO_DATA . '/' . $name . '.json')) throw new RuntimeException('Falta un dataset del almacenamiento instalado: ' . $name);
            // Validate an unversioned/v1 installation before adding known new datasets.
            if (($state['schema_version'] ?? 1) < PERRO_STORAGE_SCHEMA) {
                foreach ($base as $name) storage_validate_records($name, json_decode((string) file_get_contents(PERRO_DATA . '/' . $name . '.json'), true));
            }
        }
        if ($fresh || ($state['schema_version'] ?? 1) < PERRO_STORAGE_SCHEMA) {
            foreach ($names as $name) {
                $file = PERRO_DATA . '/' . $name . '.json';
                if (is_file($file)) storage_validate_records($name, json_decode((string) file_get_contents($file), true));
                elseif (!storage_write_json($file, [])) throw new RuntimeException('No se pudo inicializar un dataset nuevo.');
            }
            $newState = ['schema_version'=>PERRO_STORAGE_SCHEMA, 'initialized_at'=>$state['initialized_at'] ?? date(DATE_ATOM), 'upgraded_at'=>date(DATE_ATOM)];
            if (!storage_write_json($stateFile, $newState)) throw new RuntimeException('No se pudo registrar la versión del almacenamiento.');
        }
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

// Independent of bootstrap: never creates directories, locks or missing datasets.
function storage_doctor(string $root): array
{
    $data = $root . '/storage/data'; $uploads = $root . '/storage/uploads';
    $result = ['mode'=>'read-only', 'schema_version'=>null, 'counts'=>[], 'issues'=>[]];
    $issue = static function (string $dataset, string $code, ?string $id = null) use (&$result): void {
        $entry = ['dataset'=>$dataset, 'code'=>$code]; if ($id !== null) $entry['id'] = $id; $result['issues'][] = $entry;
    };
    $handle = is_file($data . '/application.lock') ? @fopen($data . '/application.lock', 'r') : false;
    if ($handle && !flock($handle, LOCK_SH)) { fclose($handle); throw new RuntimeException('No se pudo bloquear la lectura del diagnóstico.'); }
    try {
        if (!$handle) $issue('storage', 'missing_lock');
        $state = is_file($data . '/storage-state.json') ? json_decode((string) file_get_contents($data . '/storage-state.json'), true) : null;
        $version = is_array($state) ? ($state['schema_version'] ?? null) : null;
        $result['schema_version'] = $version;
        if (!is_file($data . '/storage-state.json')) $issue('storage', 'unversioned');
        elseif (!is_int($version) || $version < 1 || $version > PERRO_STORAGE_SCHEMA) $issue('storage', 'incompatible_version');
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
