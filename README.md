[Acuerdo editorial/comercial compartido v1.0.0](docs/portfolio/2026-10-09/MASCOTA-PERRO-AGREEMENT.md) · [90 días y acciones del propietario](docs/portfolio/2026-10-09/90-DAY-PLAN.md).

# Perro.com.py — paquete Hostinger PHP

Sitio ligero para difusión y moderación de adopciones de perros en Paraguay. No usa Node.js, npm, Firebase ni servicios de IA.

## Requisitos

- PHP 8.1 o superior.
- Apache con `mod_rewrite` y soporte para `.htaccess`.
- Permiso de escritura en `storage/data` y `storage/uploads`.
- Extensión GD para recibir, redimensionar y limpiar fotos. GD con FreeType y las fuentes incluidas en assets/fonts permite generar las imágenes para compartir. Sin GD, las fichas de texto siguen funcionando y el formulario explica que las fotos están deshabilitadas.
- Extensión EXIF opcional para corregir la orientación de fotos JPEG de celulares antes de quitar sus metadatos. Sin EXIF se conservan los píxeles originales y el equipo puede corregir el giro desde el panel.

## Subida a Hostinger

Para actualizar el sitio existente desde Git, seguí **REDEPLOY.md** y **LEGAL-RELEASE.md**. Conservá los datos de producción y verificá las variables del responsable legal y el acceso administrativo antes de abrir el sitio.

1. Extraé el contenido del ZIP directamente dentro de `public_html`. `index.php` debe quedar en la raíz, no dentro de otra carpeta.
2. Verificá que `storage/data` y `storage/uploads` sean escribibles por PHP. Normalmente 755 alcanza; usá 775 solo si Hostinger lo requiere.
3. Abrí `/admin` con la credencial ya configurada en producción. En una instalación nueva, configurá `PERRO_ADMIN_PASSWORD_SHA256` en el entorno PHP; este repositorio no incluye una contraseña.
4. En el mismo panel, usá “Cambiar contraseña” y elegí una clave larga y única. El nuevo hash queda dentro de la carpeta protegida `storage/data`.
5. El WhatsApp general ya está configurado como `+595 992 279 599`, con mensajes que cambian según la página.
6. La indexación pública ya está habilitada. Después de subir, verificá `/robots.txt`, `/sitemap.xml`, HTTPS y las URLs canónicas.

## Flujo

El formulario público funciona sin crear una cuenta y sin correo obligatorio. El WhatsApp privado permite contactar al responsable. Con JavaScript, se completa en cuatro pasos con validación, resumen de privacidad y vistas previas de fotos; también se puede abrir completo. Sin JavaScript sigue siendo un formulario normal. Los navegadores compatibles reducen fotos grandes antes de enviarlas; PHP vuelve a validar y limpiar cada imagen. Si hay errores del servidor, se conservan los textos, pero las fotos deben seleccionarse nuevamente.

La búsqueda incluye ciudad, departamento, edad, tamaño, sexo, disponibilidad y orden, con 12 fichas por página. Los avisos perdidos/encontrados también tienen búsqueda y filtro por tipo. Las sugerencias de ubicación salen de avisos públicos reales. Las páginas con filtros llevan `noindex,follow`; la paginación sin filtros tiene su propia URL canónica. Las fichas incluyen rutas de navegación estructuradas y botones para compartir; no se presentan como productos en venta.

1. Una persona completa `/dar-perro-en-adopcion`.
2. La ficha se guarda como pendiente y no se publica.
3. La administración ingresa en `/admin`.
4. Al aprobar, se crea la ficha pública y se fija un vencimiento a 60 días.
5. La administración puede revisar fotos privadas, editar datos y fotos, retirar permisos de contacto y gestionar estados según el tipo de aviso. Adoptados y reencontrados dejan de aparecer públicamente. Para renovar o reabrir un aviso, debe confirmar su vigencia con el responsable.
6. Repetir una aprobación no duplica ni reabre una ficha. Las solicitudes con versiones expresamente compatibles conservan su aceptación; los cambios materiales requieren una nueva aceptación del responsable.

## Compartir y mantener avisos

La página /como-funciona explica el envío sin cuenta, los datos privados, la revisión, los cambios por WhatsApp con referencia y la confirmación de vigencia. La referencia ayuda a localizar el envío; el equipo verifica la relación del solicitante con el aviso antes de modificarlo. La confirmación de envío ofrece borradores separados para correcciones, retiro y cambio de estado.

