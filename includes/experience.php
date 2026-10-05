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
        if ($filters['department'] !== '' && search_text($dog['department']) !== search_text($filters['department'])) return false;
        $haystack = search_text(implode(' ', [$dog['name'], $dog['city'], $dog['department'], $dog['breed_label'], $dog['description']]));
        foreach (preg_split('/\s+/', search_text($filters['q']), -1, PREG_SPLIT_NO_EMPTY) as $word) if (!str_contains($haystack, $word)) return false;
        return true;
    }));
    usort($dogs, static function (array $a, array $b) use ($filters): int {
        $order = $filters['sort'] === 'name' ? strcmp(search_text($a['name']), search_text($b['name'])) : (strtotime($a['published_at']) <=> strtotime($b['published_at']));
        return ($filters['sort'] === 'newest' ? -$order : $order) ?: strcmp($a['id'], $b['id']);
    });
    $count = count($dogs); $pages = max(1, (int) ceil($count / 12));
    $page = min($pages, max(1, (int) query_value('page', 8)));
    $visible = array_slice($dogs, ($page - 1) * 12, 12);
    $active = array_filter($filters, static fn($v, $k): bool => $v !== '' && !($k === 'sort' && $v === 'newest') && !($k === 'age' && $path === 'cachorros-en-adopcion'), ARRAY_FILTER_USE_BOTH);
    $heading = match ($path) { 'perros-perdidos-paraguay'=>'Perros perdidos y encontrados', 'cachorros-en-adopcion'=>'Cachorros en adopción', 'perros-de-raza-en-adopcion'=>'Perros de raza en adopción', default=>'Perros para adoptar' };
    $meta = page_meta($heading . ' en Paraguay | Perro', $lost ? 'Buscá avisos de perros perdidos y encontrados por ciudad y departamento en Paraguay.' : 'Encontrá perros en adopción gratuita en Paraguay por ciudad, edad, sexo y tamaño.', $path . ($page > 1 ? '?page=' . $page : ''));
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
    <?php if ($active): ?><nav class="filter-chips" aria-label="Quitar filtros"><?php foreach ($active as $key => $value): ?><a href="<?= h(discovery_url($path, $filters, [$key=>''])) ?>" aria-label="Quitar filtro <?= h($key . ': ' . $value) ?>"><?= h(listing_options()['listing_type'][$value] ?? ['available'=>'Disponible', 'reserved'=>'Reservado', 'oldest'=>'Más antiguos', 'name'=>'Nombre A–Z'][$value] ?? $value) ?> ×</a><?php endforeach; ?></nav><?php endif; ?>
    <div class="results-head"><p role="status"><strong><?= $count ?></strong> <?= $count === 1 ? 'ficha encontrada' : 'fichas encontradas' ?><?= $count ? ' · Mostrando ' . (($page - 1) * 12 + 1) . '–' . min($count, $page * 12) : '' ?></p><a class="text-link" href="/dar-perro-en-adopcion">Publicá un aviso</a></div>
    <?php if ($visible): ?><div class="dog-grid"><?php foreach ($visible as $dog) dog_card($dog); ?></div><?php else: ?><div class="empty-state"><h2><?= $active ? 'No encontramos fichas con esos filtros' : 'Todavía no hay avisos activos' ?></h2><p><?= $active ? 'Probá otra zona o quitá algún filtro. Los avisos que terminaron o vencieron dejan de aparecer.' : '¿Conocés un perro que necesita ayuda? Enviá un aviso real para que el equipo lo revise.' ?></p><div class="button-row"><a class="button" href="/<?= h($path) ?>">Ver todos</a><a class="button button-secondary" href="/dar-perro-en-adopcion">Publicar un aviso</a></div></div><?php endif; ?>
    <?php if ($pages > 1): ?><nav class="pagination" aria-label="Páginas de resultados"><?php if ($page > 1): ?><a class="button button-secondary" href="<?= h(discovery_url($path, $filters, ['page'=>$page - 1])) ?>">← Anterior</a><?php endif; ?><span>Página <?= $page ?> de <?= $pages ?></span><?php if ($page < $pages): ?><a class="button button-secondary" href="<?= h(discovery_url($path, $filters, ['page'=>$page + 1])) ?>">Siguiente →</a><?php endif; ?></nav><?php endif; ?>
    </div></section><?php if ($path === 'perros-de-raza-en-adopcion'): ?><section class="section section-note"><div class="shell narrow"><h2>La raza puede ser aproximada</h2><p>Perro no certifica pedigrí ni pureza de raza. Priorizá el carácter, los cuidados y la compatibilidad real. La adopción es gratuita; los cuidados diarios y veterinarios tienen costos.</p></div></section><?php endif; ?>
    <?php render_footer();
}

