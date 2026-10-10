<?php

declare(strict_types=1);

function admin_role_options(): array
{
    return ['moderator'=>'Moderación', 'manager'=>'Gestión (incluye CSV privado y supresión permanente)'];
}

function admin_account_role(?array $account = null): string
{
    if ($account === null) {
        if (current_admin_account_id() === 'admin') return 'primary';
        $account = find_record('settings', current_admin_account_id()) ?? [];
    }
    // Never upgrade a legacy, absent or unrecognized role implicitly.
    $role = $account['role'] ?? null;
    return is_string($role) && array_key_exists($role, admin_role_options()) ? $role : 'moderator';
}

function admin_has_capability(string $capability): bool
{
    if (!is_admin()) return false;
    $known = ['moderate', 'edit', 'photos', 'owner_links', 'export_private', 'permanent_delete', 'manage_accounts'];
    if (!in_array($capability, $known, true)) return false;
    if (current_admin_account_id() === 'admin') return true;
    if ($capability === 'manage_accounts') return false;
    if (in_array($capability, ['export_private', 'permanent_delete'], true)) return admin_account_role() === 'manager';
    return true;
}

function require_admin_capability(string $capability): void
{
    require_admin();
    if (!admin_has_capability($capability)) {
        render_error_page('Acceso restringido', 'Tu cuenta no tiene permiso para esta acción. Pedile a la cuenta principal que revise tu rol.', 403);
        exit;
    }
}

function change_admin_role(string $id, string $role): array
{
    return with_data_lock(static function () use ($id, $role): array {
        if (!admin_has_capability('manage_accounts')) return ['error', 'Solo la cuenta principal puede administrar accesos.'];
        if (!array_key_exists($role, admin_role_options())) return ['error', 'Elegí un rol válido.'];
        $account = str_starts_with($id, 'admin-user-') ? find_record('settings', $id) : null;
        if (!$account) return ['error', 'La cuenta no existe.'];
        $account['role'] = $role;
        $account['updated_at'] = now_iso();
        persist_admin_account($account, 'admin_role_changed');
        return ['success', 'Rol actualizado. Los permisos nuevos se aplican en la próxima acción.'];
    });
}
