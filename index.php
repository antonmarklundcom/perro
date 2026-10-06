<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/render.php';
require __DIR__ . '/includes/legal.php';
require __DIR__ . '/includes/moderation.php';
require __DIR__ . '/includes/accounts.php';
require __DIR__ . '/includes/experience.php';
require __DIR__ . '/includes/sharing.php';
require __DIR__ . '/includes/guidance.php';

$path = request_path();
if (method_is_post() && ini_bytes((string) ini_get('post_max_size')) > 0
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_bytes((string) ini_get('post_max_size'))) {
    render_error_page('El envío es demasiado grande', 'Reducí las fotos o enviá menos archivos. El servidor rechazó el tamaño total del envío.', 413); exit;
}
admin_edit_routes($path);
admin_account_routes($path);
sharing_routes($path);

if ($path === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nSitemap: " . app_url('sitemap.xml') . "\n";
    exit;
}

if ($path === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $routes = ['', 'perros', 'cachorros-en-adopcion', 'perros-de-raza-en-adopcion', 'perros-perdidos-paraguay', 'dar-perro-en-adopcion', 'centros-de-adopcion', 'como-funciona', 'seguridad', 'privacidad', 'terminos'];
    foreach ($routes as $route) {
        echo '<url><loc>' . h(app_url($route)) . '</loc></url>';
    }
    foreach (public_dogs() as $dog) {
        echo '<url><loc>' . h(app_url('perro/' . $dog['slug'])) . '</loc><lastmod>' . h(substr((string) ($dog['updated_at'] ?? $dog['published_at']), 0, 10)) . '</lastmod></url>';
    }
    echo '</urlset>';
    exit;
}