function private_contact_url(array $record, string $purpose = 'review'): string
{
    $number = valid_whatsapp((string) ($record['whatsapp'] ?? ''));
    if ($number === '') return '';
    $prefix = 'Hola ' . ($record['submitter_name'] ?? '') . ', somos del equipo de Perro.com.py. Sobre ' . $record['name'] . ' (referencia ' . ($record['reference'] ?? '') . '): ';
    $dog = !empty($record['published_dog_id']) ? find_record('dogs', $record['published_dog_id']) : null;
    $public = $dog && public_dog_by_slug($dog['slug']);
    $message = match ($purpose) {
        'photos'=>'¿Podés compartir fotos claras del perro que tengas permiso de publicar, sin documentos ni direcciones privadas?',
        'confirm'=>'¿El aviso sigue vigente? Contanos si el perro ya fue adoptado o reencontrado, o si hay datos para corregir.',
        'published'=>$public ? 'Tu aviso ya está publicado: ' . app_url('perro/' . $dog['slug']) . '. Avisanos si cambia la situación.' : 'Queremos consultar cómo sigue la situación del perro.',
        default=>'Recibimos tu ficha y queremos confirmar algunos datos antes de continuar. La publicación y la adopción son gratuitas.',
    };
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($prefix . $message);
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
    $section = in_array($section, ['solicitudes', 'publicadas', 'reportes', 'clave'], true) ? $section : 'solicitudes';
    $query = ['section'=>$section];
    foreach (['queue', 'q', 'dog_status'] as $key) if (text('return_' . $key, 100) !== '') $query[$key] = text('return_' . $key, 100);
    return 'admin?' . http_build_query($query) . '#' . $section;
}

function admin_action_fields(string $dataset, string $id, string $action, string $section): void
{
    echo csrf_field();
    foreach (['dataset'=>$dataset, 'id'=>$id, 'action'=>$action, 'section'=>$section, 'return_queue'=>query_value('queue'), 'return_q'=>query_value('q'), 'return_dog_status'=>query_value('dog_status')] as $key => $value) if ($key !== 'action' || $value !== '') echo '<input type="hidden" name="' . $key . '" value="' . h($value) . '">';
}

