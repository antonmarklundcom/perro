<?php

declare(strict_types=1);

return [
    'site_name' => 'Perro',
    'base_url' => 'https://perro.com.py',
    'locale' => 'es-PY',
    'timezone' => 'America/Asuncion',
    'demo_mode' => false,
    'contact_whatsapp' => '595992279599',
    'admin_username' => 'admin',
    // Cambiá esta credencial después del primer ingreso. La contraseña inicial
    // se entrega fuera del ZIP para que no quede publicada en el servidor.
    'admin_password_sha256' => (getenv('PERRO_ADMIN_PASSWORD_SHA256') ?: ''),
    'max_uploads' => 5,
    'max_upload_bytes' => 5 * 1024 * 1024,
    'listing_expiry_days' => 60,
];