if (preg_match('#^media/([a-z0-9-]+)/([a-z0-9._-]+)$#i', $path, $matches)) {
    $dog = find_record('dogs', $matches[1]);
    $filename = basename($matches[2]);
    if (!$dog || ($dog['status'] ?? '') !== 'published'
        || (!empty($dog['expires_at']) && strtotime((string) $dog['expires_at']) < time())
        || in_array($dog['adoption_status'] ?? '', ['adopted', 'reunited'], true)
        || !in_array($filename, $dog['photos'] ?? [], true)) {
        http_response_code(404);
        exit;
    }
    $file = PERRO_UPLOADS . '/' . basename($dog['source_submission_id'] ?? $dog['id']) . '/' . $filename;
    $details = is_file($file) ? @getimagesize($file) : false;
    if (!$details) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $details['mime']);
    header('Cache-Control: no-store');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if ($path === 'enviar-perro' && method_is_post()) {
    require_csrf();
    if (text('website') !== '') {
        redirect('gracias');
    }
    $started = (int) text('form_started', 20);
    if ($started < 1 || time() - $started < 3) {
        set_flash('error', 'Completá el formulario con calma antes de enviarlo.');
        redirect('dar-perro-en-adopcion');
    }
    $required = ['submitter_name', 'whatsapp', 'relationship', 'name', 'department', 'city', 'age_group', 'sex', 'size', 'description'];
    $errors = listing_input_errors();
    if (!in_array(text('relationship'), ['Responsable actual', 'Hogar temporal', 'Rescatista independiente', 'Organización', 'Otra'], true)) $errors[] = 'Elegí una relación válida con el perro.';
    foreach ($required as $field) {
        if (text($field) === '') {
            $errors[] = 'Completá todos los campos obligatorios.';
            break;
        }
    }
    $email = filter_var(text('email', 180), FILTER_VALIDATE_EMAIL) ?: '';
    $whatsapp = valid_whatsapp(text('whatsapp', 40));
    if (text('email', 180) !== '' && $email === '') {
        $errors[] = 'Ingresá un correo válido.';
    }
    if ($whatsapp === '') {
        $errors[] = 'Ingresá un WhatsApp paraguayo válido.';
    }
    foreach (['adult_confirm', 'authorized_confirm', 'photo_consent', 'terms_accept', 'no_sale_confirm'] as $confirmation) {
        if (!checked($confirmation)) {
            $errors[] = 'Necesitamos todas las confirmaciones obligatorias.';
            break;
        }
    }
    if (text('terms_version') !== PERRO_TERMS_VERSION || text('privacy_version') !== PERRO_PRIVACY_VERSION) {
        $errors[] = 'Las reglas se actualizaron. Revisá los textos y confirmá nuevamente antes de enviar.';
    }
    $publicName = text('name_visibility') === 'public';
    $publicDisplayName = $publicName ? text('public_display_name', 80) : '';
    if ($publicName && $publicDisplayName === '') {
        $errors[] = 'Escribí el nombre o alias que autorizás mostrar, o elegí no mostrar tu nombre.';
    }
    if (!in_array(text('name_visibility'), ['', 'private', 'public'], true)) {
        $errors[] = 'Elegí una opción válida para la privacidad de tu nombre.';
    }
    if ($errors) {
        $_SESSION['old'] = array_filter($_POST, 'is_string');
        set_flash('error', implode(' ', array_unique($errors)));
        redirect('dar-perro-en-adopcion');
    }
    $id = random_id('sub-');
    if (!allow_public_request('submission', 20)) {
        render_error_page('Llegaste al límite de envíos', 'Esperá una hora antes de enviar otra ficha. Si necesitás publicar varios rescates, escribinos por WhatsApp.', 429); exit;
    }
    $record = [
        'id' => $id,
        'reference' => strtoupper(substr(str_replace('-', '', $id), -8)),
        'status' => 'pending',
        'listing_type' => in_array(text('listing_type'), ['adoption', 'lost', 'found'], true) ? text('listing_type') : 'adoption',
        'submitter_name' => text('submitter_name', 120),
        'email' => $email,
        'whatsapp' => $whatsapp,
        'relationship' => text('relationship', 60),
        'public_name' => $publicName,
        'public_display_name' => $publicDisplayName,
        'public_whatsapp' => checked('public_whatsapp'),
        'consents' => [
            'adult_confirm' => true,
            'authorized_confirm' => true,
            'photo_consent' => true,
            'no_sale_confirm' => true,
            'terms_version' => PERRO_TERMS_VERSION,
            'privacy_version' => PERRO_PRIVACY_VERSION,
            'accepted_at' => now_iso(),
            'public_name' => $publicName,
            'public_whatsapp' => checked('public_whatsapp'),
        ],
        'name' => text('name', 80),
        'department' => text('department', 80),
        'city' => text('city', 100),
        'age_group' => text('age_group', 30),
        'approximate_age' => text('approximate_age', 60),
        'sex' => text('sex', 20),
        'size' => text('size', 30),
        'breed_label' => text('breed_label', 100),
        'mixed_breed' => checked('mixed_breed'),
        'description' => text('description', 3000),
        'health_information' => text('health_information', 1200),
        'vaccination_status' => text('vaccination_status', 20),
        'sterilization_status' => text('sterilization_status', 20),
        'compatibility' => text('compatibility', 800),
        'reason' => text('reason', 1000),
        'adoption_requirements' => text('adoption_requirements', 1200),
        'last_location' => text('last_location', 180),
        'incident_date' => text('incident_date', 20),
        'photos' => [],
        'internal_note' => '',
        'created_at' => now_iso(),
        'updated_at' => now_iso(),
    ];
    $record['photos'] = save_submission_images($id, $photoErrors);
    if ($photoErrors) {
        remove_upload_folder($id);
        $_SESSION['old'] = array_filter($_POST, 'is_string');
        set_flash('error', implode(' ', $photoErrors));
        redirect('dar-perro-en-adopcion');
    }
    if (!save_record('submissions', $record)) {
        remove_upload_folder($id);
        render_error_page('No pudimos guardar el envío', 'Intentá nuevamente. Si el problema continúa, avisale a la persona administradora.', 500);
        exit;
    }
    $_SESSION['last_reference'] = $record['reference'];
    unset($_SESSION['old']);
    redirect('gracias');
}

if ($path === 'reportar' && method_is_post()) {
    require_csrf();
    $dog = public_dog_by_slug(text('slug', 160));
    if (!$dog || text('reason', 1000) === '') {
        set_flash('error', 'No pudimos registrar el reporte. Revisá el motivo e intentá otra vez.');
        redirect('perros');
    }
    if (!allow_public_request('report', 10)) {
        render_error_page('Llegaste al límite de reportes', 'Esperá una hora o escribile al equipo por WhatsApp.', 429); exit;
    }
    $saved = save_record('reports', [
        'id' => random_id('rep-'),
        'dog_id' => $dog['id'],
        'dog_name' => $dog['name'],
        'reason' => text('reason', 1000),
        'contact' => text('contact', 180),
        'status' => 'open',
        'created_at' => now_iso(),
    ]);
    if (!$saved) { render_error_page('No se guardó el reporte', 'Intentá nuevamente o escribile al equipo.', 500); exit; }
    set_flash('success', 'Recibimos el reporte. Lo vamos a revisar.');
    redirect('perro/' . $dog['slug']);
}

