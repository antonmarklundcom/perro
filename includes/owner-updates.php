<?php

declare(strict_types=1);

function owner_link_eligible(array $dog): bool
{
    return ($dog['status'] ?? '') === 'published' && ($dog['adoption_status'] ?? '') === 'available'
        && empty($dog['needs_repair']) && strtotime((string) ($dog['expires_at'] ?? '')) > time();
}

function owner_link_event(string $action, string $id, string $actor): array
{
    return ['id'=>random_id('mod-'), 'action'=>$action, 'record_id'=>$id, 'note'=>'', 'admin'=>$actor, 'created_at'=>now_iso()];
}

function create_owner_action_link(string $dogId, string $scope, string $verifiedContact, bool $verified): array
{
    return with_data_lock(static function () use ($dogId, $scope, $verifiedContact, $verified): array {
        if (!admin_has_capability('owner_links')) return ['error', 'Tu cuenta no tiene permiso para esta acción.'];
        $dog = find_record('dogs', $dogId);
        $source = $dog ? find_record('submissions', (string) ($dog['source_submission_id'] ?? '')) : null;
        // Contact must match the authoritative private submission, never the public alias/contact.
        $phone = valid_whatsapp($verifiedContact);
        $email = strtolower(trim($verifiedContact));
        $match = $source && ($source['status'] ?? '') === 'approved' && ($source['published_dog_id'] ?? '') === $dogId && (($phone !== '' && hash_equals(valid_whatsapp((string) ($source['whatsapp'] ?? '')), $phone))
            || (filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(strtolower(trim((string) ($source['email'] ?? ''))), $email)));
        if (!$verified || !$match) return ['error', 'Verificá el contacto del responsable registrado antes de crear el enlace.'];
        if (!$dog || !owner_link_eligible($dog) || !in_array($scope, ['confirm', 'withdraw'], true)) return ['error', 'Solo se puede crear un enlace para un aviso publicado, vigente y disponible.'];
        $rows = read_dataset('owner_actions');
        $recent = array_filter($rows, static fn(array $r): bool => ($r['dog_id'] ?? '') === $dogId && strtotime((string) ($r['created_at'] ?? '')) > time()-3600);
        if (count($recent) >= 5) return ['error', 'Ya se crearon varios enlaces para esta ficha. Intentá más tarde.'];
        // New issuance revokes older unused links to avoid conflicting owner requests.
        foreach ($rows as &$row) if (($row['dog_id'] ?? '') === $dogId && empty($row['used_at'])) $row['revoked_at'] = now_iso();
        unset($row);
        $token = bin2hex(random_bytes(32));
        $rows[] = ['id'=>random_id('owner-'), 'token_hash'=>hash('sha256', $token), 'dog_id'=>$dogId, 'scope'=>$scope,
            'source_submission_id'=>$source['id'], 'source_revision'=>record_revision($source),
            'expires_at'=>time()+86400, 'used_at'=>null, 'created_at'=>now_iso(), 'created_by'=>current_admin_account_id()];
        $events = read_dataset('moderation');
        $events[] = owner_link_event('owner_link_created', $dogId, current_admin_account_id());
        if (!commit_datasets(['owner_actions'=>$rows, 'moderation'=>$events])) return ['error', 'No pudimos guardar el enlace.'];
        $url = app_url('actualizar-aviso?token=' . $token);
        $draft = 'Hola. Después de verificar tu contacto, te compartimos este enlace privado de Perro para ' . ($scope === 'confirm' ? 'confirmar que tu aviso sigue vigente y disponible' : 'solicitar el retiro de tu aviso') . ': ' . $url . '. Vence en 24 horas y funciona una sola vez. Si no pediste esto, no lo uses.';
        return ['success', 'Enlace creado. Revisá el borrador y compartilo manualmente con el responsable verificado.', $url, $draft];
    });
}

function find_owner_action(string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) return null;
    return owner_action_from_digest(hash('sha256', $token));
}

