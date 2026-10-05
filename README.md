# Perro.com.py — paquete Hostinger PHP

Sitio ligero para difusión y moderación de adopciones de perros en Paraguay. No usa Node.js, npm, Firebase ni servicios de IA.

## Requisitos

- PHP 8.1 o superior.
- Apache con `mod_rewrite` y soporte para `.htaccess`.
- Permiso de escritura en `storage/data` y `storage/uploads`.
- Extensión GD para recibir, redimensionar y limpiar fotos. Sin GD, las fichas de texto siguen funcionando y el formulario explica que las fotos están deshabilitadas.

## Subida a Hostinger

Para actualizar el sitio existente desde Git, seguí **REDEPLOY.md** y **LEGAL-RELEASE.md**. Conservá los datos de producción y verificá las variables del responsable legal y el acceso administrativo antes de abrir el sitio.

1. Extraé el contenido del ZIP directamente dentro de `public_html`. `index.php` debe quedar en la raíz, no dentro de otra carpeta.
2. Verificá que `storage/data` y `storage/uploads` sean escribibles por PHP. Normalmente 755 alcanza; usá 775 solo si Hostinger lo requiere.
3. Abrí `/admin` con la credencial ya configurada en producción. En una instalación nueva, configurá `PERRO_ADMIN_PASSWORD_SHA256` en el entorno PHP; este repositorio no incluye una contraseña.
4. En el mismo panel, usá “Cambiar contraseña” y elegí una clave larga y única. El nuevo hash queda dentro de la carpeta protegida `storage/data`.
5. El WhatsApp general ya está configurado como `+595 992 279 599`, con mensajes que cambian según la página.
6. La indexación pública ya está habilitada. Después de subir, verificá `/robots.txt`, `/sitemap.xml`, HTTPS y las URLs canónicas.

## Flujo

1. Una persona completa `/dar-perro-en-adopcion`.
2. La ficha se guarda como pendiente y no se publica.
3. La administración ingresa en `/admin`.
4. Al aprobar, se crea la ficha pública y se fija un vencimiento a 60 días.
5. La administración puede revisar fotos privadas, editar datos y fotos, retirar permisos de contacto y gestionar estados según el tipo de aviso. Adoptados y reencontrados dejan de aparecer públicamente. Para renovar o reabrir un aviso, debe confirmar su vigencia con el responsable.
6. Repetir una aprobación no duplica ni reabre una ficha. Las solicitudes antiguas sin aceptación de las reglas actuales necesitan un nuevo envío del responsable.

## WhatsApp

- El número general configurado es `+595 992 279 599`.
- La cabecera, el pie y el botón flotante usan mensajes según la página visitada. El hero permite adoptar o publicar; el botón flotante se oculta en los formularios y las fichas para no tapar campos.
- En una ficha individual, el mensaje incluye el nombre del perro.
- Dentro de `/admin`, cada solicitud tiene un botón para escribir al remitente con el nombre del perro y la referencia ya incluidos.
- WhatsApp no aprueba ni publica nada automáticamente: las decisiones siguen ocurriendo dentro de `/admin`.

## Seguridad y límites

- Los archivos de datos y fotos están dentro de `storage`, bloqueado por `.htaccess`.
- Las fotos públicas se entregan mediante un endpoint que comprueba el estado de la ficha.
- Las fotos aceptadas se vuelven a codificar como JPEG, se limitan a 1800 px y no conservan los metadatos del archivo original.
- Hay CSRF, honeypot, tiempo mínimo de formulario, validación MIME, límites de tamaño y cabeceras de seguridad.
- Nombre y WhatsApp permanecen privados por defecto. El correo nunca se publica. Hay límites persistentes para ingresos, envíos y reportes; cambiar la contraseña invalida otras sesiones.
- Los registros JSON se actualizan bajo un bloqueo global y un diario de recuperación para cambios en varios archivos. Un archivo dañado produce un error y no se reemplaza silenciosamente por datos vacíos.
- El almacenamiento JSON con bloqueo de archivos es suficiente para un proyecto pequeño. Si crece a cientos de envíos concurrentes, migrá a MySQL.
- No borres ni reemplaces el contenido de `storage/data` durante una actualización sin hacer una copia.

## Copias de seguridad

Descargá periódicamente la carpeta `storage`. El panel permite exportar las fichas publicadas a CSV, pero ese CSV no sustituye la copia completa.

## Prueba de desarrollo

`node tools/privacy-smoke.cjs` verifica rutas y flujos con datos sintéticos en una copia temporal. Configurá `PERRO_PHP_BIN` si PHP no está en PATH. Para habilitar GD localmente en la prueba, podés configurar `PERRO_PHP_GD_DIR`. No hace falta Node en Hostinger.
