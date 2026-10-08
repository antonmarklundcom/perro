<?php

declare(strict_types=1);

function render_admin_panel(): void
{
    if (!is_admin()) {
        render_header(page_meta('Administración | Perro', 'Panel privado de moderación.', 'admin', false));
        ?><section class="section"><div class="shell login-card"><span class="eyebrow">Acceso privado</span><h1>Administración</h1><p>Ingresá para revisar avisos, publicar y hablar con sus responsables.</p><form method="post" action="/admin/login"><?= csrf_field() ?><label>Correo o usuario principal<input name="username" required maxlength="180" autocomplete="username" autocapitalize="none" spellcheck="false"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-full" type="submit">Ingresar</button></form></div></section><?php render_footer(); return;
    }
    $submissions = read_dataset('submissions'); $dogs = read_dataset('dogs'); $reports = read_dataset('reports');
    foreach (['submissions', 'dogs', 'reports'] as $name) usort($$name, static fn(array $a, array $b): int => strcmp($b['updated_at'] ?? $b['created_at'], $a['updated_at'] ?? $a['created_at']));
    $section = query_value('section');
    if (!in_array($section, ['solicitudes', 'publicadas', 'reportes', 'confirmar', 'clave', 'archivo'], true)) $section = 'solicitudes';
    $q = query_value('q'); $queue = query_value('queue'); $dogStatus = query_value('dog_status');
    if (!in_array($queue, ['pending', 'approved', 'rejected', 'all'], true)) $queue = $q !== '' ? 'all' : 'pending';
    $pending = count(array_filter($submissions, static fn(array $s): bool => $s['status'] === 'pending'));
    $open = count(array_filter($reports, static fn(array $r): bool => $r['status'] === 'open'));
    $confirm = array_values(array_filter($dogs, 'needs_confirmation'));
    usort($confirm, static fn(array $a, array $b): int => strcmp($a['expires_at'], $b['expires_at']));
    $records = match ($section) {
        'solicitudes'=>array_values(array_filter($submissions, static fn(array $r): bool => ($queue === 'all' || $r['status'] === $queue) && admin_search_matches($r, $q))),
        'publicadas'=>array_values(array_filter($dogs, static function (array $r) use ($q, $dogStatus): bool {
            if (!admin_search_matches($r, $q)) return false;
            if ($dogStatus === 'active') return public_dog_by_slug($r['slug']) !== null;
            if ($dogStatus === 'expired') return $r['status'] === 'expired' || ($r['status'] === 'published' && strtotime($r['expires_at']) <= time() && !in_array($r['adoption_status'], ['adopted', 'reunited'], true));
            return $dogStatus === '' || $r['status'] === $dogStatus || $r['adoption_status'] === $dogStatus;
        })),
        'confirmar'=>array_values(array_filter($confirm, static fn(array $r): bool => admin_search_matches($r, $q))),
        'reportes'=>array_values(array_filter($reports, static fn(array $r): bool => admin_search_matches($r, $q))),
        'archivo'=>archived_records($q),
        default=>[],
    };
    $count = count($records); $page = min(max(1, (int) ceil($count / 25)), max(1, (int) query_value('page', 8)));
    $records = array_slice($records, ($page - 1) * 25, 25);
    render_header(page_meta('Administración | Perro', 'Panel privado de moderación.', 'admin', false));
    ?><section class="admin-hero"><div class="shell admin-title"><div><span class="eyebrow">Panel privado</span><h1>Moderación de Perro</h1><p>Revisá, publicá y acompañá cada aviso.</p></div><details class="admin-menu"><summary>Mi cuenta y herramientas</summary><div class="admin-actions"><?php if (current_admin_account_id() === 'admin'): ?><a class="button button-small button-secondary" href="/admin/accounts">Cuentas del equipo</a><?php endif; ?><a class="button button-small button-secondary" href="/admin/export.csv">Exportar CSV</a><form method="post" action="/admin/logout"><?= csrf_field() ?><button class="button button-small" type="submit">Cerrar sesión</button></form></div></details></div></section>
    <section class="section admin-section"><div class="shell"><div class="stats"><article><span>Pendientes</span><strong><?= $pending ?></strong></article><article><span>Publicados</span><strong><?= count(public_dogs()) ?></strong></article><article><span>Reportes abiertos</span><strong><?= $open ?></strong></article></div>
    <?php if (empty($GLOBALS['config']['operator_name']) || !filter_var($GLOBALS['config']['privacy_email'], FILTER_VALIDATE_EMAIL)): ?><div class="notice">Falta configurar el responsable legal y el correo de privacidad en el servidor.</div><?php endif; ?>
    <nav class="admin-tabs" aria-label="Secciones de administración"><?php foreach (['solicitudes'=>'Revisar (' . $pending . ')', 'publicadas'=>'Avisos', 'reportes'=>'Reportes (' . $open . ')', 'confirmar'=>'Por confirmar (' . count($confirm) . ')', 'archivo'=>'Archivo', 'clave'=>'Mi clave'] as $key=>$label): ?><a href="/admin?section=<?= $key ?>#<?= $key ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a><?php endforeach; ?></nav>
    <div class="admin-block" id="<?= h($section) ?>"><h2><?= h(['solicitudes'=>'Solicitudes para revisar', 'publicadas'=>'Fichas de perros', 'reportes'=>'Reportes', 'confirmar'=>'Por confirmar', 'archivo'=>'Archivo', 'clave'=>'Cambiar contraseña'][$section]) ?></h2>
    <?php if ($section !== 'clave'): ?>
    <form class="admin-filter" action="/admin" method="get"><input type="hidden" name="section" value="<?= h($section) ?>"><label>Buscar<input name="q" value="<?= h($q) ?>" placeholder="Perro, ciudad, referencia, código, responsable o teléfono" maxlength="100"></label>
    <?php if ($section === 'solicitudes'): ?><label>Estado<select name="queue"><?php select_options(array_combine(['pending', 'approved', 'rejected', 'all'], array_map('record_status_label', ['pending', 'approved', 'rejected', 'all'])), $queue); ?></select></label><?php elseif ($section === 'publicadas'): ?><label>Estado<select name="dog_status"><option value="">Todos</option><?php select_options(array_combine(['active', 'reserved', 'adopted', 'reunited', 'expired', 'removed'], array_map('record_status_label', ['active', 'reserved', 'adopted', 'reunited', 'expired', 'removed'])), $dogStatus); ?></select></label><?php endif; ?><button class="button button-small" type="submit">Buscar</button></form>
    <p class="date-note"><?= $count ?> registro(s). Página <?= $page ?>.</p>
    <?php if ($section === 'confirmar'): ?><p>Revisá avisos próximos a vencer, vencidos en los últimos 30 días y reservas de más de 14 días. Abrí el borrador de WhatsApp; renová solo después de confirmar con el responsable.</p><?php endif; ?>
    <?php if (!$records): ?><div class="empty-state small"><p>No hay registros con estos filtros.</p><a href="/admin?queue=all">Ver todas las solicitudes</a></div><?php else: ?><div class="admin-list"><?php foreach ($records as $record) {
        if ($section === 'solicitudes') render_admin_submission($record);
        elseif (in_array($section, ['publicadas', 'confirmar'], true)) render_admin_dog($record, $section);
        elseif ($section === 'reportes') render_admin_report($record);
        else render_archived_record($record);
    } ?></div><?php endif; admin_pagination($count, $page); ?>
    <?php else: ?><form class="password-form" method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="action" value="change_password"><label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nueva contraseña<input type="password" name="new_password" required minlength="14" autocomplete="new-password"></label><label>Repetir nueva contraseña<input type="password" name="confirm_password" required minlength="14" autocomplete="new-password"></label><button class="button" type="submit">Guardar nueva contraseña</button></form><?php endif; ?>
    </div></div></section><?php render_footer();
}