// Digests come only from the server session, never a client-supplied digest parameter.
function owner_action_from_digest(string $digest): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) return null;
    foreach (read_dataset('owner_actions') as $row) {
        if (!empty($row['used_at']) || !empty($row['revoked_at']) || ($row['expires_at'] ?? 0) <= time()
            || !in_array($row['scope'] ?? '', ['confirm', 'withdraw'], true)
            || !is_string($row['token_hash'] ?? null) || !hash_equals($row['token_hash'], $digest)) continue;
        $dog = find_record('dogs', (string) ($row['dog_id'] ?? ''));
        $source = find_record('submissions', (string) ($row['source_submission_id'] ?? ''));
        return $dog && owner_link_eligible($dog) && ($dog['source_submission_id'] ?? '') === ($row['source_submission_id'] ?? '')
            && $source && ($source['status'] ?? '') === 'approved' && ($source['published_dog_id'] ?? '') === $dog['id'] && is_string($row['source_revision'] ?? null)
            && hash_equals($row['source_revision'], record_revision($source)) ? $row : null;
    }
    return null;
}

function owner_action_rate_allowed(): bool
{
    return with_data_lock(static function (): bool {
        // No raw address, token or private contact is retained in the rate bucket.
        $key = 'owner-rate-' . hash_hmac('sha256', rate_address(), admin_credential_version('admin'));
        $rows = read_dataset('security');
        $bucket = null;
        foreach ($rows as $r) if ($r['id'] === $key && ($r['started'] ?? 0) > time()-900) $bucket = $r;
        $bucket ??= ['id'=>$key, 'started'=>time(), 'count'=>0, 'kind'=>'owner_action_rate'];
        if ($bucket['count'] >= 30) return false;
        $bucket['count']++;
        $rows = array_values(array_filter($rows, static fn(array $r): bool => $r['id'] !== $key && (($r['kind'] ?? '') !== 'owner_action_rate' || ($r['started'] ?? 0) > time()-900)));
        $rows[] = $bucket;
        if (!commit_datasets(['security'=>$rows])) throw new RuntimeException('No se pudo guardar el control de acceso.');
        return true;
    });
}

function apply_owner_action(string $token, string $scope): array
{
    return apply_owner_action_digest(preg_match('/^[a-f0-9]{64}$/D', $token) === 1 ? hash('sha256', $token) : '', $scope);
}

function apply_owner_action_digest(string $digest, string $scope): array
{
    global $config;
    return with_data_lock(static function () use ($digest, $scope, $config): array {
        $invalid = ['error', 'Este enlace no está disponible. Pedile al equipo un enlace nuevo.'];
        $action = owner_action_from_digest($digest);
        if (!$action || !hash_equals($action['scope'], $scope)) return $invalid;
        $dogs = read_dataset('dogs');
        foreach ($dogs as &$dog) if ($dog['id'] === $action['dog_id']) {
            if (!owner_link_eligible($dog)) return $invalid;
            if ($scope === 'confirm') {
                $dog['last_confirmed_at'] = now_iso();
                $dog['expires_at'] = date(DATE_ATOM, time()+max(1, min(365, (int) $config['listing_expiry_days']))*86400);
            } else {
                $dog['status'] = 'removed';
                $dog['status_changed_at'] = now_iso();
            }
            $dog['updated_at'] = now_iso();
        }
        unset($dog);
        $rows = read_dataset('owner_actions');
        foreach ($rows as &$row) if ($row['id'] === $action['id']) $row['used_at'] = now_iso();
        unset($row);
        $events = read_dataset('moderation');
        $events[] = owner_link_event($scope === 'confirm' ? 'owner_confirmed' : 'owner_withdrawal_requested', $action['dog_id'], 'owner-link');
        if (!commit_datasets(['dogs'=>$dogs, 'owner_actions'=>$rows, 'moderation'=>$events])) return ['error', 'No pudimos guardar el cambio. Intentá nuevamente.'];
        return ['success', $scope === 'confirm' ? 'Confirmación guardada. El aviso sigue vigente.' : 'Solicitud guardada. El aviso ya dejó de mostrarse públicamente.'];
    });
}

