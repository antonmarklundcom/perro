<?php

declare(strict_types=1);

function asset_url(string $path): string
{
    $digest = hash_file('sha256', PERRO_ROOT . '/' . $path);
    return '/' . $path . '?v=' . substr($digest, 0, 12);
}

function search_text(string $value): string
{
    return fold_accents($value);
}

function record_status_label(string $status): string
{
    return ['pending'=>'Pendiente', 'approved'=>'Aprobada', 'rejected'=>'Rechazada', 'published'=>'Publicada', 'removed'=>'Retirada', 'withdrawn'=>'Retirada', 'expired'=>'Vencida', 'available'=>'Disponible', 'reserved'=>'Reservado', 'adopted'=>'Adoptado', 'reunited'=>'Reencontrado', 'open'=>'Abierto', 'resolved'=>'Resuelto', 'active'=>'Activo', 'all'=>'Todas', 'adoption'=>'Adopción', 'lost'=>'Perdido', 'found'=>'Encontrado'][$status] ?? 'Sin estado informado';
}

function whatsapp_visibility_label(bool $public): string
{
    return $public ? 'Autorizó mostrar su WhatsApp en la ficha pública' : 'WhatsApp privado';
}

function page_meta(string $title, string $description, string $path = '', bool $indexable = true, ?string $image = null): array
{
    $robots = $indexable ? 'index,follow' : 'noindex,nofollow';
    return [
        'title' => $title,
        'description' => $description,
        'canonical' => app_url($path),
        'robots' => $robots,
        'image' => $image ?: app_url('assets/images/default-preview.jpg'),
        'image_width' => $image ? null : 1200,
        'image_height' => $image ? null : 630,
        'image_type' => $image ? null : 'image/jpeg',
    ];
}

function project_whatsapp_message(): string
{
    if (!empty($GLOBALS['perro_storage_error'])) return 'Hola, necesito ayuda con una operación en Perro.com.py.';
    $path = request_path();
    if (preg_match('#^perro/([a-z0-9-]+)$#', $path, $matches) && function_exists('public_dog_by_slug')) {
        $dog = public_dog_by_slug($matches[1]);
        if ($dog) {
            $type = listing_options()['listing_type'][$dog['listing_type'] ?? 'adoption'] ?? 'Aviso';
            return enquiry_message($dog);
        }
    }

    return match ($path) {
        '', 'perros' => 'Hola, vi Perro.com.py y quisiera consultar sobre perros en adopción en Paraguay.',
        'cachorros-en-adopcion' => 'Hola, vi la página de cachorros en adopción de Perro.com.py y quisiera hacer una consulta.',
        'perros-de-raza-en-adopcion' => 'Hola, vi la página de perros de raza en adopción de Perro.com.py y quisiera hacer una consulta.',
        'dar-perro-en-adopcion' => 'Hola, vi Perro.com.py y necesito ayuda para publicar responsablemente un aviso sobre un perro.',
        'perros-perdidos-paraguay' => 'Hola, vi la página de perros perdidos de Perro.com.py y quisiera consultar por un aviso.',
        'centros-de-adopcion' => 'Hola, vi el directorio de Perro.com.py y quisiera consultar sobre una organización o grupo de rescate.',
        'seguridad' => 'Hola, leí la guía de adopción segura de Perro.com.py y quisiera hacer una consulta.',
        'como-funciona' => 'Hola, vi cómo funciona Perro.com.py y quisiera hacer una consulta.',
        'privacidad' => 'Hola, quisiera solicitar información, corrección o retiro de datos publicados en Perro.com.py.',
        'terminos' => 'Hola, leí los términos de Perro.com.py y quisiera hacer una consulta.',
        default => 'Hola, vi Perro.com.py y quisiera hacer una consulta sobre adopción responsable.',
    };
}

function project_whatsapp_url(?string $message = null): string
{
    global $config;
    $number = preg_replace('/\D+/', '', (string) ($config['contact_whatsapp'] ?? '')) ?: '';
    if ($number === '') {
        return '';
    }
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message ?: project_whatsapp_message());
}

