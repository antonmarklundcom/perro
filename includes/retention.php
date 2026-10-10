<?php

declare(strict_types=1);

function retention_save_button(array $dog): void
{
    ?><button type="button" class="button button-secondary saved-toggle" data-save-dog="<?= h($dog['slug']) ?>" aria-pressed="false" hidden>Guardar aviso</button><?php
}

// Return an explicit allowlist, never a stored record or a private status reason.
function retention_public_states(array $slugs): array
{
    $visible = array_column(public_dogs(), null, 'slug');
    $states = [];
    foreach ($slugs as $slug) {
        $dog = $visible[$slug] ?? null;
        $states[] = $dog ? ['slug'=>$slug, 'available'=>true, 'name'=>$dog['name'], 'city'=>$dog['city'], 'type'=>$dog['listing_type'], 'status'=>($dog['adoption_status'] ?? '') === 'reserved' ? 'reserved' : 'available'] : ['slug'=>$slug, 'available'=>false];
    }
    return $states;
}

function retention_xml(string $value): string
{
    // XML 1.0 excludes control characters that HTML escaping alone preserves.
    $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function retention_rss(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel><title>Perro.com.py — avisos activos</title><link>' . retention_xml(app_url('perros')) . '</link><description>Adopción gratuita y avisos de perros perdidos o encontrados en Paraguay. Consultá siempre el estado actual en la ficha.</description><language>es-PY</language><atom:link href="' . retention_xml(app_url('avisos.rss')) . '" rel="self" type="application/rss+xml"/>';
    foreach (array_slice(public_dogs(), 0, 100) as $dog) {
        $url = retention_xml(app_url('perro/' . $dog['slug']));
        $type = ['adoption'=>'Adopción', 'lost'=>'Perdido', 'found'=>'Encontrado'][$dog['listing_type']] ?? 'Aviso';
        $xml .= '<item><title>' . retention_xml($dog['name'] . ' — ' . $type) . '</title><link>' . $url . '</link><guid isPermaLink="true">' . $url . '</guid><description>' . retention_xml($type . ' · ' . $dog['city'] . '. Consultá el estado actual en la ficha de Perro.com.py.') . '</description>';
        $published = strtotime((string) ($dog['published_at'] ?? ''));
        if ($published !== false && $published > 0) $xml .= '<pubDate>' . gmdate(DATE_RSS, $published) . '</pubDate>';
        $xml .= '</item>';
    }
    return $xml . '</channel></rss>';
}

function retention_routes(string $path): void
{
    if (!in_array($path, ['guardados', 'guardados/estado', 'avisos.rss'], true)) return;
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        http_response_code(405); header('Allow: GET, HEAD'); exit;
    }
    if ($path === 'guardados/estado') {
        header('Content-Type: application/json; charset=utf-8');
        $raw = $_GET['slugs'] ?? '';
        if (!is_string($raw) || strlen($raw) > 13000) { http_response_code(400); echo '{"error":"invalid_slugs"}'; exit; }
        $slugs = $raw === '' ? [] : array_values(array_unique(explode(',', $raw)));
        if (count($slugs) > 100 || array_filter($slugs, static fn(string $slug): bool => preg_match('/^[a-z0-9-]{1,512}$/D', $slug) !== 1)) { http_response_code(400); echo '{"error":"invalid_slugs"}'; exit; }
        $json = json_encode(['items'=>retention_public_states($slugs)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') echo $json;
        exit;
    }
    if ($path === 'avisos.rss') {
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=0, must-revalidate');
        $xml = retention_rss(); $etag = '"' . hash('sha256', $xml) . '"';
        header('ETag: ' . $etag);
        $validators = array_map('trim', explode(',', (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')));
        if (in_array($etag, $validators, true) || in_array('W/' . $etag, $validators, true) || in_array('*', $validators, true)) { http_response_code(304); exit; }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') echo $xml;
        exit;
    }
    render_header(page_meta('Avisos guardados en este navegador | Perro.com.py', 'Volvé a consultar tus avisos guardados y su disponibilidad actual. Sin crear una cuenta.', 'guardados', false));
    ?><section class="section"><div class="shell narrow"><h1>Avisos guardados</h1><p>Guardás hasta 100 referencias en este navegador, sin cuenta. No guardamos fotos ni contactos. Si usás un equipo compartido, vaciá la lista al terminar.</p><p>Guardar un aviso no reserva un perro. La adopción sigue siendo gratuita.</p><noscript><p>Activá JavaScript para ver los guardados de este navegador. Podés consultar los <a href="/perros">avisos activos</a>.</p></noscript><div data-saved-dogs><p role="status" data-saved-status>Cargando tus guardados…</p><button type="button" class="button button-secondary" data-saved-clear hidden>Vaciar guardados</button><ul class="saved-list" data-saved-list></ul></div><p><a href="/perros">Ver avisos activos</a> · <a href="/avisos.rss">Seguir los avisos por RSS</a></p><p>Un lector RSS puede conservar copias de avisos anteriores. Revisá la ficha para confirmar su estado actual.</p></div></section><?php
    render_footer(); exit;
}
