<?php

declare(strict_types=1);

return [
    'site_name' => 'Perro',
    'base_url' => 'https://perro.com.py',
    'locale' => 'es-PY',
    'timezone' => 'America/Asuncion',
    'demo_mode' => false,
    // Set PERRO_NOINDEX=1 in every staging/preview PHP process.
    'indexing_enabled' => getenv('PERRO_NOINDEX') !== '1',
    // Enable contextual links only after the Mascota guides are live and checked.
    'mascota_guides_enabled' => getenv('PERRO_MASCOTA_GUIDES_ENABLED') === '1',
    'contact_whatsapp' => '595992279599',
    // Datos públicos del responsable. Completalos antes de publicar esta versión.
    'operator_name' => (getenv('PERRO_OPERATOR_NAME') ?: ''),
    'operator_address' => (getenv('PERRO_OPERATOR_ADDRESS') ?: ''),
    'operator_ruc' => (getenv('PERRO_OPERATOR_RUC') ?: ''),
    'privacy_email' => (getenv('PERRO_PRIVACY_EMAIL') ?: ''),
    'admin_username' => 'admin',
    // Cambiá esta credencial después del primer ingreso. La contraseña inicial
    // se entrega fuera del ZIP para que no quede publicada en el servidor.
    'admin_password_sha256' => (getenv('PERRO_ADMIN_PASSWORD_SHA256') ?: ''),
    'max_uploads' => 5,
    'max_upload_bytes' => 5 * 1024 * 1024,
    'listing_expiry_days' => 60,
];