function render_header(array $meta): void
{
    global $config;
    $flash = take_flash();
    $GLOBALS['perro_flash'] = $flash;
    if (session_status() !== PHP_SESSION_ACTIVE && $meta['robots'] === 'index,follow') header('Cache-Control: public, max-age=60, must-revalidate');
    $whatsappUrl = project_whatsapp_url();
    $isAdminPage = request_path() === 'admin' || request_path() === 'activar-admin' || str_starts_with(request_path(), 'admin/');
    ?><!doctype html>
<html lang="es-PY">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($meta['title']) ?></title>
    <meta name="description" content="<?= h($meta['description']) ?>">
    <meta name="robots" content="<?= h($meta['robots']) ?>">
    <link rel="canonical" href="<?= h($meta['canonical']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_PY">
    <meta property="og:title" content="<?= h($meta['title']) ?>">
    <meta property="og:description" content="<?= h($meta['description']) ?>">
    <meta property="og:url" content="<?= h($meta['canonical']) ?>">
    <meta property="og:site_name" content="Perro.com.py">
    <?php if (!empty($meta['image'])): ?>
    <meta property="og:image" content="<?= h($meta['image']) ?>">
    <?php if (!empty($meta['image_width'])): ?><meta property="og:image:width" content="<?= (int) $meta['image_width'] ?>"><meta property="og:image:height" content="<?= (int) $meta['image_height'] ?>"><?php endif; ?>
    <?php if (!empty($meta['image_type'])): ?><meta property="og:image:type" content="<?= h($meta['image_type']) ?>"><?php endif; ?>
    <meta property="og:image:alt" content="<?= h($meta['image_alt'] ?? 'Perro: adopción responsable en Paraguay') ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($meta['title']) ?>">
    <meta name="twitter:description" content="<?= h($meta['description']) ?>">
    <?php if (!empty($meta['image'])): ?><meta name="twitter:image" content="<?= h($meta['image']) ?>"><meta name="twitter:image:alt" content="<?= h($meta['image_alt'] ?? 'Perro: adopción responsable en Paraguay') ?>"><?php endif; ?>
    <?php render_public_schema($meta); ?>
    <meta name="theme-color" content="#193d32">
    <meta name="perro-release" content="<?= h(perro_release_id()) ?>">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/site.css')) ?>">
</head>
<body class="<?= $isAdminPage ? 'admin-page' : 'public-page' ?>">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="/" aria-label="Perro, inicio">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span>Perro<span class="brand-dot">.</span></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav"><span></span><span></span><span></span><span class="sr-only">Abrir menú</span></button>
        <nav id="site-nav" class="site-nav" aria-label="Navegación principal">
            <?php if ($isAdminPage): ?>
            <a href="/admin">Panel</a><a href="/" target="_blank" rel="noopener noreferrer">Ver sitio ↗</a>
            <?php else: ?>
            <a href="/perros"<?= in_array(request_path(), ['perros', 'cachorros-en-adopcion', 'perros-de-raza-en-adopcion'], true) ? ' aria-current="page"' : '' ?>>Adoptá</a>
            <a href="/perros-perdidos-paraguay"<?= request_path() === 'perros-perdidos-paraguay' ? ' aria-current="page"' : '' ?>>Perdidos y encontrados</a>
            <a href="/como-funciona"<?= request_path() === 'como-funciona' ? ' aria-current="page"' : '' ?>>Cómo funciona</a>
            <?php if (!$isAdminPage && $whatsappUrl): ?><a class="nav-whatsapp" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
            <a class="button button-small button-coral" href="/dar-perro-en-adopcion">Publicá un aviso</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($flash): ?><div class="shell flash flash-<?= h($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= h($flash['message']) ?></div><?php endif; ?>
<main id="contenido">
<?php
}

