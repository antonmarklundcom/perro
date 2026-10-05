<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/render.php';
require __DIR__ . '/includes/legal.php';

$path = request_path();

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
    $started = (int) ($_POST['form_started'] ?? 0);
    if ($started < 1 || time() - $started < 3) {
        set_flash('error', 'Completá el formulario con calma antes de enviarlo.');
        redirect('dar-perro-en-adopcion');
    }
    $required = ['submitter_name', 'email', 'whatsapp', 'relationship', 'name', 'department', 'city', 'age_group', 'sex', 'size', 'description'];
    $errors = [];
    foreach ($required as $field) {
        if (text($field) === '') {
            $errors[] = 'Completá todos los campos obligatorios.';
            break;
        }
    }
    $email = filter_var(text('email', 180), FILTER_VALIDATE_EMAIL) ?: '';
    $whatsapp = valid_whatsapp(text('whatsapp', 40));
    if ($email === '') {
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
        'mixed_breed' => isset($_POST['mixed_breed']),
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
    $record['photos'] = save_submission_images($id);
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
    save_record('reports', [
        'id' => random_id('rep-'),
        'dog_id' => $dog['id'],
        'dog_name' => $dog['name'],
        'reason' => text('reason', 1000),
        'contact' => text('contact', 180),
        'status' => 'open',
        'created_at' => now_iso(),
    ]);
    set_flash('success', 'Recibimos el reporte. Lo vamos a revisar.');
    redirect('perro/' . $dog['slug']);
}

if ($path === 'admin/login' && method_is_post()) {
    require_csrf();
    if (verify_admin_login(text('username', 100), text('password', 300))) {
        session_regenerate_id(true);
        $_SESSION['perro_admin'] = true;
        set_flash('success', 'Sesión iniciada.');
        redirect('admin');
    }
    set_flash('error', 'Usuario o contraseña incorrectos.');
    redirect('admin');
}

if ($path === 'admin/logout' && method_is_post()) {
    require_csrf();
    unset($_SESSION['perro_admin']);
    session_regenerate_id(true);
    redirect('admin');
}

