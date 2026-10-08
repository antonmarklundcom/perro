<?php

declare(strict_types=1);

function enquiry_message(array $dog): string
{
    $identity = $dog['name'] . ' · Ficha ' . listing_share_code($dog) . ' · ' . $dog['city'] . "\n" . app_url('perro/' . $dog['slug']);
    return ($dog['listing_type'] ?? 'adoption') === 'adoption'
        ? 'Hola, quisiera consultar por la adopción gratuita de ' . $identity . "\nVivo en: \nTipo de hogar: \nOtros animales: \nExperiencia con perros: "
        : 'Hola, quisiera aportar o consultar información sobre ' . $identity;
}

function listing_contact_url(array $dog): string
{
    $number = valid_whatsapp((string) ($dog['contact_whatsapp'] ?? ''));
    return $number !== '' ? 'https://wa.me/' . $number . '?text=' . rawurlencode(enquiry_message($dog)) : project_whatsapp_url(enquiry_message($dog));
}

function report_categories(): array
{
    return ['availability'=>'Ya no está disponible', 'sale'=>'Venta, cobro o seña', 'privacy'=>'Datos privados o foto sin permiso', 'incorrect'=>'Información incorrecta', 'welfare'=>'Posible riesgo para el animal', 'other'=>'Otro motivo'];
}

function format_date(string $value): string
{
    $date = strtotime($value);
    return $date === false || $date <= 0 ? 'Sin fecha informada' : date('d/m/Y', $date);
}

function needs_confirmation(array $dog): bool
{
    if (in_array($dog['adoption_status'], ['adopted', 'reunited'], true) || !in_array($dog['status'], ['published', 'expired'], true)) return false;
    $expires = strtotime($dog['expires_at']);
    return ($expires <= time() + 7 * 86400 && $expires >= time() - 30 * 86400)
        || ($dog['adoption_status'] === 'reserved' && strtotime($dog['status_changed_at'] ?? $dog['updated_at']) < time() - 14 * 86400);
}

function admin_search_matches(array $record, string $q): bool
{
    if ($q === '') return true;
    $source = !empty($record['source_submission_id']) ? find_record('submissions', $record['source_submission_id']) : [];
    $linkedId = $record['published_dog_id'] ?? $record['dog_id'] ?? '';
    $linked = $linkedId !== '' ? find_record('dogs', $linkedId) : [];
    $values = [];
    foreach ([$record, $source ?? [], $linked ?? []] as $item) {
        foreach (['name', 'dog_name', 'dog_id', 'record_id', 'action', 'city', 'department', 'reference', 'submitter_name', 'whatsapp', 'email', 'slug', 'id'] as $field) $values[] = $item[$field] ?? '';
        if (!empty($item['slug'])) $values[] = listing_share_code($item);
    }
    if (str_contains(search_text(implode(' ', $values)), search_text($q))) return true;
    $digits = preg_replace('/\D/', '', $q);
    $phone = valid_whatsapp($q);
    return strlen($digits) >= 6 && str_contains(preg_replace('/\D/', '', ($source['whatsapp'] ?? $record['whatsapp'] ?? '')), $phone ?: $digits);
}

function report_count(string $id): int
{
    if (!isset($GLOBALS['perro_report_counts'])) {
        $counts = [];
        foreach (read_dataset('reports') as $report) if ($report['status'] === 'open') $counts[$report['dog_id']] = ($counts[$report['dog_id']] ?? 0) + 1;
        $GLOBALS['perro_report_counts'] = $counts;
    }
    return $GLOBALS['perro_report_counts'][$id] ?? 0;
}

function moderation_hints(array $record): array
{
    $hints = [];
    $story = search_text(implode(' ', array_map(static fn($k): string => $record[$k] ?? '', ['description', 'reason', 'adoption_requirements'])));
    if (preg_match('/\b(precio|venta|sena|cruza|vendo|gs)[.\s]|\d[.\d]*\s*(guaranies|usd|dolares)/u', $story)) $hints[] = 'Menciona precio, venta o seña: verificá el contexto.';
    if (preg_match('/(?:\+?595|09)\s*[\d ()-]{6,}/', $story)) $hints[] = 'Posible teléfono en el texto público.';
    if (!isset($GLOBALS['perro_hint_index'])) {
        $phones = $photos = [];
        foreach (read_dataset('submissions') as $submission) {
            $number = valid_whatsapp($submission['whatsapp']);
            if ($number !== '') $phones[$number] = ($phones[$number] ?? 0) + 1;
            foreach ($submission['photos'] as $photo) {
                $file = PERRO_UPLOADS . '/' . basename($submission['id']) . '/' . $photo;
                $hash = is_file($file) ? hash_file('sha256', $file) : false;
                if ($hash) $photos[$hash][$submission['id']] = true;
            }
        }
        $GLOBALS['perro_hint_index'] = [$phones, $photos];
    }
    [$phones, $photos] = $GLOBALS['perro_hint_index'];
    $number = valid_whatsapp($record['whatsapp']);
    if (($phones[$number] ?? 0) >= 3) $hints[] = 'Mismo WhatsApp en ' . $phones[$number] . ' avisos.';
    foreach ($record['photos'] as $photo) {
        $file = PERRO_UPLOADS . '/' . basename($record['id']) . '/' . $photo;
        $hash = is_file($file) ? hash_file('sha256', $file) : false;
        if ($hash && count($photos[$hash] ?? []) > 1) { $hints[] = 'Foto repetida en otro envío.'; break; }
    }
    return $hints;
}

function admin_pagination(int $count, int $page): void
{
    $pages = max(1, (int) ceil($count / 25));
    if ($pages === 1) return;
    echo '<nav class="pagination" aria-label="Páginas del panel">';
    $query = [];
    foreach (['section', 'queue', 'q', 'dog_status'] as $key) if (query_value($key) !== '') $query[$key] = query_value($key);
    foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $i) {
        echo '<a class="button button-small button-secondary" href="/admin?' . h(http_build_query($query + ['page'=>$i])) . '"' . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    echo '</nav>';
}

function render_nearby(array $dog = []): void
{
    $nearby = array_values(array_filter(public_dogs('adoption'), static fn(array $r): bool => ($dog['id'] ?? '') !== $r['id'] && (empty($dog['department']) || search_text($r['department']) === search_text($dog['department']))));
    if (!$nearby) return;
    echo '<section class="section"><div class="shell"><h2>Otros perros cerca</h2><div class="dog-grid">';
    foreach (array_slice($nearby, 0, 3) as $r) dog_card($r);
    echo '</div></div></section>';
}