function owner_update_routes(string $path): void
{
    if ($path === 'admin/owner-link') {
        require_admin_capability('owner_links');
        if (!method_is_post()) { http_response_code(405); header('Allow: POST'); exit; }
        require_csrf();
        $result = create_owner_action_link(text('id', 100), text('scope', 20), text('verified_contact', 180), checked('contact_verified'));
        render_header(page_meta('Enlace privado del responsable | Perro', 'Borrador manual privado.', 'admin/owner-link', false));
        ?><section class="section"><div class="shell narrow"><h1><?= $result[0] === 'success' ? 'Enlace creado' : 'No se creó el enlace' ?></h1><p><?= h($result[1]) ?></p><?php
        if (isset($result[2])) render_owner_link_draft(['text'=>$result[3]]);
        ?><a class="button" href="/admin?section=confirmar">Volver al panel</a></div></section><?php
        render_footer(); exit;
    }
    if ($path !== 'actualizar-aviso') return;
    start_perro_session();
    header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
    if (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
    if (!owner_action_rate_allowed()) { render_error_page('Intentá más tarde', 'Esperá unos minutos antes de intentar nuevamente.', 429); exit; }
    if (!method_is_post() && isset($_GET['token'])) {
        $token = is_string($_GET['token']) ? $_GET['token'] : '';
        unset($_SESSION['owner_action_digest']);
        if (!find_owner_action($token)) { render_error_page('Enlace no disponible', 'Este enlace no está disponible. Pedile al equipo un enlace nuevo.', 404); exit; }
        $_SESSION['owner_action_digest'] = hash('sha256', $token);
        redirect('actualizar-aviso');
    }
    $digest = is_string($_SESSION['owner_action_digest'] ?? null) ? $_SESSION['owner_action_digest'] : '';
    if (method_is_post()) {
        require_csrf();
        $result = apply_owner_action_digest($digest, text('scope', 20));
        unset($_SESSION['owner_action_digest']);
        if ($result[0] !== 'success') http_response_code(404);
        render_header(page_meta('Actualizar aviso | Perro', 'Actualización privada del aviso.', 'actualizar-aviso', false));
        ?><section class="section"><div class="shell narrow"><h1><?= $result[0] === 'success' ? 'Cambio guardado' : 'Enlace no disponible' ?></h1><p><?= h($result[1]) ?></p><a href="/" class="button">Volver a Perro</a></div></section><?php
        render_footer(); exit;
    }
    $action = owner_action_from_digest($digest);
    if (!$action) { render_error_page('Enlace no disponible', 'Este enlace no está disponible. Pedile al equipo un enlace nuevo.', 404); exit; }
    render_header(page_meta('Actualizar aviso | Perro', 'Actualización privada del aviso.', 'actualizar-aviso', false));
    ?><section class="section"><div class="shell narrow"><h1><?= $action['scope'] === 'confirm' ? 'Confirmar vigencia del aviso' : 'Solicitar retiro del aviso' ?></h1><p><?= $action['scope'] === 'confirm' ? 'Confirmás que el aviso publicado sigue vigente y disponible. Esto renueva su fecha de vencimiento.' : 'Al enviar esta solicitud, el aviso deja de mostrarse públicamente de inmediato y el equipo conserva su historial de revisión.' ?></p><p>Este enlace funciona una sola vez. No cambia datos, fotos ni permisos de contacto.</p><form method="post" action="/actualizar-aviso"><?= csrf_field() ?><input type="hidden" name="scope" value="<?= h($action['scope']) ?>"><button type="submit" class="button"><?= $action['scope'] === 'confirm' ? 'Confirmar que sigue disponible' : 'Solicitar retiro ahora' ?></button></form></div></section><?php
    render_footer(); exit;
}

function render_owner_link_draft(?array $draft = null): void
{
    if (!is_array($draft) || !admin_has_capability('owner_links')) return;
    ?><div class="notice"><h2>Borrador privado para el responsable</h2><label>Mensaje para copiar<textarea readonly autocomplete="off"><?= h($draft['text']) ?></textarea></label><p>Compartilo manualmente solo con el responsable cuyo contacto verificaste. Vence en 24 horas. No se envió ningún mensaje automáticamente.</p></div><?php
}

function render_owner_link_form(array $dog): void
{
    if (!admin_has_capability('owner_links') || !owner_link_eligible($dog)) return;
    ?><details><summary>Crear enlace privado para el responsable</summary><form method="post" action="/admin/owner-link"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($dog['id']) ?>"><label>Acción permitida<select name="scope"><option value="confirm">Confirmar que sigue vigente y disponible</option><option value="withdraw">Solicitar retiro inmediato</option></select></label><label>Contacto verificado del envío original<input name="verified_contact" required maxlength="180" autocomplete="off"><small>Ingresá el WhatsApp o correo registrado, después de verificar a la persona por ese canal.</small></label><label class="check"><input type="checkbox" name="contact_verified" value="1" required> Verifiqué que esta solicitud viene del responsable registrado.</label><button class="button button-small" type="submit">Crear enlace y borrador manual</button></form></details><?php
}