if ($path === 'admin/action' && method_is_post()) {
    require_admin();
    require_csrf();
    $action = text('action', 40);
    if ($action === 'change_password') {
        $currentPassword = text('current_password', 300);
        $newPassword = text('new_password', 300);
        $confirmPassword = text('confirm_password', 300);
        if (!verify_admin_password($currentPassword)) {
            set_flash('error', 'La contraseña actual no coincide.');
        } elseif (strlen($newPassword) < 14) {
            set_flash('error', 'La nueva contraseña debe tener al menos 14 caracteres.');
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            set_flash('error', 'La confirmación no coincide con la nueva contraseña.');
        } elseif (save_record('settings', ['id' => 'admin', 'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'updated_at' => now_iso()])) {
            record_moderation('password_changed', 'admin');
            set_flash('success', 'Contraseña actualizada. Guardala en un lugar seguro.');
        } else {
            set_flash('error', 'No se pudo guardar la nueva contraseña. Revisá los permisos de storage/data.');
        }
        redirect('admin');
    }
    $dataset = text('dataset', 30);
    $id = text('id', 100);
    if (!in_array($dataset, ['submissions', 'dogs', 'reports'], true)) {
        render_error_page('Acción no permitida', 'El registro solicitado no es válido.', 400);
        exit;
    }
    $record = find_record($dataset, $id);
    if (!$record) {
        render_error_page('Registro no encontrado', 'Puede haber sido eliminado o actualizado.', 404);
        exit;
    }
    if ($dataset === 'submissions' && $action === 'approve') {
        $slug = slugify($record['name']) . '-' . substr(str_replace('-', '', $record['id']), -5);
        $dog = [
            'id' => random_id('dog-'),
            'source_submission_id' => $record['id'],
            'slug' => $slug,
            'listing_type' => $record['listing_type'],
            'name' => $record['name'],
            'department' => $record['department'],
            'city' => $record['city'],
            'age_group' => $record['age_group'],
            'approximate_age' => $record['approximate_age'],
            'sex' => $record['sex'],
            'size' => $record['size'],
            'breed_label' => $record['breed_label'],
            'mixed_breed' => $record['mixed_breed'],
            'description' => $record['description'],
            'health_information' => $record['health_information'],
            'vaccination_status' => $record['vaccination_status'],
            'sterilization_status' => $record['sterilization_status'],
            'compatibility' => $record['compatibility'],
            'adoption_requirements' => $record['adoption_requirements'],
            'last_location' => $record['last_location'],
            'incident_date' => $record['incident_date'],
            'photos' => $record['photos'],
            'adoption_status' => 'available',
            'status' => 'published',
            'public_name' => ($record['public_name'] ?? false) === true,
            'contact_name' => ($record['public_name'] ?? false) === true ? ($record['public_display_name'] ?? '') : '',
            'contact_whatsapp' => $record['public_whatsapp'] ? $record['whatsapp'] : '',
            'published_at' => now_iso(),
            'last_confirmed_at' => now_iso(),
            'expires_at' => date(DATE_ATOM, strtotime('+' . (int) $config['listing_expiry_days'] . ' days')),
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
        $record['status'] = 'approved';
        $record['updated_at'] = now_iso();
        if (save_record('dogs', $dog) && save_record('submissions', $record)) {
            record_moderation('published', $dog['id'], 'Aprobado desde solicitud ' . $record['reference']);
            set_flash('success', 'La ficha quedó publicada.');
        } else {
            set_flash('error', 'No se pudo completar la publicación.');
        }
    } elseif ($dataset === 'submissions' && $action === 'reject') {
        $record['status'] = 'rejected';
        $record['internal_note'] = text('note', 500);
        $record['updated_at'] = now_iso();
        save_record('submissions', $record);
        record_moderation('rejected', $id, $record['internal_note']);
        set_flash('success', 'Solicitud rechazada y guardada en el historial.');
    } elseif ($dataset === 'dogs' && in_array($action, ['available', 'reserved', 'adopted', 'reunited', 'expired', 'unpublish'], true)) {
        if ($action === 'unpublish') {
            $record['status'] = 'removed';
        } elseif ($action === 'expired') {
            $record['status'] = 'expired';
        } else {
            $record['adoption_status'] = $action;
        }
        $record['updated_at'] = now_iso();
        save_record('dogs', $record);
        record_moderation($action, $id);
        set_flash('success', 'Estado actualizado.');
    } elseif ($dataset === 'reports' && $action === 'resolve') {
        $record['status'] = 'resolved';
        $record['updated_at'] = now_iso();
        save_record('reports', $record);
        record_moderation('report_resolved', $id);
        set_flash('success', 'Reporte marcado como resuelto.');
    } else {
        set_flash('error', 'La acción solicitada no está disponible.');
    }
    redirect('admin');
}

if ($path === 'admin/export.csv') {
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="perro-listados-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Nombre', 'Tipo', 'Estado', 'Ciudad', 'Departamento', 'Publicado', 'Vence']);
    foreach (read_dataset('dogs') as $dog) {
        fputcsv($out, [$dog['id'], $dog['name'], $dog['listing_type'], $dog['status'], $dog['city'], $dog['department'], $dog['published_at'], $dog['expires_at']]);
    }
    fclose($out);
    exit;
}

if ($path === '') {
    $dogs = array_slice(public_dogs('adoption'), 0, 6);
    $homeWhatsapp = project_whatsapp_url();
    render_header(page_meta('Perros en adopción en Paraguay | Perro', 'Encontrá perros para adoptar o publicá responsablemente un perro que necesita hogar en Paraguay.'));
    ?>
    <section class="hero">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <span class="eyebrow">Adopción responsable · Paraguay</span>
                <h1>Perros en adopción en Paraguay</h1>
                <p class="hero-lead">Conectamos perros que necesitan un hogar con personas dispuestas a adoptar con responsabilidad.</p>
                <div class="button-row"><a class="button button-whatsapp" href="<?= h($homeWhatsapp) ?>" target="_blank" rel="noopener noreferrer">Escribinos por WhatsApp</a><a class="button button-secondary" href="/perros">Ver perros en adopción</a></div>
                <div class="trust-line"><span>✓ Publicación gratuita</span><span>✓ Revisión antes de publicar</span><span>✓ Contacto responsable</span></div>
                <a class="visible-phone" href="<?= h($homeWhatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp: +595 992 279 599</a>
            </div>
            <figure class="hero-visual">
                <img src="/assets/images/hero-perro.webp" alt="Perro mestizo sentado junto al portón de una casa en un barrio arbolado" width="1536" height="1024">
                <figcaption>Imagen ilustrativa</figcaption>
                <div class="adoption-tab" aria-hidden="true"><span>PY</span><strong>Buscando hogar</strong></div>
            </figure>
        </div>
    </section>
    <section class="quick-search" aria-labelledby="buscar-titulo"><div class="shell search-panel"><div><span class="eyebrow">Encontrá a tu compañero</span><h2 id="buscar-titulo">Buscá por ciudad, edad o tamaño</h2></div><form action="/perros" method="get"><label><span>Ciudad</span><input name="city" placeholder="Ej. Asunción"></label><label><span>Edad</span><select name="age"><option value="">Todas</option><option>Cachorro</option><option>Joven</option><option>Adulto</option><option>Senior</option></select></label><button class="button" type="submit">Buscar perros</button></form></div></section>
    <section class="section"><div class="shell"><div class="section-head"><div><span class="eyebrow">Fichas revisadas</span><h2>Perros que buscan hogar</h2></div><a class="text-link" href="/perros">Ver todos <span aria-hidden="true">→</span></a></div>
        <?php if ($dogs): ?><div class="dog-grid"><?php foreach ($dogs as $dog) dog_card($dog); ?></div><?php else: ?><div class="empty-state"><span class="empty-mark">P</span><h3>Las primeras historias todavía están por llegar</h3><p>No inventamos perros para llenar la página. Cuando aprobemos fichas reales, van a aparecer acá.</p><a class="button button-coral" href="/dar-perro-en-adopcion">Enviar la primera ficha</a></div><?php endif; ?>
    </div></section>
    <section class="section section-blue"><div class="shell"><div class="section-head"><div><span class="eyebrow">Simple y cuidado</span><h2>Cómo funciona</h2></div></div><div class="steps"><article><span>1</span><h3>Enviás la ficha</h3><p>Contanos quién es el perro, dónde está y cómo pueden contactarte.</p></article><article><span>2</span><h3>La revisamos</h3><p>Una persona administradora verifica que esté completa y no sea una venta.</p></article><article><span>3</span><h3>Conectan con cuidado</h3><p>La persona interesada habla con el responsable y acuerdan un encuentro seguro.</p></article></div></div></section>
    <section class="section"><div class="shell split-callout"><div><span class="eyebrow">Antes de decir sí</span><h2>Adoptar es sumar una vida a la tuya</h2><p>Preguntá por salud, carácter, alimentación y rutina. Conocé al perro en un lugar seguro y nunca envíes dinero para “reservarlo”.</p><a class="text-link" href="/seguridad">Leé la guía de adopción segura <span aria-hidden="true">→</span></a></div><aside><strong>Una adopción responsable necesita:</strong><ul><li>Tiempo de adaptación</li><li>Atención veterinaria</li><li>Espacio y cuidados diarios</li><li>Compromiso para toda su vida</li></ul></aside></div></section>
    <?php
    render_footer();
    exit;
}

if ($path === 'perros' || $path === 'cachorros-en-adopcion' || $path === 'perros-de-raza-en-adopcion') {
    $dogs = public_dogs('adoption');
    $city = trim((string) ($_GET['city'] ?? ''));
    $age = trim((string) ($_GET['age'] ?? ''));
    $size = trim((string) ($_GET['size'] ?? ''));
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($path === 'cachorros-en-adopcion') $age = 'Cachorro';
    if ($path === 'perros-de-raza-en-adopcion') $dogs = array_values(array_filter($dogs, static fn(array $d): bool => empty($d['mixed_breed']) && !empty($d['breed_label'])));
    $dogs = array_values(array_filter($dogs, static function (array $dog) use ($city, $age, $size, $q): bool {
        $haystack = strtolower(implode(' ', [$dog['name'], $dog['city'], $dog['department'], $dog['breed_label'], $dog['description']]));
        return ($city === '' || stripos((string) $dog['city'], $city) !== false)
            && ($age === '' || strcasecmp((string) $dog['age_group'], $age) === 0)
            && ($size === '' || strcasecmp((string) $dog['size'], $size) === 0)
            && ($q === '' || str_contains($haystack, strtolower($q)));
    }));
    $heading = $path === 'cachorros-en-adopcion' ? 'Cachorros en adopción' : ($path === 'perros-de-raza-en-adopcion' ? 'Perros de raza en adopción' : 'Perros para adoptar');
    render_header(page_meta($heading . ' en Paraguay | Perro', 'Explorá fichas revisadas de perros que buscan adopción responsable en Paraguay.', $path));
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow">Adopción responsable</span><h1><?= h($heading) ?></h1><p>Filtrá fichas aprobadas y encontrá un perro compatible con tu hogar y tu tiempo.</p></div></section>
    <section class="section listings-layout"><div class="shell"><form class="filters" method="get" action="/<?= h($path) ?>"><label>Buscar<input name="q" value="<?= h($q) ?>" placeholder="Nombre, raza o lugar"></label><label>Ciudad<input name="city" value="<?= h($city) ?>" placeholder="Ej. Luque"></label><label>Edad<select name="age"><option value="">Todas</option><?php select_options(['Cachorro'=>'Cachorro','Joven'=>'Joven','Adulto'=>'Adulto','Senior'=>'Senior'], $age); ?></select></label><label>Tamaño<select name="size"><option value="">Todos</option><?php select_options(['Pequeño'=>'Pequeño','Mediano'=>'Mediano','Grande'=>'Grande'], $size); ?></select></label><button class="button button-small" type="submit">Aplicar filtros</button><a class="text-link" href="/<?= h($path) ?>">Limpiar</a></form>
    <div class="results-head"><p><strong><?= count($dogs) ?></strong> <?= count($dogs) === 1 ? 'ficha encontrada' : 'fichas encontradas' ?></p></div>
    <?php if ($dogs): ?><div class="dog-grid"><?php foreach ($dogs as $dog) dog_card($dog); ?></div><?php else: ?><div class="empty-state"><h2>No encontramos fichas con esos filtros</h2><p>Probá otra ciudad o quitá algún filtro. También podés ayudarnos a sumar una ficha real.</p><div class="button-row"><a class="button" href="/<?= h($path) ?>">Limpiar filtros</a><a class="button button-secondary" href="/dar-perro-en-adopcion">Publicá un perro</a></div></div><?php endif; ?></div></section>
    <?php if ($path === 'perros-de-raza-en-adopcion'): ?><section class="section section-note"><div class="shell narrow"><h2>La raza puede ser aproximada</h2><p>Perro no certifica pedigrí ni pureza de raza. Priorizá el carácter, los cuidados y la compatibilidad real. Una adopción gratuita no significa que cuidar al perro no tenga costos veterinarios y diarios.</p></div></section><?php endif; ?>
    <?php render_footer(); exit;
}

if (preg_match('#^perro/([a-z0-9-]+)$#', $path, $matches)) {
    $dog = public_dog_by_slug($matches[1]);
    if (!$dog) {
        render_error_page('Esta ficha no está disponible', 'Puede haber vencido, sido retirada o terminado en una adopción.', 404);
        exit;
    }
    $image = !empty($dog['photos'][0]) ? app_url('media/' . $dog['id'] . '/' . $dog['photos'][0]) : null;
    render_header(page_meta($dog['name'] . ', perro en adopción en ' . $dog['city'] . ' | Perro', 'Conocé la historia de ' . $dog['name'] . ' y consultá por su adopción responsable.', 'perro/' . $dog['slug'], true, $image));
    // Legacy records may contain a private submitter name: only display explicit consent.
    if (($dog['public_name'] ?? false) === true && !empty($dog['contact_name'])) {
        ?><div class="shell"><p>Nombre público del responsable: <?= h($dog['contact_name']) ?></p></div><?php
    }
    ?><section class="section dog-detail"><div class="shell dog-detail-grid"><div class="dog-gallery"><?php if (!empty($dog['photos'])): foreach ($dog['photos'] as $index => $photo): ?><img src="/media/<?= h($dog['id']) ?>/<?= h($photo) ?>" alt="<?= h($dog['name']) ?><?= $index ? ', otra vista' : '' ?>"<?= $index ? ' loading="lazy"' : '' ?>><?php endforeach; else: ?><div class="photo-placeholder large">Foto no disponible</div><?php endif; ?></div><article class="dog-profile"><span class="eyebrow"><?= h($dog['city']) ?> · <?= h($dog['department']) ?></span><h1><?= h($dog['name']) ?></h1><p class="profile-lead"><?= h($dog['description']) ?></p><dl class="facts"><div><dt>Edad</dt><dd><?= h($dog['approximate_age'] ?: $dog['age_group']) ?></dd></div><div><dt>Sexo</dt><dd><?= h($dog['sex']) ?></dd></div><div><dt>Tamaño</dt><dd><?= h($dog['size']) ?></dd></div><div><dt>Raza</dt><dd><?= h($dog['breed_label'] ?: 'Mestizo') ?></dd></div><div><dt>Vacunas</dt><dd><?= h($dog['vaccination_status'] ?: 'No informado') ?></dd></div><div><dt>Esterilización</dt><dd><?= h($dog['sterilization_status'] ?: 'No informado') ?></dd></div></dl>
    <?php if ($dog['compatibility']): ?><h2>Compatibilidad conocida</h2><p><?= nl2br(h($dog['compatibility'])) ?></p><?php endif; ?><?php if ($dog['health_information']): ?><h2>Información de salud</h2><p><?= nl2br(h($dog['health_information'])) ?></p><small>Información declarada por la persona responsable; verificá con un profesional veterinario.</small><?php endif; ?><?php if ($dog['adoption_requirements']): ?><h2>Lo que busca su responsable</h2><p><?= nl2br(h($dog['adoption_requirements'])) ?></p><?php endif; ?>
    <div class="profile-actions"><?php if (!empty($dog['contact_whatsapp'])): $msg = rawurlencode('Hola, vi a ' . $dog['name'] . ' en Perro y quisiera conocer más sobre su adopción responsable.'); ?><a class="button button-whatsapp" href="https://wa.me/<?= h($dog['contact_whatsapp']) ?>?text=<?= h($msg) ?>" rel="noopener noreferrer">Consultar por WhatsApp</a><?php else: ?><div class="notice">El contacto público no fue autorizado. La administración puede ayudar a verificar la ficha.</div><?php endif; ?></div><p class="date-note">Publicada el <?= h(date('d/m/Y', strtotime($dog['published_at']))) ?> · Última confirmación <?= h(date('d/m/Y', strtotime($dog['last_confirmed_at']))) ?></p></article></div></section>
    <section class="section section-note"><div class="shell narrow"><h2>¿Hay algo incorrecto en esta ficha?</h2><form class="inline-report" method="post" action="/reportar"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= h($dog['slug']) ?>"><label>Motivo<textarea name="reason" required maxlength="1000" placeholder="Contanos qué debería revisar la administración"></textarea></label><label>Tu contacto (opcional)<input name="contact" maxlength="180"></label><button class="button button-small button-secondary" type="submit">Enviar reporte</button></form></div></section>
    <?php render_footer(); exit;
}

if ($path === 'dar-perro-en-adopcion') {
    $old = $_SESSION['old'] ?? [];
    unset($_SESSION['old']);
    render_header(page_meta('Dar un perro en adopción en Paraguay | Perro', 'Enviá gratis una ficha para revisión y ayudá a encontrar un hogar responsable.', 'dar-perro-en-adopcion'));
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow">Publicación gratuita y moderada</span><h1>Dar un perro en adopción</h1><p>La ficha queda pendiente hasta que una persona la revise. No aceptamos ventas ni cobros disfrazados de adopción.</p></div></section>
    <section class="section"><div class="shell form-layout"><aside class="form-aside"><h2>Antes de empezar</h2><ul class="check-list"><li>Debés tener 18 años o más.</li><li>Necesitás autorización para publicar al perro.</li><li>Contá lo que sabés con honestidad.</li><li>La adopción debe ser gratuita.</li><li>Las fotos deben ser tuyas o tener permiso.</li></ul><div class="notice">Los datos de contacto permanecen privados salvo que autorices mostrar tu WhatsApp.</div></aside><form class="submission-form" method="post" action="/enviar-perro" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="form_started" value="<?= time() ?>"><div class="honeypot" aria-hidden="true"><label>Sitio web<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <fieldset><legend>1. Sobre la publicación</legend><div class="field-grid"><label>Tipo de aviso<select name="listing_type" required><option value="adoption">Adopción</option><option value="lost">Perro perdido</option><option value="found">Perro encontrado</option></select></label><label>Tu relación con el perro<select name="relationship" required><option value="">Seleccioná</option><option>Responsable actual</option><option>Hogar temporal</option><option>Rescatista independiente</option><option>Organización</option><option>Otra</option></select></label></div></fieldset>
    <fieldset><legend>2. Datos del perro</legend><div class="field-grid"><label>Nombre del perro<input name="name" required maxlength="80" value="<?= h($old['name'] ?? '') ?>"></label><label>Departamento<input name="department" required maxlength="80" placeholder="Ej. Central" value="<?= h($old['department'] ?? '') ?>"></label><label>Ciudad<input name="city" required maxlength="100" placeholder="Ej. Luque" value="<?= h($old['city'] ?? '') ?>"></label><label>Edad aproximada<input name="approximate_age" maxlength="60" placeholder="Ej. 2 años"></label><label>Etapa<select name="age_group" required><option value="">Seleccioná</option><option>Cachorro</option><option>Joven</option><option>Adulto</option><option>Senior</option></select></label><label>Sexo<select name="sex" required><option value="">Seleccioná</option><option>Hembra</option><option>Macho</option><option>No se sabe</option></select></label><label>Tamaño<select name="size" required><option value="">Seleccioná</option><option>Pequeño</option><option>Mediano</option><option>Grande</option></select></label><label>Raza o apariencia<input name="breed_label" maxlength="100" placeholder="Ej. mestizo tipo labrador"></label></div><label class="check"><input type="checkbox" name="mixed_breed" value="1"> Es mestizo o la raza es aproximada</label><label>Historia y personalidad<textarea name="description" required minlength="40" maxlength="3000" placeholder="Contá cómo es, qué rutina tiene y qué hogar podría acompañarlo mejor."><?= h($old['description'] ?? '') ?></textarea></label><div class="field-grid"><label>Vacunas<select name="vaccination_status"><option>No informado</option><option>Al día</option><option>Parcial</option><option>Sin vacunas</option></select></label><label>Esterilización<select name="sterilization_status"><option>No informado</option><option>Esterilizado</option><option>No esterilizado</option></select></label></div><label>Información de salud<textarea name="health_information" maxlength="1200"></textarea></label><label>Compatibilidad conocida<textarea name="compatibility" maxlength="800" placeholder="Niños, perros, gatos, vida en departamento..."></textarea></label><label>Motivo y contexto<textarea name="reason" maxlength="1000"></textarea></label><label>Requisitos para adoptar<textarea name="adoption_requirements" maxlength="1200"></textarea></label><div class="field-grid"><label>Último lugar visto o encontrado<input name="last_location" maxlength="180"></label><label>Fecha del hecho<input type="date" name="incident_date"></label></div></fieldset>
    <fieldset><legend>3. Fotos</legend><?php if (function_exists('imagecreatefromstring')): ?><label>Hasta cinco fotos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple><span class="field-help">JPG, PNG o WebP. Máximo 5 MB por foto. El servidor crea una copia limpia y optimizada, sin los metadatos del archivo original. No incluyas documentos, domicilios ni datos personales en la imagen.</span></label><?php else: ?><div class="notice">La extensión de imágenes del servidor todavía no está activa. Podés enviar la ficha sin fotos.</div><?php endif; ?><label class="check"><input type="checkbox" name="photo_consent" value="1" required> Tengo permiso para publicar las fotos que envío.</label></fieldset>
    <fieldset><legend>4. Tu contacto privado</legend>
        <p>La administración usa estos datos para revisar el aviso y contactarte. Tu nombre y tu WhatsApp no se muestran por defecto. Tu correo permanece privado.</p>
        <div class="field-grid">
            <label>Nombre completo (solo administración)<input name="submitter_name" required maxlength="120" autocomplete="name" value="<?= h($old['submitter_name'] ?? '') ?>"></label>
            <label>Correo electrónico (privado)<input type="email" name="email" required maxlength="180" autocomplete="email" value="<?= h($old['email'] ?? '') ?>"></label>
            <label>WhatsApp (privado)<input type="tel" name="whatsapp" required maxlength="40" autocomplete="tel" placeholder="0981 000 000" value="<?= h($old['whatsapp'] ?? '') ?>"></label>
        </div>
        <fieldset><legend>Privacidad de tu nombre</legend>
            <label class="check"><input type="radio" name="name_visibility" value="private" <?= ($old['name_visibility'] ?? 'private') !== 'public' ? 'checked' : '' ?>> No mostrar mi nombre</label>
            <label class="check"><input type="radio" name="name_visibility" value="public" <?= ($old['name_visibility'] ?? '') === 'public' ? 'checked' : '' ?>> Autorizo mostrar el nombre o alias público que escribo abajo</label>
            <label>Nombre o alias público (opcional)<input name="public_display_name" maxlength="80" autocomplete="off" value="<?= h($old['public_display_name'] ?? '') ?>"><span class="field-help">Solo se publica si elegís mostrarlo. No copiamos automáticamente tu nombre completo.</span></label>
        </fieldset>
        <label class="check"><input type="checkbox" name="public_whatsapp" value="1" <?= ($old['public_whatsapp'] ?? '') === '1' ? 'checked' : '' ?>> Autorizo mostrar mi WhatsApp en la ficha pública (opcional).</label>
        <p>Si mantenés tu contacto privado, las personas interesadas pueden consultar al equipo de Perro. No incluyas teléfonos, direcciones exactas ni datos de otras personas en la descripción o las fotos.</p>
        <input type="hidden" name="terms_version" value="<?= PERRO_TERMS_VERSION ?>">
        <input type="hidden" name="privacy_version" value="<?= PERRO_PRIVACY_VERSION ?>">
        <label class="check"><input type="checkbox" name="adult_confirm" value="1" required> Confirmo que tengo 18 años o más.</label>
        <label class="check"><input type="checkbox" name="authorized_confirm" value="1" required> Confirmo que soy responsable o tengo autorización para difundir este aviso.</label>
        <label class="check"><input type="checkbox" name="no_sale_confirm" value="1" required> Este aviso no es una venta ni una oferta de cría. No voy a pedir señas, pagos ni donaciones obligatorias para entregar el perro.</label>
        <label class="check"><input type="checkbox" name="terms_accept" value="1" required> Leí y acepto los <a href="/terminos" target="_blank" rel="noopener noreferrer">términos</a> y leí la <a href="/privacidad" target="_blank" rel="noopener noreferrer">política de privacidad</a>. Los permisos opcionales se eligen por separado.</label>
    </fieldset><button class="button button-coral button-full" type="submit">Enviar ficha para revisión</button></form></div></section>
    <?php render_footer(); exit;
}

if ($path === 'gracias') {
    $reference = $_SESSION['last_reference'] ?? '';
    unset($_SESSION['last_reference']);
    render_header(page_meta('Ficha recibida | Perro', 'Confirmación de envío.', 'gracias', false));
    ?><section class="section"><div class="shell narrow empty-state"><span class="empty-mark">✓</span><span class="eyebrow">Envío recibido</span><h1>Gracias por ayudar</h1><p>La ficha quedó pendiente de revisión. No se publica automáticamente y la aprobación no está garantizada.</p><?php if ($reference): ?><p class="reference">Referencia: <strong><?= h($reference) ?></strong></p><?php endif; ?><a class="button" href="/">Volver al inicio</a></div></section><?php
    render_footer(); exit;
}

if ($path === 'perros-perdidos-paraguay') {
    $lost = array_merge(public_dogs('lost'), public_dogs('found'));
    render_header(page_meta('Perros perdidos en Paraguay | Perro', 'Avisos moderados de perros perdidos y encontrados en Paraguay.', $path));
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow">Ayudemos a que vuelvan</span><h1>Perros perdidos en Paraguay</h1><p>Compartí información precisa. Un perro encontrado no se ofrece en adopción hasta aclarar su situación.</p><a class="button button-coral" href="/dar-perro-en-adopcion">Publicar aviso</a></div></section><section class="section"><div class="shell"><?php if ($lost): ?><div class="dog-grid"><?php foreach ($lost as $dog) dog_card($dog); ?></div><?php else: ?><div class="empty-state"><h2>No hay avisos activos</h2><p>Cuando aprobemos un aviso real de perro perdido o encontrado, va a aparecer acá.</p></div><?php endif; ?></div></section><?php
    render_footer(); exit;
}

$contentPages = [
    'como-funciona' => ['Cómo funciona Perro', 'Una ficha pasa por revisión antes de publicarse.', '<h2>Publicar, revisar y conectar</h2><p>La persona responsable completa una ficha. La administración revisa que tenga información suficiente, que no sea una venta y que las fotos tengan autorización. Solo entonces puede aparecer públicamente.</p><h2>Perro no decide la adopción</h2><p>La conversación, el encuentro y la decisión final ocurren entre la persona responsable y quien quiere adoptar. Ambas partes deben hacer preguntas, verificar la información y priorizar el bienestar del animal.</p>'],
    'seguridad' => ['Adopción segura', 'Consejos para conocer al perro y evitar engaños.', '<h2>Antes del encuentro</h2><ul><li>Pedí información sobre salud, rutina, carácter y motivo de adopción.</li><li>No envíes dinero para reservar un perro.</li><li>Desconfiá de urgencias artificiales o historias que no se pueden verificar.</li></ul><h2>Durante el encuentro</h2><ul><li>Elegí un lugar seguro y, si podés, andá acompañado.</li><li>Observá al perro con calma y respetá sus tiempos.</li><li>Si hay otros animales en casa, planificá una presentación gradual.</li></ul><h2>Después</h2><p>Coordiná una revisión veterinaria, prepará un espacio tranquilo y mantené una rutina estable durante la adaptación.</p>'],
    'centros-de-adopcion' => ['Centros de adopción y organizaciones', 'Directorio futuro de organizaciones verificadas en Paraguay.', '<div class="empty-state"><span class="empty-mark">+</span><h2>Todavía no publicamos organizaciones</h2><p>No vamos a inventar alianzas ni datos. Si representás a una organización o grupo de rescate en Paraguay, más adelante vas a poder solicitar una revisión para aparecer acá.</p></div>'],
];

$contentPages = array_replace($contentPages, legal_pages());

if (isset($contentPages[$path])) {
    [$title, $description, $html] = $contentPages[$path];
    render_header(page_meta($title . ' | Perro', $description, $path));
    ?><section class="page-hero compact"><div class="shell"><span class="eyebrow">Perro · Paraguay</span><h1><?= h($title) ?></h1><p><?= h($description) ?></p></div></section><section class="section"><article class="shell prose"><?= $html ?></article></section><?php
    render_footer(); exit;
}

if ($path === 'admin') {
    render_header(page_meta('Administración | Perro', 'Panel privado de moderación.', 'admin', false));
    if (!is_admin()) {
        ?><section class="section"><div class="shell login-card"><span class="eyebrow">Acceso privado</span><h1>Administración</h1><p>Solo para las personas que revisan y publican fichas.</p><form method="post" action="/admin/login"><?= csrf_field() ?><label>Usuario<input name="username" required autocomplete="username"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-full" type="submit">Ingresar</button></form></div></section><?php
        render_footer(); exit;
    }
    $submissions = read_dataset('submissions');
    usort($submissions, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
    $dogs = read_dataset('dogs');
    $reports = read_dataset('reports');
    $pendingCount = count(array_filter($submissions, static fn(array $s): bool => ($s['status'] ?? '') === 'pending'));
    $publishedCount = count(array_filter($dogs, static fn(array $d): bool => ($d['status'] ?? '') === 'published'));
    $openReports = count(array_filter($reports, static fn(array $r): bool => ($r['status'] ?? '') === 'open'));
    ?><section class="admin-hero"><div class="shell admin-title"><div><span class="eyebrow">Panel privado</span><h1>Moderación de Perro</h1></div><div class="admin-actions"><a class="button button-small button-secondary" href="/admin/export.csv">Exportar CSV</a><form method="post" action="/admin/logout"><?= csrf_field() ?><button class="button button-small" type="submit">Cerrar sesión</button></form></div></div></section><section class="section admin-section"><div class="shell"><div class="stats"><article><span>Pendientes</span><strong><?= $pendingCount ?></strong></article><article><span>Publicados</span><strong><?= $publishedCount ?></strong></article><article><span>Reportes abiertos</span><strong><?= $openReports ?></strong></article></div>
    <div class="admin-block">
        <div class="section-head"><div><span class="eyebrow">Cola de revisión</span><h2>Solicitudes</h2></div></div>
        <?php if ($submissions): ?><div class="admin-list">
        <?php foreach ($submissions as $submission):
            $submitterMessage = 'Hola ' . $submission['submitter_name'] . ', somos del equipo de Perro.com.py. Recibimos la ficha de ' . $submission['name'] . ' con referencia ' . $submission['reference'] . ' y queremos confirmar algunos datos antes de continuar.';
            $submitterWhatsapp = 'https://wa.me/' . preg_replace('/\D+/', '', (string) $submission['whatsapp']) . '?text=' . rawurlencode($submitterMessage);
        ?>
        <article class="admin-card">
            <div>
                <span class="status-pill status-<?= h($submission['status']) ?>"><?= h($submission['status']) ?></span>
                <h3><?= h($submission['name']) ?></h3>
                <p><?= h($submission['city']) ?> · <?= h($submission['listing_type']) ?> · <?= h($submission['reference']) ?></p>
                <p class="admin-private"><strong>Contacto privado:</strong> <?= h($submission['submitter_name']) ?> · <?= h($submission['email']) ?> · <?= h($submission['whatsapp']) ?></p>
                <p><strong>Permisos públicos:</strong> nombre <?= ($submission['public_name'] ?? false) === true ? h($submission['public_display_name'] ?? '') : 'privado' ?> · WhatsApp <?= !empty($submission['public_whatsapp']) ? 'autorizado' : 'privado' ?>.</p>
                <p><small>Reglas aceptadas: <?= h($submission['consents']['terms_version'] ?? 'envío anterior, sin versión registrada') ?> · <?= h($submission['consents']['accepted_at'] ?? '') ?></small></p>
                <details><summary>Ver ficha completa</summary><p><?= nl2br(h($submission['description'])) ?></p><dl class="facts compact"><div><dt>Edad</dt><dd><?= h($submission['age_group']) ?></dd></div><div><dt>Sexo</dt><dd><?= h($submission['sex']) ?></dd></div><div><dt>Tamaño</dt><dd><?= h($submission['size']) ?></dd></div><div><dt>Fotos</dt><dd><?= count($submission['photos'] ?? []) ?></dd></div></dl></details>
            </div>
            <div class="admin-card-actions">
                <a class="button button-small button-whatsapp" href="<?= h($submitterWhatsapp) ?>" target="_blank" rel="noopener noreferrer">Escribir por WhatsApp</a>
                <?php if ($submission['status'] === 'pending'): ?>
                <form method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="dataset" value="submissions"><input type="hidden" name="id" value="<?= h($submission['id']) ?>"><input type="hidden" name="action" value="approve"><button class="button button-small" type="submit">Aprobar y publicar</button></form>
                <form method="post" action="/admin/action" class="reject-form"><?= csrf_field() ?><input type="hidden" name="dataset" value="submissions"><input type="hidden" name="id" value="<?= h($submission['id']) ?>"><input type="hidden" name="action" value="reject"><input name="note" placeholder="Motivo interno"><button class="button button-small button-danger" type="submit">Rechazar</button></form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?></div><?php else: ?><div class="empty-state small"><p>No hay solicitudes todavía.</p></div><?php endif; ?>
    </div>
    <div class="admin-block"><div class="section-head"><div><span class="eyebrow">Publicación</span><h2>Fichas de perros</h2></div></div><?php if ($dogs): ?><div class="admin-list"><?php foreach ($dogs as $dog): ?><article class="admin-card"><div><span class="status-pill"><?= h($dog['status']) ?> / <?= h($dog['adoption_status']) ?></span><h3><?= h($dog['name']) ?></h3><p><?= h($dog['city']) ?> · vence <?= h(substr((string) $dog['expires_at'], 0, 10)) ?></p></div><div class="admin-card-actions wrap"><?php foreach (['available'=>'Disponible','reserved'=>'Reservado','adopted'=>'Adoptado','reunited'=>'Reencontrado','expired'=>'Vencido','unpublish'=>'Retirar'] as $action => $label): ?><form method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="dataset" value="dogs"><input type="hidden" name="id" value="<?= h($dog['id']) ?>"><input type="hidden" name="action" value="<?= h($action) ?>"><button class="button button-tiny <?= $action === 'unpublish' ? 'button-danger' : 'button-secondary' ?>" type="submit"><?= h($label) ?></button></form><?php endforeach; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state small"><p>No hay perros publicados.</p></div><?php endif; ?></div>
    <div class="admin-block"><div class="section-head"><div><span class="eyebrow">Comunidad</span><h2>Reportes</h2></div></div><?php if ($reports): ?><div class="admin-list"><?php foreach ($reports as $report): ?><article class="admin-card"><div><span class="status-pill"><?= h($report['status']) ?></span><h3><?= h($report['dog_name']) ?></h3><p><?= h($report['reason']) ?></p><small><?= h($report['contact']) ?></small></div><?php if ($report['status'] === 'open'): ?><form method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="dataset" value="reports"><input type="hidden" name="id" value="<?= h($report['id']) ?>"><input type="hidden" name="action" value="resolve"><button class="button button-small button-secondary" type="submit">Resolver</button></form><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="empty-state small"><p>No hay reportes.</p></div><?php endif; ?></div>
    <div class="admin-block"><div class="section-head"><div><span class="eyebrow">Seguridad</span><h2>Cambiar contraseña</h2></div></div><form class="password-form" method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="action" value="change_password"><label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nueva contraseña<input type="password" name="new_password" required minlength="14" autocomplete="new-password"></label><label>Repetir nueva contraseña<input type="password" name="confirm_password" required minlength="14" autocomplete="new-password"></label><button class="button button-small" type="submit">Guardar nueva contraseña</button></form></div>
    </div></section><?php render_footer(); exit;
}

render_error_page('Página no encontrada', 'La dirección que buscaste no existe o cambió.', 404);