function render_admin_panel(): void
{
    render_header(page_meta('Administración | Perro', 'Panel privado de moderación.', 'admin', false));
    if (!is_admin()) {
        ?><section class="section"><div class="shell login-card"><span class="eyebrow">Acceso privado</span><h1>Administración</h1><p>Ingresá para revisar avisos, publicar y hablar con sus responsables.</p><form method="post" action="/admin/login"><?= csrf_field() ?><label>Correo o usuario principal<input name="username" required maxlength="180" autocomplete="username" autocapitalize="none" spellcheck="false"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-full" type="submit">Ingresar</button></form></div></section><?php render_footer(); return;
    }
    $submissions = read_dataset('submissions'); $dogs = read_dataset('dogs'); $reports = read_dataset('reports');
    usort($submissions, static fn(array $a, array $b): int => strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? '')));
    usort($dogs, static fn(array $a, array $b): int => strtotime((string) ($b['updated_at'] ?? '')) <=> strtotime((string) ($a['updated_at'] ?? '')));
    usort($reports, static fn(array $a, array $b): int => strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? '')));
    $section = query_value('section'); if (!in_array($section, ['solicitudes', 'publicadas', 'reportes', 'clave'], true)) $section = 'solicitudes';
    $queue = query_value('queue'); if (!in_array($queue, ['all', 'pending', 'approved', 'rejected'], true)) $queue = 'pending';
    $pending = count(array_filter($submissions, static fn(array $s): bool => $s['status'] === 'pending'));
    $open = count(array_filter($reports, static fn(array $r): bool => $r['status'] === 'open'));
    $q = query_value('q'); $dogStatus = query_value('dog_status');
    $matches = static fn(array $r): bool => $q === '' || str_contains(search_text(implode(' ', [$r['name'] ?? $r['dog_name'] ?? '', $r['city'] ?? '', $r['reference'] ?? '', $r['submitter_name'] ?? ''])), search_text($q));
    $visibleSubmissions = array_values(array_filter($submissions, static fn(array $r): bool => ($queue === 'all' || $r['status'] === $queue) && $matches($r)));
    $visibleDogs = array_values(array_filter($dogs, static function (array $record) use ($matches, $dogStatus): bool {
        if (!$matches($record)) return false;
        if ($dogStatus === '') return true;
        if ($dogStatus === 'active') return public_dog_by_slug($record['slug']) !== null;
        if ($dogStatus === 'expired') return $record['status'] === 'expired' || ($record['status'] === 'published' && strtotime($record['expires_at']) < time() && !in_array($record['adoption_status'], ['adopted', 'reunited'], true));
        return $record['status'] === $dogStatus || $record['adoption_status'] === $dogStatus;
    }));
    ?><section class="admin-hero"><div class="shell admin-title"><div><span class="eyebrow">Panel privado</span><h1>Moderación de Perro</h1><p>Revisá, publicá y acompañá cada aviso.</p></div><details class="admin-menu"><summary>Mi cuenta y herramientas</summary><div class="admin-actions"><?php if (current_admin_account_id() === 'admin'): ?><a class="button button-small button-secondary" href="/admin/accounts">Cuentas del equipo</a><?php endif; ?><a class="button button-small button-secondary" href="/admin/export.csv">Exportar CSV</a><form method="post" action="/admin/logout"><?= csrf_field() ?><button class="button button-small" type="submit">Cerrar sesión</button></form></div></details></div></section>
    <section class="section admin-section"><div class="shell"><div class="stats"><article><span>Pendientes</span><strong><?= $pending ?></strong></article><article><span>Publicados</span><strong><?= count(public_dogs()) ?></strong></article><article><span>Reportes abiertos</span><strong><?= $open ?></strong></article></div>
    <nav class="admin-tabs" aria-label="Secciones de administración"><?php foreach (['solicitudes'=>'Revisar (' . $pending . ')', 'publicadas'=>'Avisos', 'reportes'=>'Reportes (' . $open . ')', 'clave'=>'Mi clave'] as $key => $label): ?><a href="/admin?section=<?= $key ?>#<?= $key ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a><?php endforeach; ?></nav>
    <div class="admin-block" id="solicitudes"<?= $section !== 'solicitudes' ? ' hidden' : '' ?>><h2>Solicitudes para revisar</h2>
    <form class="queue-filter admin-filter" action="/admin#solicitudes" method="get"><input type="hidden" name="section" value="solicitudes"><label>Estado<select name="queue"><?php select_options(['pending'=>'Pendientes', 'approved'=>'Aprobadas', 'rejected'=>'Rechazadas', 'all'=>'Todas'], $queue); ?></select></label><label>Buscar<input name="q" value="<?= h($q) ?>" placeholder="Perro, ciudad o referencia" maxlength="100"></label><button class="button button-small" type="submit">Filtrar solicitudes</button></form>
    <p class="date-note"><?= count($visibleSubmissions) ?> solicitud(es). Revisá la ficha completa antes de publicar.</p>
    <?php if ($visibleSubmissions): ?><div class="admin-list"><?php foreach ($visibleSubmissions as $submission): ?><article class="admin-card">
    <div><div class="admin-card-heading"><?php if (!empty($submission['photos'][0])): ?><img class="admin-thumb" src="/admin/media/<?= h($submission['id']) ?>/<?= h($submission['photos'][0]) ?>" alt="Foto privada de <?= h($submission['name']) ?>" loading="lazy"><?php endif; ?><div><span class="status-pill status-<?= h($submission['status']) ?>"><?= h(record_status_label($submission['status'])) ?></span><h3><?= h($submission['name']) ?></h3><p><?= h($submission['city']) ?> · <?= h(listing_options()['listing_type'][$submission['listing_type']] ?? 'Aviso') ?></p><small>Ref. <?= h($submission['reference']) ?> · <?= h(date('d/m/Y', strtotime($submission['created_at']))) ?></small></div></div>
    <details><summary>Contacto privado y permisos</summary><p class="admin-private"><strong>Contacto privado:</strong> <?= h($submission['submitter_name']) ?> · <?= h($submission['email']) ?> · <?= h($submission['whatsapp']) ?></p><p>Permisos: nombre <?= !empty($submission['public_name']) ? h($submission['public_display_name'] ?? '') : 'privado' ?> · WhatsApp <?= !empty($submission['public_whatsapp']) ? 'autorizado' : 'privado' ?>.</p><small>Reglas aceptadas: <?= h($submission['consents']['terms_version'] ?? 'Sin versión registrada') ?></small></details>
    <details><summary>Historia y datos del perro</summary><p><?= nl2br(h($submission['description'])) ?></p><p><?= h($submission['age_group'] . ' · ' . $submission['sex'] . ' · ' . $submission['size']) ?> · <?= count($submission['photos'] ?? []) ?> foto(s)</p><?php if (!empty($submission['internal_note'])): ?><p><strong>Nota interna:</strong> <?= h($submission['internal_note']) ?></p><?php endif; ?></details></div>
    <div class="admin-card-actions"><a class="button" href="/admin/edit?dataset=submissions&amp;id=<?= h($submission['id']) ?>">Revisar ficha y fotos</a><?php admin_whatsapp_links($submission); ?>
    <?php if ($submission['status'] === 'pending'): ?><details class="moderation-tools"><summary>Publicar o rechazar</summary><form method="post" action="/admin/action"><?php admin_action_fields('submissions', $submission['id'], 'approve', 'solicitudes'); ?><label class="check"><input type="checkbox" name="review_confirm" value="1" required> Revisé la ficha, fotos, permisos y adopción gratuita.</label><button class="button button-full" type="submit">Aprobar y publicar</button></form><details class="reject-tools"><summary>Rechazar solicitud</summary><form method="post" action="/admin/action" class="reject-form"><?php admin_action_fields('submissions', $submission['id'], 'reject', 'solicitudes'); ?><label>Motivo interno<input name="note" maxlength="1000" placeholder="Por qué no corresponde publicar"></label><button class="button button-danger" type="submit">Confirmar rechazo</button></form></details></details><?php endif; ?>
    </div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state small"><h3><?= $queue === 'pending' && $q === '' ? 'No quedan solicitudes pendientes' : 'No hay solicitudes con este filtro' ?></h3><p>Los nuevos envíos aparecerán acá. Podés revisar otros estados o los avisos publicados.</p><a class="text-link" href="/admin?queue=all#solicitudes">Ver todas las solicitudes</a></div><?php endif; ?></div>
    <div class="admin-block" id="publicadas"<?= $section !== 'publicadas' ? ' hidden' : '' ?>><h2>Fichas de perros</h2><form class="admin-filter" action="/admin#publicadas" method="get"><input type="hidden" name="section" value="publicadas"><label>Buscar<input name="q" value="<?= h($q) ?>" placeholder="Perro o ciudad"></label><label>Estado<select name="dog_status"><option value="">Todos</option><?php select_options(['active'=>'Activos', 'reserved'=>'Reservados', 'adopted'=>'Adoptados', 'reunited'=>'Reencontrados', 'expired'=>'Vencidos', 'removed'=>'Retirados'], $dogStatus); ?></select></label><button class="button button-small" type="submit">Buscar avisos</button></form>
    <?php if ($visibleDogs): ?><div class="admin-list"><?php foreach ($visibleDogs as $dog): $source = !empty($dog['source_submission_id']) ? find_record('submissions', $dog['source_submission_id']) : null; $isPublic = public_dog_by_slug($dog['slug']) !== null; ?><article class="admin-card"><div><span class="status-pill"><?= h(record_status_label($dog['status'])) ?> · <?= h(record_status_label($dog['adoption_status'])) ?></span><h3><?= h($dog['name']) ?></h3><p><?= h($dog['city']) ?> · <?= $isPublic ? 'Visible públicamente' : 'Fuera de la búsqueda pública' ?><br>Vence el <?= h(date('d/m/Y', strtotime($dog['expires_at']))) ?></p><?php if ($isPublic): ?><a class="text-link" href="/perro/<?= h($dog['slug']) ?>" target="_blank" rel="noopener noreferrer">Ver ficha pública ↗</a><?php endif; ?><?php if ($source) admin_whatsapp_links($source); ?></div>
    <div class="admin-card-actions"><a class="button button-secondary" href="/admin/edit?dataset=<?= $source ? 'submissions' : 'dogs' ?>&amp;id=<?= h($source ? $source['id'] : $dog['id']) ?>">Editar ficha y privacidad</a><details class="moderation-tools"><summary>Cambiar estado del aviso</summary><form method="post" action="/admin/action"><?php admin_action_fields('dogs', $dog['id'], '', 'publicadas'); ?><label>Nuevo estado<select name="action" required><option value="">Elegí una acción</option><?php select_options(($dog['listing_type'] === 'adoption' ? ['reserved'=>'Reservado', 'adopted'=>'Adoptado'] : ['reunited'=>'Reencontrado']) + ['available'=>'Renovar / reabrir', 'expired'=>'Marcar vencido', 'unpublish'=>'Retirar aviso']); ?></select></label><label class="check"><input type="checkbox" name="owner_confirmed" value="1"> Confirmé con el responsable que sigue vigente (obligatorio para renovar).</label><button class="button button-full" type="submit">Guardar estado</button></form><small>Adoptado, reencontrado, vencido o retirado deja de aparecer públicamente.</small></details></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state small"><p>No hay avisos con estos filtros.</p></div><?php endif; ?></div>
    <div class="admin-block" id="reportes"<?= $section !== 'reportes' ? ' hidden' : '' ?>><h2>Reportes</h2><?php if ($reports): ?><div class="admin-list"><?php foreach ($reports as $report): ?><article class="admin-card"><div><span class="status-pill"><?= h(record_status_label($report['status'])) ?></span><h3><?= h($report['dog_name']) ?></h3><p><?= h($report['reason']) ?></p><small><?= h($report['contact']) ?></small></div><?php if ($report['status'] === 'open'): ?><form method="post" action="/admin/action"><?php admin_action_fields('reports', $report['id'], 'resolve', 'reportes'); ?><button class="button button-small button-secondary" type="submit">Marcar revisado</button></form><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="empty-state small"><p>No hay reportes.</p></div><?php endif; ?></div>
    <div class="admin-block" id="clave"<?= $section !== 'clave' ? ' hidden' : '' ?>><h2>Cambiar contraseña</h2><form class="password-form" method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="action" value="change_password"><label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nueva contraseña<input type="password" name="new_password" required minlength="14" autocomplete="new-password"></label><label>Repetir nueva contraseña<input type="password" name="confirm_password" required minlength="14" autocomplete="new-password"></label><button class="button" type="submit">Guardar nueva contraseña</button></form></div>
    </div></section><?php render_footer();
}