Cada ficha aprobada y vigente tiene una sección /perro/{slug}#compartir con Facebook, WhatsApp, texto y enlace para copiar. Con una foto válida y GD/FreeType, ofrece JPEG de publicación (1080×1080) e historia (1080×1920); la vista previa de Facebook usa 1200×630. Se usa la primera foto real de la ficha y solo nombre, ciudad, tipo, estado e identificador público. El código público de ficha impreso en la imagen también se puede buscar en el catálogo correspondiente; es distinto de la referencia privada del envío. No se copian nombres/contactos del responsable, notas, referencias privadas ni la descripción a los materiales. Las fuentes Atkinson Hyperlegible se distribuyen con su licencia OFL.

Las rutas /compartir/{slug}/{facebook|post|story}.jpg comprueban en cada solicitud que el aviso siga activo. No sirven imágenes pendientes, vencidas, retiradas, adoptadas o reencontradas. Las imágenes se generan con caché local privada del servidor, sin servicios externos; cada solicitud comprueba de nuevo la vigencia de la ficha. El panel de avisos ofrece un acceso directo al material y el borrador de publicación señala dónde encontrarlo.

Instagram requiere subir el archivo manualmente y agregar el enlace de la ficha al sticker de una historia. Sin GD/FreeType o sin foto, siguen disponibles el texto y el enlace; una ficha sin foto no usa la imagen ilustrativa del sitio como si fuera su perro. Las copias descargadas y las vistas previas que guarda una red social no se actualizan ni se pueden retirar desde Perro. Revisá la ficha antes de volver a difundirla.

## Cuentas de administración

La cuenta principal conserva el usuario `admin` y su contraseña de producción. No hay una contraseña incluida en Git. El hash de `storage/data/settings.json`, registro `id: admin`, tiene prioridad sobre `PERRO_ADMIN_PASSWORD_SHA256`. Un hash no permite recuperar la contraseña original; si se perdió el acceso, la persona con acceso al servidor debe restablecerlo preservando los demás registros y las copias de seguridad.

Desde `/admin`, la cuenta principal entra en **Cuentas del equipo** (`/admin/accounts`), escribe el nombre y correo de la persona e invita con un enlace privado. El enlace vence en 24 horas, funciona una vez y se muestra una sola vez. Compartilo por un canal privado; no hay envío automático de correos. La persona invitada elige una contraseña de al menos 14 caracteres y después ingresa en `/admin` con su correo.

Las cuentas del equipo pueden revisar, editar y publicar fichas, gestionar reportes, exportar fichas y cambiar su propia contraseña. Solo la cuenta principal puede invitar o retirar accesos. Los cambios de contraseña invalidan otras sesiones de esa cuenta y conservan las sesiones de las demás personas. Retirar acceso invalida sesiones e invitaciones; para recuperar una cuenta del equipo, retirale el acceso y creá un enlace nuevo. Los datos de cuentas se guardan en el archivo protegido de ajustes; no se publican en Git.

## WhatsApp

El panel móvil abre en **Pendientes** y separa revisión, avisos, reportes y contraseña. Tiene búsqueda privada por perro, ciudad, referencia o responsable. Cada solicitud permite revisar sus fotos y abrir borradores de WhatsApp para consultar datos, pedir fotos, confirmar vigencia o avisar una publicación. El equipo revisa y envía esos mensajes manualmente. El correo usado para identificar una cuenta de administración no requiere un servicio de envío de emails.

- El número general configurado es `+595 992 279 599`.
- La cabecera, el pie y el botón flotante usan mensajes según la página visitada. El hero permite adoptar o publicar; el botón flotante se oculta en los formularios y las fichas para no tapar campos.
- En una ficha individual, el mensaje incluye nombre, código público, ciudad y URL.
- Dentro de `/admin`, cada solicitud tiene un botón para escribir al remitente con el nombre del perro y la referencia ya incluidos.
- WhatsApp no aprueba ni publica nada automáticamente: las decisiones siguen ocurriendo dentro de `/admin`.

En **Fichas de perros**, el panel y el CSV conservan el nombre, correo, WhatsApp y referencia del envío original como datos privados para administración, aunque no estén autorizados para publicarse. El sitio público sigue mostrando WhatsApp únicamente con autorización. Tratá el CSV como información privada.

## Girar y recortar fotos

Desde **Revisar y editar**, abrí **Girar y recortar** en cada foto. Guardá primero los cambios de texto de la ficha: la foto usa un formulario separado. Podés girar 90° a izquierda/derecha y arrastrar un recorte sobre la vista previa, también desde el celular. Sin JavaScript siguen disponibles el selector de giro y las coordenadas numéricas del recorte, medidas después del giro.