function render_admin_submission(array $submission): void
{
    ?><article class="admin-card"><div><div class="admin-card-heading"><?php if (!empty($submission['photos'][0])): ?><img class="admin-thumb" src="/admin/media/<?= h($submission['id']) ?>/<?= h($submission['photos'][0]) ?>?w=480" alt="Foto privada de <?= h($submission['name']) ?>" loading="lazy"><?php endif; ?><div><span class="status-pill"><?= h(record_status_label($submission['status'])) ?></span><h3><?= h($submission['name']) ?></h3><p><?= h($submission['city']) ?> · <?= h(record_status_label($submission['listing_type'])) ?></p><small>Ref. <?= h($submission['reference']) ?> · <?= h(format_date($submission['created_at'])) ?></small></div></div>
    <?php foreach (moderation_hints($submission) as $hint): ?><p class="notice"><?= h($hint) ?></p><?php endforeach; ?>
    <?php if (!empty($submission['needs_repair'])): ?><p class="notice">Datos incompletos: revisá esta ficha antes de publicar.</p><?php endif; ?>
    <details><summary>Contacto privado y permisos</summary><?php admin_private_contact_details($submission); ?><p>Permisos: nombre <?= !empty($submission['public_name']) ? h($submission['public_display_name'] ?? '') : 'privado' ?> · <?= h(whatsapp_visibility_label(($submission['public_whatsapp'] ?? false) === true)) ?>.</p><small>Reglas aceptadas: <?= h($submission['consents']['terms_version'] ?? 'Sin versión registrada') ?></small></details>
    <details><summary>Historia y datos del perro</summary><p><?= nl2br(h($submission['description'])) ?></p><p><?= h($submission['age_group'] . ' · ' . $submission['sex'] . ' · ' . $submission['size']) ?> · <?= count($submission['photos']) ?> foto(s)</p><?php if ($submission['internal_note'] !== ''): ?><p><strong>Nota interna:</strong> <?= h($submission['internal_note']) ?></p><?php endif; ?></details></div>
    <div class="admin-card-actions"><a class="button" href="/admin/edit?dataset=submissions&amp;id=<?= h($submission['id']) ?>&amp;<?= h(admin_context_query()) ?>">Revisar ficha y fotos</a><?php admin_whatsapp_links($submission); render_submission_actions($submission); ?></div></article><?php
}

