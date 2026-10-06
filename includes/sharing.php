<?php

declare(strict_types=1);

// Share only this small public allowlist; never serialize a listing wholesale.
function listing_share_fields(array $dog): array
{
    $clean = static function (mixed $value, int $limit): string {
        if (!is_string($value)) return '';
        $value = preg_replace('/[\p{C}\s]+/u', ' ', $value) ?? '';
        preg_match('/^.{0,' . $limit . '}/us', trim($value), $match);
        return $match[0] ?? '';
    };
    return [
        'name' => $clean($dog['name'] ?? '', 80),
        'city' => $clean($dog['city'] ?? '', 100),
        'type' => ['adoption'=>'Adopción', 'lost'=>'Perro perdido', 'found'=>'Perro encontrado'][$dog['listing_type'] ?? 'adoption'] ?? 'Aviso',
        'status' => ['available'=>(($dog['listing_type'] ?? 'adoption') === 'adoption' ? 'Disponible' : 'Aviso vigente'), 'reserved'=>'Reservado'][$dog['adoption_status'] ?? ''] ?? 'Consultar estado en la ficha',
        'slug' => is_string($dog['slug'] ?? null) && preg_match('/^[a-z0-9-]+$/D', $dog['slug']) ? $dog['slug'] : '',
    ];
}

function listing_share_code(array $dog): string
{
    $slug = listing_share_fields($dog)['slug'];
    return substr(strrchr($slug, '-') ?: $slug, str_contains($slug, '-') ? 1 : 0);
}

function listing_share_caption(array $dog): string
{
    $fields = listing_share_fields($dog);
    $caption = $fields['name'] . ' · ' . $fields['city'] . "\n" . $fields['type'] . ' · ' . $fields['status'];
    if (($dog['listing_type'] ?? 'adoption') === 'adoption') {
        $caption .= "\nLa adopción es gratuita. Si querés, compartí esta ficha para ayudar a encontrar una familia.";
    }
    return $caption . "\nConsultá la ficha y su estado actual: " . app_url('perro/' . $fields['slug']);
}

