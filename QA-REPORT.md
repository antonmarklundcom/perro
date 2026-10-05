# QA local — Perro.com.py

Fecha: 31 de agosto de 2026.

## Validado localmente

- PHP 8.4: sintaxis correcta en todos los archivos PHP.
- Página principal y 13 rutas públicas/privadas: respuesta correcta.
- Flujo completo: envío pendiente, acceso administrador, aprobación y publicación.
- Cambio de contraseña del administrador: guardado con `password_hash()` y nuevo ingreso correcto.
- Enlaces internos encontrados: sin destinos rotos en la prueba local.
- Datos privados: correo y WhatsApp del remitente no aparecen en la ficha pública.
- Rutas privadas (`config.php`, `storage`, `includes`, `.htaccess`): bloqueadas en el router local y protegidas para Apache.
- Estado inicial: sin perros, organizaciones ni testimonios inventados.
- Indexación pública: habilitada; `robots.txt` debe permitir rastreo y el sitemap incluir las rutas públicas.
- JavaScript: sintaxis correcta.
- Imagen hero: WebP de 185 KB, marcada como ilustrativa.

## Validado pero limpiado antes de empaquetar

Los registros QA creados para probar el flujo fueron eliminados. Los archivos JSON del paquete empiezan vacíos.

## Pendiente en Hostinger

- Confirmar Apache `mod_rewrite`, PHP 8.1+ y extensión GD.
- Confirmar permisos de escritura en `storage/data` y `storage/uploads`.
- Probar fotos reales de prueba y verificar la recodificación a JPEG.
- Cambiar la contraseña inicial desde `/admin`.
- Revisar textos legales y configurar un canal real para privacidad/retiro.
- Verificar DNS, SSL, rutas limpias y cabeceras en el dominio público.
- Verificar en vivo que Google recibe `index,follow`, el `robots.txt` permitido y el sitemap correcto.

La validación local no prueba por sí sola el funcionamiento en Hostinger ni que el dominio esté publicado.