function render_submission_actions(array $record): void
{
    if ($record['status'] === 'pending') {
        ?><form method="post" action="/admin/action"><?php admin_action_fields('submissions', $record['id'], 'approve', 'solicitudes'); ?><label class="check"><input type="checkbox" name="review_confirm" value="1" required> Revisé la ficha, fotos, permisos y adopción gratuita.</label><button class="button button-full" type="submit">Aprobar y publicar</button></form><details class="reject-tools"><summary>Rechazar solicitud</summary><form method="post" action="/admin/action"><?php admin_action_fields('submissions', $record['id'], 'reject', 'solicitudes'); ?><label>Motivo interno<input name="note" maxlength="1000"></label><button class="button button-danger" type="submit">Confirmar rechazo</button></form></details><?php
    } elseif ($record['status'] === 'rejected') {
        ?><form method="post" action="/admin/action"><?php admin_action_fields('submissions', $record['id'], 'reopen', 'solicitudes'); ?><button class="button button-secondary" type="submit">Reabrir para revisar</button></form><?php
    }
}

function admin_context_query(): string
{
    $query = [];
    foreach (['section', 'queue', 'q', 'dog_status', 'page'] as $key) if (query_value($key) !== '') $query[$key] = query_value($key);
    return http_build_query($query);
}

