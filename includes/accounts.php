<?php

declare(strict_types=1);

function admin_accounts(): array
{
    return array_values(array_filter(read_dataset('settings'), static fn(array $row): bool => str_starts_with($row['id'], 'admin-user-')));
}

function persist_admin_account(array $account, string $action): void
{
    $settings = read_dataset('settings');
    $replaced = false;
    foreach ($settings as &$row) {
        if ($row['id'] === $account['id']) { $row = $account; $replaced = true; break; }
    }
    unset($row);
    if (!$replaced) $settings[] = $account;
    $events = read_dataset('moderation');
    $events[] = ['id'=>random_id('mod-'), 'action'=>$action, 'record_id'=>$account['id'], 'note'=>'', 'admin'=>(($_SESSION['perro_admin'] ?? false) === true ? current_admin_account_id() : 'system'), 'created_at'=>now_iso()];
    if (!commit_datasets(['settings'=>$settings, 'moderation'=>$events])) throw new RuntimeException('No se pudo guardar la cuenta.');
}

function invite_admin_account(string $email, string $name): array
{
    return with_data_lock(static function () use ($email, $name): array {
        if (!is_admin() || current_admin_account_id() !== 'admin') return ['error', 'Solo la cuenta principal puede administrar accesos.'];
        $email = strtolower(trim($email));
        if (strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') return ['error', 'Completá un correo válido y un nombre.'];
        $id = 'admin-user-' . hash('sha256', $email);
        $existing = find_record('settings', $id);
        if (!empty($existing['active']) && empty($existing['disabled'])) return ['error', 'Este correo ya tiene una cuenta activa.'];
        $token = bin2hex(random_bytes(32));
        $record = ['id'=>$id, 'email'=>$email, 'name'=>$name, 'active'=>false, 'disabled'=>false, 'invite_hash'=>hash('sha256', $token), 'invite_expires_at'=>time()+86400, 'created_at'=>$existing['created_at'] ?? now_iso(), 'updated_at'=>now_iso()];
        persist_admin_account($record, 'admin_account_invited');
        return ['success', 'Invitación creada. Compartí el enlace únicamente con la persona invitada.', app_url('activar-admin?token=' . $token)];
    });
}

function invited_admin_account(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
    $digest = hash('sha256', $token);
    foreach (admin_accounts() as $account) {
        if (empty($account['active']) && empty($account['disabled']) && ($account['invite_expires_at'] ?? 0) > time()
            && is_string($account['invite_hash'] ?? null) && hash_equals($account['invite_hash'], $digest)) return $account;
    }
    return null;
}

function admin_password_error(string $password, string $confirm): string
{
    if (preg_match('/^.{14,}$/us', $password) !== 1) return 'La contraseña debe tener al menos 14 caracteres.';
    if (preg_match('/[\x00-\x1f\x7f]/', $password)) return 'Evitá caracteres de control en la contraseña.';
    // PHP's current default bcrypt must not silently truncate longer inputs.
    if (strlen($password) > 72) return 'La contraseña es demasiado larga. Usá una frase más corta.';
    return hash_equals($password, $confirm) ? '' : 'La confirmación no coincide con la contraseña.';
}

function activate_admin_account(string $token, string $password, string $confirm): array
{
    return with_data_lock(static function () use ($token, $password, $confirm): array {
        $account = invited_admin_account($token);
        if (!$account) return ['error', 'El enlace venció o ya se usó. Pedile uno nuevo a la cuenta principal.'];
        if ($error = admin_password_error($password, $confirm)) return ['error', $error];
        $account['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $account['active'] = true;
        $account['updated_at'] = now_iso();
        unset($account['invite_hash'], $account['invite_expires_at']);
        persist_admin_account($account, 'admin_account_activated');
        return ['success', 'Cuenta activada. Ingresá con tu correo y la contraseña que elegiste.'];
    });
}

function change_admin_password(string $current, string $new, string $confirm): array
{
    return with_data_lock(static function () use ($current, $new, $confirm): array {
        if (!is_admin() || !verify_admin_password($current)) return ['error', 'La contraseña actual no coincide o la sesión venció.'];
        if ($error = admin_password_error($new, $confirm)) return ['error', $error];
        $id = current_admin_account_id();
        $account = find_record('settings', $id) ?? ['id'=>'admin'];
        $account['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        $account['updated_at'] = now_iso();
        persist_admin_account($account, 'password_changed');
        $_SESSION['admin_version'] = admin_credential_version();
        return ['success', 'Contraseña actualizada. Guardala en un lugar seguro.'];
    });
}

function admin_account_routes(string $path): void
{
    if ($path === 'admin/accounts') {
        require_admin();
        if (current_admin_account_id() !== 'admin') { render_error_page('Acceso restringido', 'Solo la cuenta principal puede crear o retirar accesos.', 403); exit; }
        if (method_is_post()) {
            require_csrf();
            if (text('action', 30) === 'invite') {
                $result = invite_admin_account(text('email', 180), text('name', 80));
                if (isset($result[2])) $_SESSION['admin_invite_link'] = $result[2];
                set_flash($result[0], $result[1]);
            } elseif (text('action', 30) === 'disable') {
                $result = with_data_lock(static function (): array {
                    if (!is_admin() || current_admin_account_id() !== 'admin') return ['error', 'La sesión venció.'];
                    $id = text('id', 100);
                    $account = str_starts_with($id, 'admin-user-') ? find_record('settings', $id) : null;
                    if (!$account) return ['error', 'La cuenta no existe.'];
                    $account['disabled'] = true;
                    $account['active'] = false;
                    $account['updated_at'] = now_iso();
                    unset($account['password_hash'], $account['invite_hash'], $account['invite_expires_at']);
                    persist_admin_account($account, 'admin_account_disabled');
                    return ['success', 'Acceso retirado. Sus sesiones y enlaces dejaron de funcionar.'];
                });
                set_flash($result[0], $result[1]);
            } else { render_error_page('Acción no permitida', 'Elegí una acción válida.', 400); exit; }
            redirect('admin/accounts');
        }
        $inviteLink = $_SESSION['admin_invite_link'] ?? '';
        unset($_SESSION['admin_invite_link']);
        render_header(page_meta('Cuentas de administración | Perro', 'Gestión privada de accesos.', 'admin/accounts', false));
        ?><section class="section"><div class="shell narrow"><a class="text-link" href="/admin">← Volver al panel</a><h1>Cuentas de administración</h1><p>Cada persona usa su correo y su propia contraseña. Puede revisar, editar y publicar fichas, gestionar reportes y cambiar su contraseña. Solo la cuenta principal puede invitar personas o retirar accesos.</p>
        <?php if ($inviteLink): ?><div class="notice"><label>Enlace privado de activación<input readonly value="<?= h($inviteLink) ?>" autocomplete="off"></label><p>Copialo y compartilo por un canal privado. Vence en 24 horas y funciona una sola vez. La persona invitada elige su contraseña. El enlace se muestra una sola vez. No enviamos correos automáticamente.</p></div><?php endif; ?>
        <form class="submission-form account-form" method="post" action="/admin/accounts"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><fieldset><legend>Invitar una persona</legend><label>Nombre<input name="name" required maxlength="80" autocomplete="off"></label><label>Correo para ingresar<input type="email" name="email" required maxlength="180" autocomplete="off"></label><button class="button" type="submit">Crear enlace de activación</button></fieldset></form>
        <h2>Accesos del equipo</h2><div class="admin-list"><?php foreach (admin_accounts() as $account): ?><article class="admin-card"><div><h3><?= h($account['name']) ?></h3><p><?= h($account['email']) ?></p><span class="status-pill"><?= !empty($account['disabled']) ? 'Acceso retirado' : (!empty($account['active']) ? 'Activa' : (($account['invite_expires_at'] ?? 0) > time() ? 'Pendiente de activación' : 'Invitación vencida')) ?></span></div><div class="admin-card-actions"><?php if (empty($account['active'])): ?><form method="post" action="/admin/accounts"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><input type="hidden" name="email" value="<?= h($account['email']) ?>"><input type="hidden" name="name" value="<?= h($account['name']) ?>"><button class="button button-small" type="submit">Crear nuevo enlace</button></form><?php endif; ?><?php if (empty($account['disabled'])): ?><form method="post" action="/admin/accounts"><?= csrf_field() ?><input type="hidden" name="action" value="disable"><input type="hidden" name="id" value="<?= h($account['id']) ?>"><button class="button button-small button-danger" type="submit">Retirar acceso</button></form><?php endif; ?></div></article><?php endforeach; ?></div><p class="date-note">La cuenta principal «admin» se conserva y no se puede retirar desde esta página.</p></div></section><?php
        render_footer(); exit;
    }
    if ($path === 'activar-admin') {
        // Remove the bearer token from the visible URL before showing the form.
        header('Referrer-Policy: no-referrer');
        if (!method_is_post() && isset($_GET['token'])) {
            $token = is_string($_GET['token']) ? $_GET['token'] : '';
            if (!invited_admin_account($token)) { render_error_page('Invitación no disponible', 'El enlace venció o ya se usó. Pedile uno nuevo a la cuenta principal.', 404); exit; }
            $_SESSION['admin_invite_token'] = $token;
            redirect('activar-admin');
        }
        $token = (string) ($_SESSION['admin_invite_token'] ?? '');
        if (method_is_post()) {
            require_csrf();
            $result = activate_admin_account($token, password_input('password'), password_input('confirm_password'));
            set_flash($result[0], $result[1]);
            if ($result[0] === 'success') { unset($_SESSION['admin_invite_token']); session_regenerate_id(true); redirect('admin'); }
            redirect('activar-admin');
        }
        $account = invited_admin_account($token);
        if (!$account) { render_error_page('Invitación no disponible', 'El enlace venció o ya se usó. Pedile uno nuevo a la cuenta principal.', 404); exit; }
        render_header(page_meta('Activar cuenta | Perro', 'Elegí tu contraseña de administración.', 'activar-admin', false));
        ?><section class="section"><div class="shell login-card"><span class="eyebrow">Invitación privada</span><h1>Hola, <?= h($account['name']) ?></h1><p>Tu correo para ingresar será <strong><?= h($account['email']) ?></strong>.</p><form method="post" action="/activar-admin"><?= csrf_field() ?><label>Elegí tu contraseña<input type="password" name="password" required minlength="14" maxlength="72" autocomplete="new-password"><span class="field-help">Usá una frase larga y única de al menos 14 caracteres.</span></label><label>Repetí tu contraseña<input type="password" name="confirm_password" required minlength="14" maxlength="72" autocomplete="new-password"></label><button class="button button-full" type="submit">Activar mi cuenta</button></form></div></section><?php
        render_footer(); exit;
    }
}
