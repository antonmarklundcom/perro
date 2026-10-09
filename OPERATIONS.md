# Operación de Perro

El servidor ejecuta PHP 8.1+; Node se usa solo para pruebas locales y CI. Desplegá todos los archivos de la misma revisión. Conservá `storage/`, `config.php` y los ajustes/credenciales del servidor.

## Configuración y verificaciones pendientes

- Configurá `PERRO_OPERATOR_NAME`, `PERRO_PRIVACY_EMAIL` y los datos públicos aplicables del responsable. El panel avisa si faltan nombre o correo. No se inventa una identidad legal.
- Confirmá HTTPS, que PHP reciba su estado HTTPS, y que la CDN conserve la CSP completa y HSTS. No se confía en cabeceras de proxy sin verificar su origen.
- Tras desplegar, ejecutá `node tools/check-release.cjs https://perro.com.py perro-XXXXXXXXXXXX`, usando la huella obtenida de la misma revisión en una copia local. La prueba solo lee páginas y falla si faltan CSP, HSTS, salud o la huella esperada.
- Configurá un monitor externo sobre `/health`; devuelve solo estado, sin datos privados. Un 503 incluye un código de incidente para buscar en el registro PHP.
- Comprobá un mensaje desde tu teléfono y una ficha real en Sharing Debugger/Search Console. No se envían mensajes ni se crean avisos ficticios en producción desde las pruebas.

## Alertas de revisión

El envío y el reporte guardan una alerta privada con referencia/código y enlace al panel, sin nombre, teléfono, correo, historia ni fotos. Una falla de alertas no impide recibir el aviso. No se envían correos durante un despliegue.

Antes de activar envíos, configurá `PERRO_NOTIFY_EMAIL` y `PERRO_NOTIFY_FROM` con correos reales y verificá que PHP `mail()` esté disponible y el remitente autorizado. Probá primero contra un destinatario de prueba elegido por vos. Si tu alojamiento no entrega `mail()`, hace falta configurar un transporte verificado antes de activar el cron.

Ejecutá cada minuto desde un cron privado de hPanel:

```sh
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --notifications
```

Un cron diario puede ejecutar `--digest`. Los mensajes resumen pendientes, reportes abiertos y avisos por confirmar. Nunca contienen contactos privados. Un transporte que falla deja alertas pendientes para reintentar. Evitá crons simultáneos; una interrupción después del envío puede producir una alerta repetida.

## Respaldo y restauración

Con la extensión ZIP disponible, el cron diario crea una copia coherente de los JSON y fotos bajo el bloqueo de la aplicación:

```sh
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --backup-dir=/RUTA/PRIVADA/FUERA/DE/public_html/perro-backups
```

El script rechaza destinos dentro de la raíz de Perro, conserva las 14 copias más nuevas y omite cachés/archivos temporales. Confirmá además que el destino no sea público por otro dominio del alojamiento. Respaldá el código y la configuración del servidor por separado. Las copias contienen datos privados: limitá su acceso y elegí una copia semanal fuera del alojamiento.

Trimestralmente, extraé una copia en un directorio aislado, comprobá los conteos JSON y fotos, y probá ingreso/revisión con la configuración local adecuada. Nunca extraigas una copia sobre producción sin detener escrituras y planificar la restauración. La prueba de auditoría incluye una restauración local con conteos coincidentes.

## Archivo, fotos y privacidad

```sh
# Primero informa los registros elegibles; no modifica nada.
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --archive --days=180
# Después de revisar la lista, activa el archivo mensual.
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --archive --days=180 --apply
# Prepara variantes de 480/960 px para fotos existentes.
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --backfill
# Informa contactos heredados inválidos o con prefijo 5950, sin cambiarlos ni imprimir teléfonos.
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --audit-phones
# Revisa y, con --apply, elimina fotos archivadas fuera del período elegido.
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --retention --days=180
# Cuenta notas históricas copiadas a la bitácora; agrega --apply para depurarlas.
php /RUTA/REAL/DE/PERRO/tools/maintenance.php --scrub-notes
```

El archivo retira avisos cerrados antiguos de los archivos activos; sigue siendo privado y consultable en la pestaña **Archivo**. No archiva pendientes ni renueva fichas automáticamente. Los períodos deben ajustarse a la necesidad real; el mínimo del script es 180 días. `--retention` requiere `--apply` para borrar fotos y no borra los registros archivados.

La supresión desde el editor exige verificar la solicitud y confirmar. Elimina envíos/avisos vinculados, reportes, notas, entradas asociadas y fotos, incluyendo variantes y cachés locales. Los respaldos y las copias de terceros se revisan aparte. La bitácora nueva registra acciones sin copiar notas privadas. Las notas históricas aún existentes pueden revisarse con el procedimiento de privacidad; nunca modifiques JSON manualmente mientras la aplicación escribe.

Las fotos públicas usan caché privada con revalidación y ETag: el origen verifica su vigencia antes de responder 304. Las imágenes sociales se guardan en caché privada del servidor y se validan contra la ficha en cada solicitud. Los clientes y redes externas pueden conservar copias previas.

## Pruebas

```sh
node tools/privacy-smoke.cjs
PERRO_PHP_DISABLE_GD=1 node tools/privacy-smoke.cjs
node tools/audit-smoke.cjs
```

Las pruebas utilizan copias temporales y datos sintéticos. Para PHP de Windows, `PERRO_PHP_BIN` selecciona el ejecutable y `PERRO_PHP_GD_DIR` habilita GD cuando está instalado pero desactivado. `PERRO_PHP_EXIF=1` habilita EXIF si está disponible. No cambian los datos del repositorio ni llaman al sitio real.

## Current reliability upgrade

Read [schema-2 storage, role, owner-link and backup procedures](docs/development/2026-10-09/IMPLEMENTATION.md) before using the current release. Diagnostics are read-only; failed cleanup is retried explicitly; notification retry delays and worker claims are authoritative. This supersedes any older advice that missing installed datasets are initialized automatically.