function render_admin_dog(array $dog, string $section): void
{
    $source = !empty($dog['source_submission_id']) ? find_record('submissions', $dog['source_submission_id']) : null;
    $contact = admin_dog_contact_record($dog); $public = public_dog_by_slug($dog['slug']) !== null;
    $expired = $dog['status'] === 'published' && strtotime($dog['expires_at']) <= time() && !in_array($dog['adoption_status'], ['adopted', 'reunited'], true);
    ?><article class="admin-card"><div><span class="status-pill"><?= $expired ? 'Vencida' : h(record_status_label($dog['status']) . ' · ' . record_status_label($dog['adoption_status'])) ?></span><h3><?= h($dog['name']) ?></h3><p><?= h(record_status_label($dog['listing_type'])) ?> · <?= h($dog['city']) ?> · Ficha <?= h(listing_share_code($dog)) ?><br><?= $public ? 'Visible públicamente' : 'Fuera de la búsqueda pública' ?> · Vence el <?= h(format_date($dog['expires_at'])) ?></p>
    <?php if (report_count($dog['id'])): ?><a class="notice" href="/admin?section=reportes&amp;q=<?= h(rawurlencode($dog['id'])) ?>"><?= report_count($dog['id']) ?> reporte(s) abierto(s)</a><?php endif; ?>
    <?php if ($public): ?><a class="text-link" href="/perro/<?= h($dog['slug']) ?>" target="_blank" rel="noopener noreferrer">Ver ficha pública ↗</a><div class="button-row"><a class="button button-small button-secondary" href="/perro/<?= h($dog['slug']) ?>#compartir" target="_blank" rel="noopener noreferrer">Texto e imágenes para compartir</a></div><?php endif; admin_private_contact_details($contact); admin_whatsapp_links($contact); ?></div>
    <div class="admin-card-actions"><a class="button button-secondary" href="/admin/edit?dataset=<?= $source ? 'submissions' : 'dogs' ?>&amp;id=<?= h($source['id'] ?? $dog['id']) ?>&amp;<?= h(admin_context_query()) ?>">Editar ficha y privacidad</a>
    <form method="post" action="/admin/action"><?php admin_action_fields('dogs', $dog['id'], '', $section); ?><label>Nuevo estado<select name="action" required><option value="">Elegí una acción</option><?php select_options(($dog['listing_type'] === 'adoption' ? ['reserved'=>'Reservado', 'adopted'=>'Adoptado'] : ['reunited'=>'Reencontrado']) + ['available'=>'Renovar / reabrir', 'expired'=>'Marcar vencido', 'unpublish'=>'Retirar aviso']); ?></select></label><label>¿El resultado fue por Perro?<select name="outcome_via_perro"><?php select_options(['unknown'=>'No sabemos', 'yes'=>'Sí', 'no'=>'No']); ?></select></label><label class="check"><input type="checkbox" name="owner_confirmed" value="1"> Confirmé con el responsable que sigue vigente (obligatorio para renovar).</label><button class="button button-full" type="submit">Guardar estado</button></form><small>Adoptado, reencontrado, vencido o retirado deja de aparecer públicamente.</small></div></article><?php
}

function render_admin_report(array $report): void
{
    $dog = find_record('dogs', $report['dog_id']);
    ?><article class="admin-card"><div><span class="status-pill"><?= h(record_status_label($report['status'])) ?></span><h3><?= h($report['dog_name']) ?></h3><p><?= h(report_categories()[$report['category'] ?? 'other'] ?? 'Otro motivo') ?> · <?= h(format_date($report['created_at'])) ?></p><p><?= h($report['reason']) ?></p><small><?= h($report['contact']) ?></small>
    <?php if ($dog): ?><p>Estado: <?= h(record_status_label($dog['status']) . ' · ' . record_status_label($dog['adoption_status'])) ?></p><a class="button button-small button-secondary" href="/admin/edit?dataset=dogs&amp;id=<?= h($dog['id']) ?>&amp;section=reportes">Ver ficha y cambiar estado</a><?php if (public_dog_by_slug($dog['slug'])): ?><a class="text-link" href="/perro/<?= h($dog['slug']) ?>">Ver ficha pública</a><?php endif; ?><?php endif; ?>
    <?php if (!empty($report['resolution_note'])): ?><p>Resolución: <?= h($report['resolution_note']) ?></p><?php endif; ?></div>
    <?php if ($report['status'] === 'open'): ?><form method="post" action="/admin/action"><?php admin_action_fields('reports', $report['id'], 'resolve', 'reportes'); ?><label>Nota de resolución<textarea name="note" maxlength="1000"></textarea></label><button class="button button-small button-secondary" type="submit">Marcar revisado</button></form><?php endif; ?></article><?php
}