El recorte es definitivo. Al guardar, se crea un nuevo JPEG sin metadatos de hasta 1800 px, se actualizan el envío y su ficha pública bajo el mismo bloqueo y diario, y se elimina la versión anterior después de confirmar el cambio. Una edición desactualizada se rechaza. Sin GD se informa la limitación y se conserva la foto. Si una escritura se interrumpe después de guardar el diario, se conservan las imágenes completas necesarias para su recuperación; nunca borres el diario para ocultar un error.

## Seguridad y límites

- Los archivos de datos y fotos están dentro de `storage`, bloqueado por `.htaccess`.
- Las fotos públicas se entregan mediante un endpoint que comprueba el estado de la ficha.
- Las fotos aceptadas se vuelven a codificar como JPEG, se limitan a 1800 px y no conservan los metadatos del archivo original.
- Hay CSRF, honeypot, tiempo mínimo de formulario, validación MIME, límites de tamaño y cabeceras de seguridad.
- Nombre y WhatsApp permanecen privados por defecto. El correo nunca se publica. Hay límites persistentes para ingresos, envíos y reportes; cambiar la contraseña invalida otras sesiones.
- Los registros JSON se actualizan bajo un bloqueo global y un diario de recuperación para cambios en varios archivos. Un archivo dañado produce un error y no se reemplaza silenciosamente por datos vacíos.
- El almacenamiento JSON con bloqueo de archivos es suficiente para un proyecto pequeño. Si crece a cientos de envíos concurrentes, migrá a MySQL.
- No borres ni reemplaces el contenido de `storage/data` durante una actualización sin hacer una copia.

## Operación después de la auditoría

Consultá **OPERATIONS.md** para alertas opcionales, /health, respaldo diario, restauración, archivo mensual, variantes de fotos y supresión verificada. El panel pagina 25 registros por pestaña, busca referencias/códigos/teléfonos/correos, permite reabrir rechazos y muestra Por confirmar. Las fichas adoptadas o reencontradas responden 410 con otros avisos cercanos. El enlace /contactar cuenta clics agregados, sin identificar visitantes; los resultados de adopción se registran por confirmación y se exportan en CSV. Los slugs existentes se conservan al cambiar el nombre.

## Copias de seguridad

Descargá periódicamente la carpeta `storage`. El panel permite exportar las fichas publicadas a CSV, pero ese CSV no sustituye la copia completa.

## Prueba de desarrollo

`node tools/privacy-smoke.cjs` verifica rutas y flujos con datos sintéticos en una copia temporal. Configurá `PERRO_PHP_BIN` si PHP no está en PATH. Para habilitar GD localmente en la prueba, podés configurar `PERRO_PHP_GD_DIR`; agregá `PERRO_PHP_EXIF=1` para habilitar la extensión EXIF instalada. La prueba comprueba etiquetas, CSV privado, orientación, giro, recorte y errores de guardado cuando GD está disponible, y la conservación de fotos sin GD. No hace falta Node en Hostinger.


## SEO, guías y coordinación con Mascota — 9 octubre 2026

Consultá [el informe y mapa de páginas](docs/seo/2026-10-09/README.md), [el modelo de ingresos](docs/business/2026-10-09/REVENUE-PLAN.md) y [el release con pruebas/subida/rollback](docs/verification/2026-10-09/SEO-RELEASE.md).

Siete páginas nuevas orientan requisitos, elección, hogar temporal, reubicación, llegada y apoyo; seguridad y los catálogos existentes se amplían sin cambiar los avisos ni su moderación. `/docs` contiene documentación privada al público bajo Apache/router y se excluye del ZIP; no guardes secretos allí ni asumas que la denegación sustituye protección del servidor.

Para staging configurá `PERRO_NOINDEX=1` en el proceso PHP: robots, meta y cabecera bloquean indexación. Confirmá que esté desactivado en producción. `PERRO_MASCOTA_GUIDES_ENABLED=1` activa enlaces contextuales solo después de publicar/verificar las cinco guías de Mascota; por defecto están deshabilitados porque el dominio mostró una página de hosting por defecto.

`node tools/seo-smoke.cjs` agrega pruebas aisladas de SEO y estados. `python tools/build-release.py DESTINO.zip` prepara desde una revisión limpia y comprometida solo archivos de aplicación para Hostinger, sin storage privado, investigación o configuración secreta. La adopción sigue gratuita y la página `/apoyar` no habilita cobros.

## Reliability and return visits — 9 October 2026

See [the new operating instructions](docs/development/2026-10-09/IMPLEMENTATION.md) and [status-first Claude audit prompt](docs/development/2026-10-09/CLAUDE-STATUS-FIRST.txt). Storage remains private JSON; this release adds safeguards, diagnostics, verified backup drills, cleanup retries, worker claims, scoped owner links, team roles, saved references and RSS. No SQL migration is needed.