if ($path === 'admin/login' && method_is_post()) {
    require_csrf();
    if (verify_admin_login(text('username', 180), password_input('password'))) {
        session_regenerate_id(true);
        $_SESSION['perro_admin'] = true;
        $_SESSION['admin_started'] = $_SESSION['admin_last_seen'] = time();
        $_SESSION['admin_version'] = admin_credential_version();
        set_flash('success', 'Sesión iniciada.');
        redirect('admin');
    }
    set_flash('error', 'Usuario o contraseña incorrectos.');
    redirect('admin');
}

if ($path === 'admin/logout' && method_is_post()) {
    require_csrf();
    unset($_SESSION['perro_admin'], $_SESSION['admin_account_id'], $_SESSION['admin_version'], $_SESSION['admin_started'], $_SESSION['admin_last_seen']);
    session_regenerate_id(true);
    redirect('admin');
}

if ($path === 'admin/action' && method_is_post()) {
    require_admin();
    require_csrf();
    $action = text('action', 40);
    if ($action === 'change_password') {
        $result = change_admin_password(password_input('current_password'), password_input('new_password'), password_input('confirm_password'));
        set_flash($result[0], $result[1]);
        redirect('admin');
    }
    $dataset = text('dataset', 30);
    $id = text('id', 100);
    if (!in_array($dataset, ['submissions', 'dogs', 'reports'], true)) {
        render_error_page('Acción no permitida', 'El registro solicitado no es válido.', 400);
        exit;
    }
    $result = moderate_record($dataset, $id, $action);
    set_flash($result[0], $result[1]);
    redirect(admin_return_url());
}