function listing_share_photo(array $dog): ?array
{
    $filename = $dog['photos'][0] ?? null;
    $folder = $dog['source_submission_id'] ?? $dog['id'] ?? null;
    if (!is_string($filename) || !is_string($folder)
        || !preg_match('/^[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$/Di', $filename)
        || !preg_match('/^[a-zA-Z0-9_-]+$/D', $folder)) return null;
    $root = realpath(PERRO_UPLOADS);
    $directory = realpath(PERRO_UPLOADS . '/' . $folder);
    $file = realpath(PERRO_UPLOADS . '/' . $folder . '/' . $filename);
    if ($root === false || $directory === false || $file === false || !is_file($file)
        || dirname($directory) !== $root || dirname($file) !== $directory) return null;
    $size = @filesize($file);
    if ($size === false || $size < 1 || $size > 16 * 1024 * 1024) return null;
    $details = @getimagesize($file);
    if (!$details || $details[0] < 1 || $details[1] < 1 || $details[0] * $details[1] > 8000000
        || !in_array($details['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) return null;
    return ['path'=>$file, 'width'=>$details[0], 'height'=>$details[1], 'mime'=>$details['mime']];
}

function listing_share_has_photo(array $dog): bool
{
    return listing_share_photo($dog) !== null;
}

function sharing_available(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromstring')
        && function_exists('imagejpeg') && function_exists('imagettftext') && function_exists('imagettfbbox')
        && is_readable(PERRO_ROOT . '/assets/fonts/AtkinsonHyperlegible-Regular.ttf')
        && is_readable(PERRO_ROOT . '/assets/fonts/AtkinsonHyperlegible-Bold.ttf');
}

function listing_share_image_url(array $dog, string $format = 'facebook'): string
{
    $fields = listing_share_fields($dog);
    $photo = listing_share_photo($dog);
    if ($fields['slug'] === '' || !$photo || !in_array($format, ['facebook', 'post', 'story'], true)) return '';
    $photoVersion = @hash_file('sha256', $photo['path']);
    if ($photoVersion === false) return '';
    // Private notes, contacts, submission IDs and timestamps cannot change this public revision.
    $revision = substr(hash('sha256', 'share-v1|' . perro_release_id() . '|' . json_encode($fields, JSON_UNESCAPED_UNICODE) . '|' . $photoVersion), 0, 16);
    return app_url('compartir/' . $fields['slug'] . '/' . $format . '.jpg') . '?v=' . $revision;
}

function sharing_text_width(string $text, int $size, string $font): int
{
    $box = @imagettfbbox($size, 0, $font, $text);
    return $box ? (int) (max($box[0], $box[2], $box[4], $box[6]) - min($box[0], $box[2], $box[4], $box[6])) : PHP_INT_MAX;
}

// Break long words by Unicode code points, so Spanish accents never become broken bytes.
function sharing_wrap(string $text, int $size, string $font, int $width): array
{
    $lines = []; $line = '';
    foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (sharing_text_width($candidate, $size, $font) <= $width) { $line = $candidate; continue; }
        if ($line !== '') { $lines[] = $line; $line = ''; }
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($line !== '' && sharing_text_width($line . $character, $size, $font) > $width) {
                $lines[] = $line; $line = '';
            }
            $line .= $character;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}

function sharing_draw_text(GdImage $canvas, string $text, int $x, int $y, int $width, int $height, int $size, string $font, int $color): void
{
    do {
        $lines = sharing_wrap($text, $size, $font, $width);
        $lineHeight = (int) ceil($size * 1.45);
        if (count($lines) * $lineHeight <= $height) break;
        $size--;
    } while ($size > 16);
    foreach ($lines as $index => $line) {
        if (($index + 1) * $lineHeight > $height) break;
        @imagettftext($canvas, $size, 0, $x, $y + $size + $index * $lineHeight, $color, $font, $line);
    }
}

function sharing_image(array $dog, array $photo, string $format): ?string
{
    [$width, $height] = ['facebook'=>[1200,630], 'post'=>[1080,1080], 'story'=>[1080,1920]][$format];
    $memoryLimit = function_exists('ini_bytes') ? ini_bytes((string) ini_get('memory_limit')) : -1;
    $estimate = memory_get_usage(true) + $photo['width'] * $photo['height'] * 8 + $width * $height * 8 + 16 * 1024 * 1024;
    if ($memoryLimit > 0 && $estimate > $memoryLimit) return null;
    $bytes = @file_get_contents($photo['path']);
    $source = $bytes !== false ? @imagecreatefromstring($bytes) : false;
    unset($bytes);
    if (!$source) return null;
    $canvas = @imagecreatetruecolor($width, $height);
    if (!$canvas) { imagedestroy($source); return null; }
    try {
        $navy = imagecolorallocate($canvas, 25, 61, 50);
        $cream = imagecolorallocate($canvas, 250, 249, 245);
        $coral = imagecolorallocate($canvas, 184, 68, 50);
        $green = imagecolorallocate($canvas, 32, 97, 69);
        $pale = imagecolorallocate($canvas, 230, 238, 231);
        imagefill($canvas, 0, 0, $cream);
        $regular = PERRO_ROOT . '/assets/fonts/AtkinsonHyperlegible-Regular.ttf';
        $bold = PERRO_ROOT . '/assets/fonts/AtkinsonHyperlegible-Bold.ttf';
        $fields = listing_share_fields($dog);
        if ($format === 'facebook') {
            $panel = [40, 40, 630, 550]; $textX = 710; $textY = 115; $textWidth = 450;
            $nameHeight = 180; $cityHeight = 90; $nameSize = 49;
        } else {
            $story = $format === 'story';
            $panel = [54, $story ? 180 : 54, 972, $story ? 1000 : 560];
            $textX = 60; $textY = $story ? 1260 : 690; $textWidth = 960;
            $nameHeight = $story ? 185 : 110; $cityHeight = $story ? 135 : 80; $nameSize = $story ? 68 : 56;
        }
        [$px,$py,$pw,$ph] = $panel;
        imagefilledrectangle($canvas, $px, $py, $px + $pw, $py + $ph, $pale);
        $scale = min($pw / imagesx($source), $ph / imagesy($source));
        $dw = max(1, (int) round(imagesx($source) * $scale));
        $dh = max(1, (int) round(imagesy($source) * $scale));
        imagecopyresampled($canvas, $source, $px + (int) (($pw - $dw) / 2), $py + (int) (($ph - $dh) / 2), 0, 0, $dw, $dh, imagesx($source), imagesy($source));
        sharing_draw_text($canvas, $fields['type'] . ' · ' . $fields['status'], $textX, $textY - 65, $textWidth, 60, 28, $bold, $green);
        sharing_draw_text($canvas, $fields['name'], $textX, $textY, $textWidth, $nameHeight, $nameSize, $bold, $navy);
        sharing_draw_text($canvas, $fields['city'], $textX, $textY + $nameHeight + 10, $textWidth, $cityHeight, 32, $regular, $navy);
        if ($format === 'facebook') {
            $brandX = $textX; $brandY = 465; $referenceY = 520;
        } else {
            $brandX = 60; $brandY = $format === 'story' ? 1700 : 922; $referenceY = $brandY + 55;
        }
        sharing_draw_text($canvas, 'perro.com.py', $brandX, $brandY, $textWidth, 55, 34, $bold, $coral);
        $suffix = listing_share_code($dog);
        sharing_draw_text($canvas, 'Ficha ' . $suffix . ' · Buscala en Perro.com.py', $brandX, $referenceY, $textWidth, 65, 20, $regular, $navy);
        ob_start();
        $ok = @imagejpeg($canvas, null, 88);
        $output = ob_get_clean();
        return $ok && is_string($output) ? $output : null;
    } finally {
        imagedestroy($source); imagedestroy($canvas);
    }
}

function sharing_routes(string $path): void
{
    if ($path !== 'compartir' && !str_starts_with($path, 'compartir/')) return;
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $error = static function (int $status, string $message) use ($method): never {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        if ($method !== 'HEAD') echo $message;
        exit;
    };
    if (!in_array($method, ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); $error(405, 'Método no permitido.'); }
    if (!preg_match('#^compartir/([a-z0-9-]+)/(facebook|post|story)\.jpg$#D', $path, $matches)) $error(404, 'Imagen no disponible.');
    $dog = public_dog_by_slug($matches[1]);
    $photo = $dog ? listing_share_photo($dog) : null;
    if (!$dog || !$photo) $error(404, 'Imagen no disponible.');
    if (!sharing_available()) $error(503, 'La imagen para compartir no está disponible. Podés compartir el enlace de la ficha.');
    $image = sharing_image($dog, $photo, $matches[2]);
    if ($image === null) $error(503, 'No pudimos preparar la imagen. Podés compartir el enlace de la ficha.');
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($image));
    if (($_GET['download'] ?? '') === '1') {
        header('Content-Disposition: attachment; filename="perro-' . substr($matches[1], 0, 100) . '-' . $matches[2] . '.jpg"');
    }
    if ($method !== 'HEAD') echo $image;
    exit;
}

function render_listing_sharing(array $dog): void
{
    $fields = listing_share_fields($dog);
    if ($fields['slug'] === '') return;
    $url = app_url('perro/' . $fields['slug']);
    $caption = listing_share_caption($dog);
    $images = sharing_available() && listing_share_has_photo($dog);
    $code = listing_share_code($dog);
    $searchPath = ($dog['listing_type'] ?? 'adoption') === 'adoption' ? 'perros' : 'perros-perdidos-paraguay';
    ?>
    <section class="section listing-sharing" id="compartir" aria-labelledby="sharing-title"><div class="shell narrow">
        <h2 id="sharing-title">Compartí esta ficha</h2>
        <p><?= ($dog['listing_type'] ?? 'adoption') === 'adoption' ? 'Compartir es voluntario y puede ayudar a encontrar una familia. La adopción es gratuita.' : 'Podés compartir el aviso para ayudar a difundirlo.' ?></p>
        <div class="button-row">
            <a class="button button-secondary" href="<?= h('https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url)) ?>" target="_blank" rel="noopener noreferrer">Compartir en Facebook</a>
            <a class="button button-whatsapp" href="<?= h('https://wa.me/?text=' . rawurlencode($caption)) ?>" target="_blank" rel="noopener noreferrer">Compartir por WhatsApp</a>
        </div>
        <p>Se abre un borrador: revisalo y elegí dónde publicarlo.</p>
        <label for="sharing-caption">Texto para acompañar la publicación</label>
        <textarea id="sharing-caption" class="share-caption" rows="6" readonly><?= h($caption) ?></textarea>
        <div class="button-row"><button class="button button-secondary" type="button" data-copy="<?= h($caption) ?>">Copiar texto</button><button class="button button-secondary" type="button" data-copy="<?= h($url) ?>">Copiar enlace</button><span class="copy-status" role="status" aria-live="polite"></span></div>
        <h3>Para Instagram</h3>
        <p>La imagen incluye el código público de ficha <strong><?= h($code) ?></strong>. Podés encontrarla en <a href="<?= h(app_url($searchPath) . '?q=' . rawurlencode($code)) ?>">el buscador de Perro</a>. Este código público es distinto de tu referencia privada de envío.</p>
        <?php if ($images): ?>
        <div class="button-row"><a class="button button-secondary" href="<?= h(listing_share_image_url($dog, 'post') . '&download=1') ?>" download>Descargar imagen para publicación</a><a class="button button-secondary" href="<?= h(listing_share_image_url($dog, 'story') . '&download=1') ?>" download>Descargar imagen para historia</a></div>
        <p>Descargá la imagen y subila manualmente en Instagram. Pegá el texto en la descripción; el enlace escrito allí puede no ser clicable. Para una historia, agregá un sticker de enlace con la URL directa de esta ficha.</p>
        <?php else: ?><p>No hay una imagen para descargar en este momento. Podés copiar el texto y el enlace. Si tenés una foto real con permiso para publicarla, subila manualmente y agregá el enlace directo de esta ficha; en historias, usá el sticker de enlace.</p><?php endif; ?>
        <p class="field-help">Las imágenes descargadas y las vistas previas de otras plataformas pueden no actualizarse cuando cambia el aviso. Revisá siempre el estado actual en <a href="<?= h($url) ?>">la ficha</a> antes de compartir.</p>
    </div></section>
    <?php
}
