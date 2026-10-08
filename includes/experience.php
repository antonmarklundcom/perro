<?php

declare(strict_types=1);

function query_value(string $key, int $limit = 100): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? substr(trim($value), 0, $limit) : '';
}

function render_public_schema(array $meta): void
{
    $path = request_path();
    if ($path === 'admin' || $path === 'activar-admin' || str_starts_with($path, 'admin/') || $meta['robots'] !== 'index,follow') return;
    $schema = ['@context'=>'https://schema.org', '@type'=>'WebSite', 'name'=>'Perro', 'url'=>app_url(), 'inLanguage'=>'es-PY'];
    if (preg_match('#^perro/([a-z0-9-]+)$#', $path, $matches) && ($dog = public_dog_by_slug($matches[1]))) {
        $adoption = ($dog['listing_type'] ?? 'adoption') === 'adoption';
        $schema = ['@context'=>'https://schema.org', '@type'=>'BreadcrumbList', 'itemListElement'=>[
            ['@type'=>'ListItem', 'position'=>1, 'name'=>'Inicio', 'item'=>app_url()],
            ['@type'=>'ListItem', 'position'=>2, 'name'=>$adoption ? 'Adopción' : 'Perdidos y encontrados', 'item'=>app_url($adoption ? 'perros' : 'perros-perdidos-paraguay')],
            ['@type'=>'ListItem', 'position'=>3, 'name'=>$dog['name'], 'item'=>$meta['canonical']],
        ]];
    } elseif ($path !== '') return;
    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

function location_suggestions(): void
{
    // Suggestions come from real public listings; arbitrary Paraguay locations remain valid.
    foreach (['city'=>'ciudades', 'department'=>'departamentos'] as $key => $id) {
        $values = array_unique(array_filter(array_column(public_dogs(), $key)));
        natcasesort($values);
        echo '<datalist id="' . $id . '">';
        foreach ($values as $value) echo '<option value="' . h($value) . '"></option>';
        echo '</datalist>';
    }
}

function discovery_url(string $path, array $filters, array $changes = []): string
{
    $values = array_filter(array_replace($filters, $changes), static fn($v): bool => $v !== '' && $v !== null);
    return '/' . $path . ($values ? '?' . http_build_query($values) : '');
}

function render_discovery(string $path): void
{
    $lost = $path === 'perros-perdidos-paraguay';
    $filters = [];
    foreach (['q', 'city', 'department', 'age', 'size', 'sex', 'availability', 'type', 'sort'] as $key) $filters[$key] = query_value($key);
    foreach (['age'=>'age_group', 'size'=>'size', 'sex'=>'sex', 'type'=>'listing_type'] as $key => $option) {
        if ($filters[$key] !== '' && !isset(listing_options()[$option][$filters[$key]])) $filters[$key] = '';
    }
    if (!in_array($filters['sort'], ['newest', 'oldest', 'name'], true)) $filters['sort'] = 'newest';
    if (!in_array($filters['availability'], ['available', 'reserved'], true)) $filters['availability'] = '';
    if (!$lost) $filters['type'] = '';
    if ($lost) $filters['availability'] = '';
    if ($lost && $filters['type'] === 'adoption') $filters['type'] = '';
    if ($path === 'cachorros-en-adopcion') $filters['age'] = 'Cachorro';
    $dogs = array_values(array_filter(public_dogs(), static function (array $dog) use ($filters, $lost, $path): bool {
        $type = $dog['listing_type'] ?? 'adoption';
        if ($lost ? !in_array($type, ['lost', 'found'], true) : $type !== 'adoption') return false;
        if ($path === 'perros-de-raza-en-adopcion' && (!empty($dog['mixed_breed']) || empty($dog['breed_label']))) return false;
        foreach (['age'=>'age_group', 'size'=>'size', 'sex'=>'sex', 'availability'=>'adoption_status', 'type'=>'listing_type'] as $key => $field) {
            if ($filters[$key] !== '' && strcasecmp((string) ($dog[$field] ?? ''), $filters[$key]) !== 0) return false;
        }
        if ($filters['city'] !== '' && !str_contains(search_text($dog['city']), search_text($filters['city']))) return false;
        if ($filters['department'] !== '' && !str_contains(search_text($dog['department']), search_text($filters['department']))) return false;
        $haystack = search_text(implode(' ', [$dog['name'], $dog['city'], $dog['department'], $dog['breed_label'], $dog['description'], $dog['slug'] ?? '', $dog['age_group'], $dog['sex'], $dog['size'], $dog['last_location'], $dog['mixed_breed'] ?? false ? 'mestizo' : '', listing_share_code($dog)]));
        foreach (preg_split('/\s+/', search_text($filters['q']), -1, PREG_SPLIT_NO_EMPTY) as $word) if (!str_contains($haystack, $word)) return false;
        return true;
    }));
    usort($dogs, static function (array $a, array $b) use ($filters, $lost): int {
        if (!$lost && ($a['adoption_status'] === 'reserved') !== ($b['adoption_status'] === 'reserved')) return ($a['adoption_status'] === 'reserved') ? 1 : -1;
        $order = $filters['sort'] === 'name' ? strcmp(search_text($a['name']), search_text($b['name'])) : (strtotime($a['published_at']) <=> strtotime($b['published_at']));
        return ($filters['sort'] === 'newest' ? -$order : $order) ?: strcmp($a['id'], $b['id']);
    });
    $count = count($dogs); $pages = max(1, (int) ceil($count / 12));
    $page = min($pages, max(1, (int) query_value('page', 8)));
    $visible = array_slice($dogs, ($page - 1) * 12, 12);
    $active = array_filter($filters, static fn($v, $k): bool => $v !== '' && !($k === 'sort' && $v === 'newest') && !($k === 'age' && $path === 'cachorros-en-adopcion'), ARRAY_FILTER_USE_BOTH);
    $heading = match ($path) { 'perros-perdidos-paraguay'=>'Perros perdidos y encontrados', 'cachorros-en-adopcion'=>'Cachorros en adopción', 'perros-de-raza-en-adopcion'=>'Perros de raza en adopción', default=>'Perros para adoptar' };
    $meta = page_meta(($heading . ' en Paraguay' . ($page > 1 ? ' · Página ' . $page : '') . ' | Perro'), $lost ? 'Buscá avisos de perros perdidos y encontrados por ciudad y departamento en Paraguay.' : match ($path) { 'cachorros-en-adopcion'=>'Buscá cachorros en adopción gratuita y conocé los cuidados que necesitan en Paraguay.', 'perros-de-raza-en-adopcion'=>'Buscá perros con raza declarada en adopción gratuita. La raza puede ser aproximada.', default=>'Encontrá perros en adopción gratuita en Paraguay por ciudad, edad, sexo y tamaño.' }, $path . ($page > 1 ? '?page=' . $page : ''));
    if ($active) $meta['robots'] = 'noindex,follow';
    render_header($meta);
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow"><?= $lost ? 'Ayudemos a que vuelvan' : 'Adopción responsable' ?></span><h1><?= h($heading) ?></h1><p><?= $lost ? 'Buscá por zona o tipo de aviso. Un perro encontrado no se ofrece en adopción hasta aclarar su situación.' : 'Encontrá un perro compatible con tu hogar. Todos los avisos pasan por revisión; no se permiten ventas.' ?></p><?php if ($lost): ?><div class="button-row"><a class="button" href="/dar-perro-en-adopcion?type=lost">Perdí un perro</a><a class="button button-secondary" href="/dar-perro-en-adopcion?type=found">Encontré un perro</a></div><?php endif; ?></div></section>
    <section class="section listings-layout"><div class="shell">
    <details class="filter-disclosure" open><summary>Buscar y filtrar<?= $active ? ' · ' . count($active) . ' activo(s)' : '' ?></summary>
    <form class="filters discovery-filters" method="get" action="/<?= h($path) ?>">
    <label>Buscar<input name="q" value="<?= h($filters['q']) ?>" placeholder="Nombre, apariencia o historia" maxlength="100"></label>
    <label>Ciudad<input name="city" list="ciudades" value="<?= h($filters['city']) ?>" placeholder="Ej. Luque" maxlength="100"></label>
    <label>Departamento<input name="department" list="departamentos" value="<?= h($filters['department']) ?>" placeholder="Ej. Central" maxlength="100"></label>
    <?php foreach (['age'=>['Edad', 'age_group'], 'size'=>['Tamaño', 'size'], 'sex'=>['Sexo', 'sex']] as $key => [$label, $option]): ?><label><?= $label ?><select name="<?= $key ?>"<?= $key === 'age' && $path === 'cachorros-en-adopcion' ? ' disabled' : '' ?>><option value="">Todos</option><?php select_options(listing_options()[$option], $filters[$key]); ?></select></label><?php endforeach; ?>
    <?php if ($lost): ?><label>Tipo de aviso<select name="type"><option value="">Perdidos y encontrados</option><?php select_options(['lost'=>'Perdidos', 'found'=>'Encontrados'], $filters['type']); ?></select></label><?php else: ?><label>Disponibilidad<select name="availability"><option value="">Todos los activos</option><?php select_options(['available'=>'Disponibles', 'reserved'=>'Reservados'], $filters['availability']); ?></select></label><?php endif; ?>
    <label>Ordenar<select name="sort"><?php select_options(['newest'=>'Más recientes', 'oldest'=>'Más antiguos', 'name'=>'Nombre A–Z'], $filters['sort']); ?></select></label>
    <div class="button-row"><button class="button" type="submit">Buscar perros</button><a class="text-link" href="/<?= h($path) ?>">Limpiar</a></div>
    </form></details><?php location_suggestions(); ?>
    <?php if ($active): ?><nav class="filter-chips" aria-label="Quitar filtros"><?php foreach ($active as $key => $value): ?><a href="<?= h(discovery_url($path, $filters, [$key=>''])) ?>" aria-label="Quitar filtro <?= h((['q'=>'Búsqueda', 'city'=>'Ciudad', 'department'=>'Departamento', 'age'=>'Edad', 'size'=>'Tamaño', 'sex'=>'Sexo', 'availability'=>'Disponibilidad', 'type'=>'Tipo', 'sort'=>'Orden'][$key] ?? $key) . ': ' . $value) ?>"><?= h(listing_options()['listing_type'][$value] ?? ['available'=>'Disponible', 'reserved'=>'Reservado', 'oldest'=>'Más antiguos', 'name'=>'Nombre A–Z'][$value] ?? $value) ?> ×</a><?php endforeach; ?></nav><?php endif; ?>
    <div class="results-head"><p role="status"><strong><?= $count ?></strong> <?= $count === 1 ? 'ficha encontrada' : 'fichas encontradas' ?><?= $count ? ' · Mostrando ' . (($page - 1) * 12 + 1) . '–' . min($count, $page * 12) : '' ?></p><a class="text-link" href="/dar-perro-en-adopcion">Publicá un aviso</a></div>
    <?php if ($visible): ?><div class="dog-grid"><?php foreach ($visible as $dog) dog_card($dog); ?></div><?php else: ?><div class="empty-state"><h2><?= $active ? 'No encontramos fichas con esos filtros' : 'Todavía no hay avisos activos' ?></h2><p><?= $active ? 'Probá otra zona o quitá algún filtro. Los avisos que terminaron o vencieron dejan de aparecer.' : '¿Conocés un perro que necesita ayuda? Enviá un aviso real para que el equipo lo revise.' ?></p><div class="button-row"><a class="button" href="/<?= h($path) ?>">Ver todos</a><a class="button button-secondary" href="/dar-perro-en-adopcion">Publicar un aviso</a></div></div><?php endif; ?>
    <?php if ($pages > 1): ?><nav class="pagination" aria-label="Páginas de resultados"><?php if ($page > 1): ?><a class="button button-secondary" href="<?= h(discovery_url($path, $filters, ['page'=>$page - 1])) ?>">← Anterior</a><?php endif; ?><span>Página <?= $page ?> de <?= $pages ?></span><?php if ($page < $pages): ?><a class="button button-secondary" href="<?= h(discovery_url($path, $filters, ['page'=>$page + 1])) ?>">Siguiente →</a><?php endif; ?></nav><?php endif; ?>
    </div></section><?php if ($path === 'perros-de-raza-en-adopcion'): ?><section class="section section-note"><div class="shell narrow"><h2>La raza puede ser aproximada</h2><p>Perro no certifica pedigrí ni pureza de raza. Priorizá el carácter, los cuidados y la compatibilidad real. La adopción es gratuita; los cuidados diarios y veterinarios tienen costos.</p></div></section><?php endif; ?>
    <?php if (!$visible) render_nearby(['department'=>$filters['department']]); render_footer();
}

// Resolve private submission data only after the administrator has authenticated.
// Legacy records use only contact fields that actually exist on that record.
function admin_dog_contact_record(array $dog): array
{
    require_admin();
    $source = !empty($dog['source_submission_id']) ? find_record('submissions', (string) $dog['source_submission_id']) : null;
    if ($source) return $source;
    return [
        'name' => $dog['name'] ?? '',
        'submitter_name' => $dog['submitter_name'] ?? $dog['contact_name'] ?? '',
        'email' => $dog['email'] ?? $dog['contact_email'] ?? '',
        'whatsapp' => $dog['whatsapp'] ?? $dog['contact_whatsapp'] ?? '',
        'reference' => $dog['reference'] ?? '',
    ];
}

function admin_private_contact_details(array $record): void
{
    require_admin();
    $number = valid_whatsapp((string) ($record['whatsapp'] ?? ''));
    ?><div class="admin-private"><p><strong>Contacto privado para administración</strong><br>Responsable: <?= h($record['submitter_name'] ?? '') ?><br>Correo privado: <?= h($record['email'] ?? '') ?><br>WhatsApp privado de contacto: <?= h($record['whatsapp'] ?? '') ?><br>Referencia privada del envío: <?= h($record['reference'] ?? '') ?></p><?php if ($number !== ''): ?><a class="text-link" href="tel:+<?= h($number) ?>">Llamar al responsable</a><?php endif; ?></div><?php
}

function private_contact_url(array $record, string $purpose = 'review'): string
{
    $number = valid_whatsapp((string) ($record['whatsapp'] ?? ''));
    if ($number === '') return '';
    $prefix = 'Hola' . (!empty($record['submitter_name']) ? ' ' . $record['submitter_name'] : '') . ', somos del equipo de Perro.com.py. Sobre ' . ($record['name'] ?? '') . (!empty($record['reference']) ? ' (referencia ' . $record['reference'] . ')' : '') . ': ';
    $dog = !empty($record['published_dog_id']) ? find_record('dogs', $record['published_dog_id']) : null;
    $public = $dog && public_dog_by_slug($dog['slug']);
    $message = match ($purpose) {
        'photos'=>'¿Podés compartir fotos claras del perro que tengas permiso de publicar, sin documentos ni direcciones privadas?',
        'confirm'=>'¿El aviso sigue vigente? Contanos si el perro ya fue adoptado o reencontrado, o si hay datos para corregir.',
        'published'=>$public ? 'Tu aviso ya está publicado: ' . app_url('perro/' . $dog['slug']) . '. Podés copiar el texto y descargar imágenes para compartir desde ' . app_url('perro/' . $dog['slug']) . '#compartir. Avisanos si cambia la situación.' : 'Queremos consultar cómo sigue la situación del perro.',
        default=>'Recibimos tu ficha y queremos confirmar algunos datos antes de continuar. La publicación y la adopción son gratuitas.',
    };
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($prefix . $message . ($dog ? "\nFicha " . listing_share_code($dog) . ' · ' . app_url('perro/' . $dog['slug']) : ''));
}

function admin_whatsapp_links(array $submission): void
{
    $published = !empty($submission['published_dog_id']) ? find_record('dogs', $submission['published_dog_id']) : null;
    $canNotify = $published && public_dog_by_slug($published['slug']);
    ?><details class="whatsapp-tools"><summary>Mensajes de WhatsApp</summary><div class="admin-message-links"><?php foreach (['review'=>'Consultar datos', 'photos'=>'Pedir fotos', 'confirm'=>'Confirmar vigencia', 'published'=>'Avisar publicación'] as $purpose => $label): if ($purpose === 'published' && !$canNotify) continue; $url = private_contact_url($submission, $purpose); if (!$url) continue; ?><a class="button button-small button-whatsapp" href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= $label ?></a><?php endforeach; ?></div><small>Se abre un borrador. Revisalo antes de enviarlo.</small></details><?php
}

function admin_return_url(): string
{
    $section = text('section', 30);
    $section = in_array($section, ['solicitudes', 'publicadas', 'reportes', 'confirmar', 'clave', 'archivo'], true) ? $section : 'solicitudes';
    $query = ['section'=>$section];
    foreach (['queue', 'q', 'dog_status', 'page'] as $key) if (text('return_' . $key, 100) !== '') $query[$key] = text('return_' . $key, 100);
    return 'admin?' . http_build_query($query) . '#' . $section;
}

function admin_action_fields(string $dataset, string $id, string $action, string $section): void
{
    echo csrf_field();
    foreach (['dataset'=>$dataset, 'id'=>$id, 'action'=>$action, 'section'=>$section, 'return_queue'=>query_value('queue'), 'return_q'=>query_value('q'), 'return_dog_status'=>query_value('dog_status'), 'return_page'=>query_value('page')] as $key => $value) if ($key !== 'action' || $value !== '') echo '<input type="hidden" name="' . $key . '" value="' . h($value) . '">';
}