if ($path === 'admin/export.csv') {
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="perro-listados-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Nombre', 'Tipo', 'Estado', 'Ciudad', 'Departamento', 'Publicado', 'Vence']);
    foreach (read_dataset('dogs') as $dog) {
        fputcsv($out, array_map(static fn($value): string => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'" . $value : (string) $value, [$dog['id'], $dog['name'], $dog['listing_type'], $dog['status'], $dog['city'], $dog['department'], $dog['published_at'], $dog['expires_at']]));
    }
    fclose($out);
    exit;
}

if ($path === '') {
    $dogs = array_slice(public_dogs('adoption'), 0, 6);
    render_header(page_meta('Perros en adopción en Paraguay | Perro', 'Encontrá perros para adoptar o publicá responsablemente un perro que necesita hogar en Paraguay.'));
    ?>
    <section class="hero">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <span class="eyebrow">Adopción responsable · Paraguay</span>
                <h1>Un hogar cambia<br>toda una vida.</h1>
                <p class="hero-lead">Encontrá perros en adopción en Paraguay. Si conocés uno que necesita una familia, ayudalo a encontrarla con un aviso gratuito.</p>
                <div class="button-row"><a class="button" href="/perros">Quiero adoptar</a><a class="button button-secondary" href="/dar-perro-en-adopcion">Publicar un aviso</a></div>
                <div class="trust-line"><span>✓ Publicación gratuita</span><span>✓ Revisión antes de publicar</span><span>✓ Contacto responsable</span></div>

            </div>
            <figure class="hero-visual">
                <img src="/assets/images/hero-perro.webp" alt="Perro mestizo sentado junto al portón de una casa en un barrio arbolado" width="1536" height="1024" fetchpriority="high">
                <figcaption>Imagen ilustrativa</figcaption>

            </figure>
        </div>
    </section>
    <section class="quick-search" aria-labelledby="buscar-titulo"><div class="shell search-panel"><div><span class="eyebrow">Encontrá a tu compañero</span><h2 id="buscar-titulo">Tu compañero puede estar cerca</h2></div><form action="/perros" method="get"><label><span>Ciudad</span><input name="city" placeholder="Ej. Asunción"></label><label><span>Edad</span><select name="age"><option value="">Todas</option><option>Cachorro</option><option>Joven</option><option>Adulto</option><option>Senior</option></select></label><label><span>Tamaño</span><select name="size"><option value="">Todos</option><?php select_options(listing_options()['size']); ?></select></label><button class="button" type="submit">Buscar perros</button></form></div></section>
    <section class="section"><div class="shell"><div class="section-head"><div><span class="eyebrow">Fichas revisadas</span><h2>Perros que buscan hogar</h2></div><a class="text-link" href="/perros">Ver todos <span aria-hidden="true">→</span></a></div>
        <?php if ($dogs): ?><div class="dog-grid"><?php foreach ($dogs as $dog) dog_card($dog); ?></div><?php else: ?><div class="empty-state"><span class="empty-mark">P</span><h3>Las primeras historias todavía están por llegar</h3><p>Todavía no hay fichas activas. ¿Conocés un perro que necesita hogar? Enviá su aviso y el equipo lo revisará antes de publicarlo.</p><a class="button button-coral" href="/dar-perro-en-adopcion">Enviar la primera ficha</a></div><?php endif; ?>
    </div></section>
    <section class="section section-blue"><div class="shell"><div class="section-head"><div><span class="eyebrow">Simple y cuidado</span><h2>Cómo funciona</h2></div></div><div class="steps"><article><span>1</span><h3>Enviás la ficha</h3><p>Contanos quién es el perro, dónde está y cómo pueden contactarte.</p></article><article><span>2</span><h3>La revisamos</h3><p>Una persona administradora verifica que esté completa y no sea una venta.</p></article><article><span>3</span><h3>Conectan con cuidado</h3><p>La persona interesada habla con el responsable y acuerdan un encuentro seguro.</p></article></div></div></section>
    <section class="section"><div class="shell split-callout"><div><span class="eyebrow">Antes de decir sí</span><h2>Adoptar es sumar una vida a la tuya</h2><p>Preguntá por salud, carácter, alimentación y rutina. Conocé al perro en un lugar seguro y nunca envíes dinero para “reservarlo”.</p><a class="text-link" href="/seguridad">Leé la guía de adopción segura <span aria-hidden="true">→</span></a></div><aside><strong>Una adopción responsable necesita:</strong><ul><li>Tiempo de adaptación</li><li>Atención veterinaria</li><li>Espacio y cuidados diarios</li><li>Compromiso para toda su vida</li></ul></aside></div></section>
    <?php
    render_footer();
    exit;
}

if (in_array($path, ['perros', 'cachorros-en-adopcion', 'perros-de-raza-en-adopcion', 'perros-perdidos-paraguay'], true)) {
    render_discovery($path); exit;
}

if (preg_match('#^perro/([a-z0-9-]+)$#', $path, $matches)) {
    $dog = public_dog_by_slug($matches[1]);
    if (!$dog) {
        render_error_page('Esta ficha no está disponible', 'Puede haber vencido, sido retirada o terminado en una adopción.', 404);
        exit;
    }
    $image = !empty($dog['photos'][0]) ? app_url('media/' . $dog['id'] . '/' . $dog['photos'][0]) : null;
    $typeLabel = listing_options()['listing_type'][$dog['listing_type']] ?? 'Adopción';
    $meta = page_meta($dog['name'] . ', ' . $typeLabel . ' en ' . $dog['city'] . ' | Perro', 'Conocé a ' . $dog['name'] . ' en ' . $dog['city'] . ', ' . $dog['department'] . '. ' . $dog['age_group'] . ' · ' . $dog['size'] . ' · ' . $typeLabel . '.', 'perro/' . $dog['slug'], true, $image);
    // A listing without a usable photo must never inherit an illustrative dog as its preview.
    $hasSharePhoto = listing_share_has_photo($dog);
    $meta['image'] = $hasSharePhoto ? ($image ?? '') : '';
    $meta['image_alt'] = 'Foto de ' . $dog['name'] . ' en ' . $dog['city'];
    if ($hasSharePhoto && sharing_available()) {
        $meta['image'] = listing_share_image_url($dog, 'facebook');
        $meta['image_width'] = 1200; $meta['image_height'] = 630; $meta['image_type'] = 'image/jpeg';
    }
    render_header($meta);
    ?><section class="section dog-detail"><nav class="shell breadcrumbs" aria-label="Ruta de navegación"><a href="/">Inicio</a><span>›</span><a href="<?= ($dog['listing_type'] ?? 'adoption') === 'adoption' ? '/perros' : '/perros-perdidos-paraguay' ?>"><?= ($dog['listing_type'] ?? 'adoption') === 'adoption' ? 'Adopción' : 'Perdidos y encontrados' ?></a><span>›</span><span><?= h($dog['name']) ?></span></nav><div class="shell dog-detail-grid"><div class="dog-gallery"><?php if (!empty($dog['photos'])): foreach ($dog['photos'] as $index => $photo): ?><img src="/media/<?= h($dog['id']) ?>/<?= h($photo) ?>" alt="<?= h($dog['name']) ?><?= $index ? ', otra vista' : '' ?>"<?= $index ? ' loading="lazy"' : '' ?>><?php endforeach; else: ?><div class="photo-placeholder large">Foto no disponible</div><?php endif; ?></div><article class="dog-profile"><span class="eyebrow"><?= h($dog['city']) ?> · <?= h($dog['department']) ?></span><h1><?= h($dog['name']) ?></h1><?php if (($dog['public_name'] ?? false) === true && !empty($dog['contact_name'])): ?><p class="date-note">Nombre público del responsable: <?= h($dog['contact_name']) ?></p><?php endif; ?><p class="status-pill"><?= h($typeLabel) ?><?= ($dog['adoption_status'] ?? '') === 'reserved' ? ' · Reservado' : '' ?></p><?php if (($dog['listing_type'] ?? 'adoption') !== 'adoption'): ?><p><strong>Zona aproximada:</strong> <?= h($dog['last_location'] ?? '') ?><br><strong>Fecha:</strong> <?= h($dog['incident_date'] ?? '') ?></p><div class="notice">Este aviso busca reunir al perro con su responsable. No es una oferta de adopción. Verificá la relación con el perro y avisá a las autoridades competentes.</div><?php endif; ?><p class="profile-lead"><?= h($dog['description']) ?></p><dl class="facts"><div><dt>Edad</dt><dd><?= h($dog['approximate_age'] ?: $dog['age_group']) ?></dd></div><div><dt>Sexo</dt><dd><?= h($dog['sex']) ?></dd></div><div><dt>Tamaño</dt><dd><?= h($dog['size']) ?></dd></div><div><dt>Raza</dt><dd><?= h($dog['breed_label'] ?: (!empty($dog['mixed_breed']) ? 'Mestizo o raza aproximada' : 'Raza no informada')) ?></dd></div><div><dt>Vacunas</dt><dd><?= h($dog['vaccination_status'] ?: 'No informado') ?></dd></div><div><dt>Esterilización</dt><dd><?= h($dog['sterilization_status'] ?: 'No informado') ?></dd></div></dl>
    <?php if ($dog['compatibility']): ?><h2>Compatibilidad conocida</h2><p><?= nl2br(h($dog['compatibility'])) ?></p><?php endif; ?><?php if ($dog['health_information']): ?><h2>Información de salud</h2><p><?= nl2br(h($dog['health_information'])) ?></p><small>Información declarada por la persona responsable; verificá con un profesional veterinario.</small><?php endif; ?><?php if ($dog['adoption_requirements']): ?><h2>Lo que busca su responsable</h2><p><?= nl2br(h($dog['adoption_requirements'])) ?></p><?php endif; ?>
    <div class="profile-actions"><?php if (!empty($dog['contact_whatsapp'])): $msg = rawurlencode('Hola, vi el aviso de ' . $dog['name'] . ' (' . $typeLabel . ') en Perro y quisiera aportar o consultar información.'); ?><a class="button button-whatsapp" href="https://wa.me/<?= h($dog['contact_whatsapp']) ?>?text=<?= h($msg) ?>" rel="noopener noreferrer">Consultar por WhatsApp</a><?php else: ?><a class="button button-whatsapp" href="<?= h(project_whatsapp_url()) ?>" rel="noopener noreferrer">Consultar al equipo de Perro</a><p>El contacto del responsable permanece privado.</p><?php endif; ?></div><p><a class="text-link" href="#compartir">Compartí este aviso →</a></p><p class="date-note">Publicada el <?= h(date('d/m/Y', strtotime($dog['published_at']))) ?> · Última confirmación <?= h(date('d/m/Y', strtotime($dog['last_confirmed_at']))) ?></p></article></div></section>
    <?php render_listing_sharing($dog); ?>
    <section class="section section-note"><div class="shell narrow"><h2>¿Hay algo incorrecto en esta ficha?</h2><form class="inline-report" method="post" action="/reportar"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= h($dog['slug']) ?>"><label>Motivo<textarea name="reason" required maxlength="1000" placeholder="Contanos qué debería revisar la administración"></textarea></label><label>Tu contacto (opcional)<input name="contact" maxlength="180"></label><button class="button button-small button-secondary" type="submit">Enviar reporte</button></form></div></section>
    <?php render_footer(); exit;
}

if ($path === 'dar-perro-en-adopcion') {
    $old = $_SESSION['old'] ?? [];
    $retry = !empty($old);
    $requestedType = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'adoption';
    if (!isset($old['listing_type']) && isset(listing_options()['listing_type'][$requestedType])) $old['listing_type'] = $requestedType;
    unset($_SESSION['old']);
    render_header(page_meta('Dar un perro en adopción en Paraguay | Perro', 'Enviá gratis una ficha para revisión y ayudá a encontrar un hogar responsable.', 'dar-perro-en-adopcion'));
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow">Publicación gratuita y moderada</span><h1>Publicá un aviso. Ayudá a un perro.</h1><p>Sin cuenta y sin correo obligatorio. Completá el aviso y el equipo te contactará por WhatsApp para revisarlo. No aceptamos ventas ni cobros por entrega.</p><a class="text-link" href="/como-funciona">Cómo publicar, actualizar y compartir un aviso →</a></div></section>
    <section class="section"><div class="shell form-layout"><aside class="form-aside"><h2>Tu aviso, paso a paso</h2><nav class="form-jump" aria-label="Secciones del formulario"><a href="#aviso">1. Aviso</a><a href="#perro">2. Datos del perro</a><a href="#fotos">3. Fotos</a><a href="#contacto">4. Contacto privado</a></nav><ul class="check-list"><li>Debés tener 18 años o más.</li><li>Necesitás autorización para publicar al perro.</li><li>Contá lo que sabés con honestidad.</li><li>La adopción debe ser gratuita.</li><li>Las fotos deben ser tuyas o tener permiso.</li></ul><div class="notice">Los datos de contacto permanecen privados salvo que autorices mostrar tu WhatsApp.</div></aside><form class="submission-form owner-form" data-retry="<?= $retry ? '1' : '0' ?>" method="post" action="/enviar-perro" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="form_started" value="<?= time() ?>"><div class="honeypot" aria-hidden="true"><label>Sitio web<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <fieldset id="aviso"><legend>1. Sobre la publicación</legend><div class="field-grid"><label>Tipo de aviso<select name="listing_type" required><?php select_options(listing_options()['listing_type'], $old['listing_type'] ?? 'adoption'); ?></select></label><label>Tu relación con el perro<select name="relationship" required><option value="">Seleccioná</option><?php select_options(array_combine(['Responsable actual', 'Hogar temporal', 'Rescatista independiente', 'Organización', 'Otra'], ['Responsable actual', 'Hogar temporal', 'Rescatista independiente', 'Organización', 'Otra']), $old['relationship'] ?? ''); ?></select></label></div></fieldset>
    <fieldset id="perro"><legend>2. Datos del perro</legend><div class="field-grid"><label>Nombre del perro (o «Sin nombre»)<input name="name" required maxlength="80" value="<?= h($old['name'] ?? '') ?>"></label><label>Departamento<input name="department" list="departamentos" required maxlength="80" placeholder="Ej. Central" value="<?= h($old['department'] ?? '') ?>"></label><label>Ciudad<input name="city" list="ciudades" required maxlength="100" placeholder="Ej. Luque" value="<?= h($old['city'] ?? '') ?>"></label><label>Edad aproximada<input name="approximate_age" maxlength="60" placeholder="Ej. 2 años" value="<?= h($old['approximate_age'] ?? '') ?>"></label><label>Etapa<select name="age_group" required><option value="">Seleccioná</option><?php select_options(listing_options()['age_group'], $old['age_group'] ?? ''); ?></select></label><label>Sexo<select name="sex" required><option value="">Seleccioná</option><?php select_options(listing_options()['sex'], $old['sex'] ?? ''); ?></select></label><label>Tamaño<select name="size" required><option value="">Seleccioná</option><?php select_options(listing_options()['size'], $old['size'] ?? ''); ?></select></label><label>Raza o apariencia<input name="breed_label" maxlength="100" placeholder="Ej. mestizo tipo labrador" value="<?= h($old['breed_label'] ?? '') ?>"></label></div><label class="check"><input type="checkbox" name="mixed_breed" value="1" <?= ($old['mixed_breed'] ?? '') === '1' ? 'checked' : '' ?>> Es mestizo o la raza es aproximada</label><label>Historia y personalidad<textarea name="description" required minlength="40" maxlength="3000" placeholder="Contá cómo es, qué rutina tiene y qué hogar podría acompañarlo mejor."><?= h($old['description'] ?? '') ?></textarea></label><div class="field-grid"><label>Vacunas<select name="vaccination_status"><?php select_options(listing_options()['vaccination_status'], $old['vaccination_status'] ?? 'No informado'); ?></select></label><label>Esterilización<select name="sterilization_status"><?php select_options(listing_options()['sterilization_status'], $old['sterilization_status'] ?? 'No informado'); ?></select></label></div><details class="optional-details"><summary>Salud, convivencia y requisitos (opcional)</summary><div class="submission-form"><label>Información de salud<textarea name="health_information" maxlength="1200"><?= h($old['health_information'] ?? '') ?></textarea></label><label>Compatibilidad conocida<textarea name="compatibility" maxlength="800" placeholder="Niños, perros, gatos, vida en departamento..."><?= h($old['compatibility'] ?? '') ?></textarea></label><label>Motivo y contexto<textarea name="reason" maxlength="1000"><?= h($old['reason'] ?? '') ?></textarea></label><label>Requisitos para adoptar<textarea name="adoption_requirements" maxlength="1200"><?= h($old['adoption_requirements'] ?? '') ?></textarea></label></div></details><div class="incident-fields"><p>Para avisos de perros perdidos o encontrados, indicá la zona y la fecha. Evitá publicar una dirección exacta.</p><div class="field-grid"><label>Zona aproximada (obligatoria si está perdido o fue encontrado)<input name="last_location" maxlength="180" value="<?= h($old['last_location'] ?? '') ?>"></label><label>Fecha del hecho (obligatoria si está perdido o fue encontrado)<input type="date" name="incident_date" value="<?= h($old['incident_date'] ?? '') ?>"></label></div></div></fieldset>
    <fieldset id="fotos"><legend>3. Fotos</legend><?php if (function_exists('imagecreatefromstring')): ?><label>Hasta cinco fotos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple><span class="field-help">JPG, PNG o WebP. Máximo 5 MB y 8 megapíxeles por foto. El servidor crea una copia limpia y optimizada, sin los metadatos del archivo original. No incluyas documentos, domicilios ni datos personales en la imagen.</span></label><?php else: ?><div class="notice">La extensión de imágenes del servidor todavía no está activa. Podés enviar la ficha sin fotos.</div><?php endif; ?><label class="check"><input type="checkbox" name="photo_consent" value="1" required> Tengo permiso para publicar las fotos que envío.</label></fieldset>
    <fieldset id="contacto"><legend>4. Tu contacto privado</legend>
        <p>La administración usa estos datos para revisar el aviso y contactarte. Tu nombre y tu WhatsApp no se muestran por defecto. Tu correo permanece privado.</p>
        <div class="field-grid">
            <label>Nombre completo (solo administración)<input name="submitter_name" required maxlength="120" autocomplete="name" value="<?= h($old['submitter_name'] ?? '') ?>"></label>
            <label>Correo electrónico (opcional, privado)<input type="email" name="email" maxlength="180" autocomplete="email" value="<?= h($old['email'] ?? '') ?>"></label>
            <label>WhatsApp (privado)<input type="tel" name="whatsapp" inputmode="tel" required maxlength="40" autocomplete="tel" placeholder="0981 000 000" value="<?= h($old['whatsapp'] ?? '') ?>"></label>
        </div>
        <fieldset><legend>Privacidad de tu nombre</legend>
            <label class="check"><input type="radio" name="name_visibility" value="private" <?= ($old['name_visibility'] ?? 'private') !== 'public' ? 'checked' : '' ?>> No mostrar mi nombre</label>
            <label class="check"><input type="radio" name="name_visibility" value="public" <?= ($old['name_visibility'] ?? '') === 'public' ? 'checked' : '' ?>> Autorizo mostrar el nombre o alias público que escribo abajo</label>
            <label>Nombre o alias que querés mostrar<input name="public_display_name" maxlength="80" autocomplete="off" value="<?= h($old['public_display_name'] ?? '') ?>"><span class="field-help">Completalo si elegís mostrar un nombre. No copiamos automáticamente tu nombre completo.</span></label>
        </fieldset>
        <label class="check"><input type="checkbox" name="public_whatsapp" value="1" <?= ($old['public_whatsapp'] ?? '') === '1' ? 'checked' : '' ?>> Autorizo mostrar mi WhatsApp en la ficha pública (opcional).</label>
        <p>Si mantenés tu contacto privado, las personas interesadas pueden consultar al equipo de Perro. No incluyas teléfonos, direcciones exactas ni datos de otras personas en la descripción o las fotos.</p>
        <input type="hidden" name="terms_version" value="<?= PERRO_TERMS_VERSION ?>">
        <input type="hidden" name="privacy_version" value="<?= PERRO_PRIVACY_VERSION ?>">
        <label class="check"><input type="checkbox" name="adult_confirm" value="1" required> Confirmo que tengo 18 años o más.</label>
        <label class="check"><input type="checkbox" name="authorized_confirm" value="1" required> Confirmo que soy responsable o tengo autorización para difundir este aviso.</label>
        <label class="check"><input type="checkbox" name="no_sale_confirm" value="1" required> Este aviso no es una venta ni una oferta de cría. No voy a pedir señas, pagos ni donaciones obligatorias para entregar el perro.</label>
        <label class="check"><input type="checkbox" name="terms_accept" value="1" required> <span>Leí y acepto los <a href="/terminos" target="_blank" rel="noopener noreferrer">términos</a> y leí la <a href="/privacidad" target="_blank" rel="noopener noreferrer">política de privacidad</a>. Los permisos opcionales se eligen por separado.</span></label>
    </fieldset><button class="button button-coral button-full" type="submit">Enviar ficha para revisión</button></form><?php location_suggestions(); ?></div></section>
    <?php render_footer(); exit;
}

if ($path === 'gracias') {
    $reference = $_SESSION['last_reference'] ?? '';
    render_header(page_meta('Ficha recibida | Perro', 'Confirmación de envío.', 'gracias', false));
    $ownerMessage = 'Hola, envié un aviso a Perro. Referencia: ' . ($reference ?: '[escribí tu referencia]') . '. '; ?>
    <section class="section"><div class="shell narrow empty-state receipt">
        <span class="empty-mark">✓</span><span class="eyebrow">Envío recibido</span><h1>Gracias por ayudar</h1>
        <p>La ficha quedó pendiente de revisión. No se publica automáticamente y la aprobación no está garantizada.</p>
        <?php if ($reference): ?><div class="receipt-reference"><p class="reference">Tu referencia: <strong><?= h($reference) ?></strong></p><button class="button button-secondary" type="button" data-copy="<?= h($reference) ?>">Copiar referencia</button><span class="copy-status" role="status"></span></div><?php endif; ?>
        <p>Guardá la referencia o una captura de esta pantalla. El equipo puede contactarte por WhatsApp; no enviamos correos automáticos.</p>
        <h2>¿Necesitás cambiar algo?</h2><p>Escribinos desde el WhatsApp que usaste al enviar. Se abre un borrador con tu referencia; completalo antes de enviarlo. El equipo verifica tu relación con el aviso antes de hacer cambios.</p>
        <?php if (project_whatsapp_url()): ?><div class="receipt-requests">
            <a class="button button-whatsapp" href="<?= h(project_whatsapp_url($ownerMessage . 'Quisiera consultar la revisión o corregir estos datos: ')) ?>" target="_blank" rel="noopener noreferrer">Consultar o corregir</a>
            <a class="button button-secondary" href="<?= h(project_whatsapp_url($ownerMessage . 'Quisiera retirar el aviso o estos permisos: ')) ?>" target="_blank" rel="noopener noreferrer">Solicitar retiro</a>
            <a class="button button-secondary" href="<?= h(project_whatsapp_url($ownerMessage . 'Quisiera actualizar su estado: adoptado, reencontrado o todavía vigente. La situación actual es: ')) ?>" target="_blank" rel="noopener noreferrer">Avisar un cambio de estado</a>
        </div><?php endif; ?>
        <p><a class="text-link" href="/como-funciona">Cómo funciona la revisión y cómo compartir después</a></p><a class="button" href="/perros">Ver avisos</a>
    </div></section><?php
    render_footer(); exit;
}

if ($path === 'como-funciona') { render_how_it_works(); exit; }

$contentPages = [
    'seguridad' => ['Adopción segura', 'Consejos para conocer al perro y evitar engaños.', '<h2>Antes del encuentro</h2><ul><li>Pedí información sobre salud, rutina, carácter y motivo de adopción.</li><li>No envíes dinero para reservar un perro.</li><li>Desconfiá de urgencias artificiales o historias que no se pueden verificar.</li></ul><h2>Durante el encuentro</h2><ul><li>Elegí un lugar seguro y, si podés, andá acompañado.</li><li>Observá al perro con calma y respetá sus tiempos.</li><li>Si hay otros animales en casa, planificá una presentación gradual.</li></ul><h2>Después</h2><p>Coordiná una revisión veterinaria, prepará un espacio tranquilo y mantené una rutina estable durante la adaptación.</p>'],
    'centros-de-adopcion' => ['Centros de adopción y organizaciones', 'Directorio futuro de organizaciones verificadas en Paraguay.', '<div class="empty-state"><span class="empty-mark">+</span><h2>Todavía no publicamos organizaciones</h2><p>Estamos preparando el directorio. Si representás a una organización o grupo de rescate en Paraguay, contactá al equipo para solicitar una revisión de tus datos.</p></div>'],
];

$contentPages = array_replace($contentPages, legal_pages());

if (isset($contentPages[$path])) {
    [$title, $description, $html] = $contentPages[$path];
    render_header(page_meta($title . ' | Perro', $description, $path));
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow">Perro · Paraguay</span><h1><?= h($title) ?></h1><p><?= h($description) ?></p></div></section><section class="section"><article class="shell prose"><?= $html ?></article></section><?php
    render_footer(); exit;
}

if ($path === 'admin') {
    render_admin_panel(); exit;
}

render_error_page('Página no encontrada', 'La dirección que buscaste no existe o cambió.', 404);