function render_footer(): void
{
    global $config;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $whatsappUrl = project_whatsapp_url();
    $isAdminPage = request_path() === 'admin' || request_path() === 'activar-admin' || str_starts_with(request_path(), 'admin/');
    ?>
</main>
<footer class="site-footer">
    <div class="shell footer-grid">
        <div>
            <a class="brand brand-footer" href="/"><span class="brand-mark" aria-hidden="true">P</span><span>Perro<span class="brand-dot">.</span></span></a>
            <p>Una plataforma independiente de difusión para adopciones responsables de perros en Paraguay.</p>
        </div>
        <div>
            <h2>Explorá</h2>
            <a href="/perros">Perros en adopción</a>
            <a href="/cachorros-en-adopcion">Cachorros en adopción</a>
            <a href="/perros-perdidos-paraguay">Perdidos y encontrados</a>
            <a href="/dar-perro-en-adopcion">Dar un perro en adopción</a>
            <a href="/centros-de-adopcion">Centros y organizaciones</a>
        </div>
        <div>
            <h2>Información</h2>
            <a href="/como-funciona">Cómo publicar y actualizar un aviso</a>
            <a href="/seguridad">Adopción segura</a>
            <a href="/terminos">Términos</a>
            <a href="/privacidad">Privacidad</a>
            <?php if ($whatsappUrl): ?><a href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp: +<?= h($config['contact_whatsapp']) ?></a><?php endif; ?>
            <a href="/admin">Administración</a>
        </div>
    </div>
    <div class="shell footer-bottom">
        <p>Perro es una plataforma independiente de difusión. No somos un refugio ni tenemos custodia de los animales publicados. Verificá la información y conocé al perro de forma segura antes de adoptar.</p>
        <p>© <?= date('Y') ?> Perro · Hecho con cariño en Paraguay.</p>
    </div>
</footer>
<?php if (!$isAdminPage && request_path() !== 'dar-perro-en-adopcion' && !str_starts_with(request_path(), 'perro/') && $whatsappUrl): ?>
<a class="whatsapp-float" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Escribinos por WhatsApp al +<?= h($config['contact_whatsapp']) ?>">
    <span class="whatsapp-float-icon" aria-hidden="true">WA</span>
    <strong>WhatsApp</strong>
</a>
<?php endif; ?>
<script src="<?= h(asset_url('assets/js/site.js')) ?>" defer></script>
</body>
</html><?php
}

function render_error_page(string $title, string $message, int $status = 400): void
{
    http_response_code($status);
    render_header(page_meta($title . ' | Perro', $message, request_path(), false));
    ?><section class="section"><div class="shell narrow empty-state"><span class="eyebrow">Algo no salió como esperábamos</span><h1><?= h($title) ?></h1><p><?= h($message) ?></p><a class="button" href="/">Volver al inicio</a></div></section><?php
    render_footer();
}

function dog_card(array $dog): void
{
    static $renderedImages = 0;
    $photo = listing_share_photo($dog) ? ($dog['photos'][0] ?? '') : '';
    $first = $photo !== '' && $renderedImages++ === 0;
    $statusLabel = match ($dog['adoption_status'] ?? 'available') {
        'reserved' => 'Reservado',
        'adopted' => 'Adoptado',
        'reunited' => 'Reencontrado',
        default => (($dog['listing_type'] ?? 'adoption') === 'lost' ? 'Perdido' : (($dog['listing_type'] ?? '') === 'found' ? 'Encontrado' : 'En adopción')),
    };
    ?>
<article class="dog-card">
    <a class="dog-card-link" href="/perro/<?= h($dog['slug']) ?>">
    <div class="dog-photo">
        <?php if ($photo): ?><img src="/media/<?= h($dog['id']) ?>/<?= h($photo) ?>?w=480" srcset="/media/<?= h($dog['id']) ?>/<?= h($photo) ?>?w=480 480w, /media/<?= h($dog['id']) ?>/<?= h($photo) ?>?w=960 960w" sizes="(max-width: 760px) 90vw, 30vw" alt="Foto de <?= h($dog['name']) ?>" <?= $first ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="800" height="600"><?php else: ?><span class="photo-placeholder">Sin foto disponible</span><?php endif; ?>
        <span class="status-tag"><?= h($statusLabel) ?></span>
    </div>
    <div class="dog-body">
        <div class="dog-heading"><h2><?= h($dog['name']) ?></h2><span><?= h($dog['city']) ?></span></div>
        <p class="dog-meta"><?= h($dog['age_group']) ?> · <?= h($dog['sex']) ?> · <?= h($dog['size']) ?></p>
        <p><?= h($dog['breed_label'] ?: (!empty($dog['mixed_breed']) ? 'Mestizo o raza aproximada' : 'Raza no informada')) ?></p>
        <?php if ($dog['listing_type'] !== 'adoption'): ?><p><?= h(format_date($dog['incident_date'])) ?> · <?= h($dog['last_location']) ?></p><?php endif; ?>
        <span class="text-link">Conocé su historia <span aria-hidden="true">→</span></span>
    </div>
    </a>
</article><?php
}

function select_options(array $options, string $selected = ''): void
{
    foreach ($options as $value => $label) {
        ?><option value="<?= h((string) $value) ?>"<?= (string) $value === $selected ? ' selected' : '' ?>><?= h((string) $label) ?></option><?php
    }
}
