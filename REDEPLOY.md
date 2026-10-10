# Redeploy from main

This is a PHP source deployment. There is no npm/build step. Main contains the release after its PR is merged; use the exact merged revision. This document does not trigger a deployment.

## Before deploying

1. In the Hostinger account that actually hosts perro.com.py, verify the domain, repository antonmarklundcom/perro, selected branch main, configured destination and automatic-deploy/webhook behavior. Do not assume another domain's Git settings apply. The previous public site matched the imported v1; these changes intentionally update its behavior.
2. Take a complete, recoverable backup of deployed code, config.php and storage, including submissions, dogs, reports, moderation, settings and all uploads. Keep the backup outside the publicly served directory. Preserve settings.json: it may hold the current administrator password. A CSV is not a full backup.
3. Provision the actual operator name, public/business contact address and monitored privacy email described in LEGAL-RELEASE.md. Add the applicable RUC if relevant. Confirm the WhatsApp number. PHP does not read .env automatically; verify PHP/Apache really receives configured environment variables. Do not copy the old hardcoded password into Git. If no settings password exists, provision PERRO_ADMIN_PASSWORD_SHA256 securely.
4. Confirm PHP >= 8.1, GD with FreeType, iconv, Apache/.htaccess rewrite and protected storage with PHP write permission. Suggested limits to support five photos: upload_max_filesize >= 5M, post_max_size >= 30M, max_file_uploads >= 5, memory_limit >= 128M. Images are limited to 8 megapixels and checked against the available memory budget. Adjust limits for the actual Hostinger runtime.
5. Schedule a brief maintenance window that stops incoming form/admin writes while all PHP files are replaced together. Old code and new code use different locking rules and must not write the same storage concurrently. Resolve any remaining transaction.json journal with this release before rolling back to older code.

## Deploy and preserve

Use the configured Hostinger Git deployment from main. Verify its behavior preserves untracked production storage/uploads/settings. If it cleans the destination, arrange safe preservation/restoration before running it. Do not deploy local test storage or copy a development config over production-specific values without reconciling them. The new includes/legal.php and includes/moderation.php, modified PHP files, .htaccess and updated CSS must all arrive in the domain's actual document root.

Fresh empty installations initialize schema-2 storage. Complete valid unversioned/v1 installations preserve their existing eight datasets and add only owner_actions/cleanup plus the inner schema marker and private `storage/installation.json` marker outside the data directory. Missing required files or data/uploads directories in a marked established installation fail safely rather than being replaced with empty arrays. Preserve `storage/installation.json`: do not remove it to force fresh initialization. Only an explicitly initializing, known-empty installation may resume. A global application.lock and private transaction journal serialize writes/recovery. Do not make concurrent hand edits to JSON while PHP is writing. Legacy public full names are hidden unless explicit name consent exists. Old pending requests lacking current policy acceptance must be resubmitted by their owner; an admin cannot accept the rules on their behalf.

Email admin accounts also require `includes/accounts.php`. Preserve the complete settings.json array: it can contain both the primary `admin` password and team accounts. Do not replace it with a single password object or an empty array. After deployment, sign in with the existing primary account and open `/admin/accounts` to issue a private activation link. New accounts are not created automatically by deploying code. The primary password is not in the repository; if access is lost, recover it through authorized server access before issuing invitations.

The mobile publishing/search/admin release also requires `includes/experience.php`; deploy the complete PHP/CSS/JS release together. No SMTP or email provider is needed. Test one real, authorized submission after deployment: keep the default private contact preferences, upload a phone photo, check the receipt reference, review it in admin, and approve only when its information and permissions are verified. Verify the search result and each WhatsApp draft on a phone. Do not use fictional test dogs on production.

The sharing/guide release requires includes/sharing.php, includes/guidance.php and assets/fonts (both Atkinson Hyperlegible TTF files and OFL.txt), together with the changed PHP/CSS. There is no extra backend, email, account or payment setup. GD with FreeType enables generated graphics; without it, caption/link sharing remains available. On an actual approved notice with its authorized dog photo, check /perro/{slug}#compartir, the Facebook image metadata and both Instagram downloads. Upload Instagram files manually. Facebook may retain an old preview; use its Sharing Debugger to request a fresh fetch after deployment if needed. Perro cannot recall images already copied elsewhere.

The admin photo editor requires the updated includes/data.php, includes/moderation.php and CSS/JS together. GD enables rotation/cropping; without it, stored photos remain intact and the editor explains the limitation. Optional EXIF support corrects all eight JPEG orientation values, including mirrored images, before stripping metadata. Without EXIF, raw pixels are retained and the admin can rotate manually. Photo edits use replacement filenames and the existing JSON journal; retain complete images and any transaction.json during interrupted-write recovery. A crop is permanent, so preserve routine backups. Admin overview/CSV contact fields are private and come from the original submission; their inclusion does not grant public WhatsApp consent.

## Validate before reopening

- Check the 14 routes in AGENTS.md over HTTPS. Verify .htaccess denies config.php, includes, tools, hidden files and Markdown documentation; storage JSON/uploads must never be directly readable. Test with an unauthenticated browser.
- Inspect the actual rendered terms/privacy for correct identity, address and contact. The pages omit unset fields; a blank configuration is not legal clearance. Have a competent Paraguayan reviewer assess the actual operation and policies.
- Verify admin login, password change, private photo review and complete editing. On staging or a controlled test fixture, verify a private-by-default submission stays pending, approval creates one public listing, public alias/WhatsApp appear only with permission, and revocation removes them. Remove all test records and images before opening to visitors.
- Confirm adopted/reunited/expired/withdrawn listings, photos and /compartir image downloads disappear from active public routes. Renewal requires confirming the situation with the owner. Test an invalid/oversized image: no success message or partially accepted submission should appear.
- Check assets, mobile layout, WhatsApp links, robots/sitemap and server logs. Purge old CDN caches where relevant. Review private backups and retention practices.

### Confirm which release is running

PHP responses expose `X-Perro-Release`, also available in the HTML `perro-release` meta tag. This fingerprint is derived from application source and CSS/JS, excluding config and private storage. Compare it with the same header from an isolated preview of the merged revision. Windows/Linux line endings are normalized for this fingerprint. Stylesheet and script URLs include their content hashes, so changed assets receive new URLs automatically.

If the release fingerprint differs after deployment, verify the deployed revision and document root, then clear the relevant Hostinger/CDN/PHP opcode caches. A hard refresh alone cannot correct files deployed into the wrong directory. This fingerprint does not validate production settings, operator identity or private data.

## Local verification

Run php -l on every root and includes PHP file. Run node tools/privacy-smoke.cjs; Node is only used by the development test. PERRO_PHP_BIN can select the PHP executable. If local GD is installed but disabled, PERRO_PHP_GD_DIR can point to the PHP extension directory to enable it for this test. Without GD the script verifies that photo attempts fail clearly; with GD it also tests real multipart image conversion, private review, addition/removal and publication. The test copies source into an OS temp directory and uses synthetic local fixtures. It never calls production.

Set PERRO_PHP_EXIF=1 to enable an installed optional EXIF extension in the smoke runtime. Added checks cover Spanish admin labels, private CSV contacts and formula escaping, all JPEG orientation values (with/without EXIF), authenticated rotation/cropping, crop bounds, stale revisions, the 1800 px limit and cleanup after a failed commit. Run once with GD/EXIF and once without GD to verify both paths. These test environment variables do not change production PHP settings.

## Rollback and daily operation

Stop writes before reverting code. Preserve current private data and photos. If transaction.json exists, let the new release complete recovery first; do not delete it to hide an error. Restore a full storage backup only after considering legitimate records received since that backup. Check functionality before reopening.

The team reviews the admin queue and reports daily, verifies listings with owners, sends follow-up messages manually using WhatsApp templates, and handles correction/deletion requests. Keep automated hosting backups and test restoration. Payment collection and donor lists are not enabled; decide recipient identity, accounting, budget transparency and separate opt-in recognition before adding fundraising.


## Auditoría 2026-10-07

Incluí includes/workflows.php, includes/admin-panel.php, includes/operations.php y assets/images/default-preview.jpg junto al resto de la misma revisión. La reescritura Apache ahora usa la URL real y elimina la consulta path. No requiere migración de base de datos: los nuevos archivos privados archive.json y notifications.json se inicializan sin reemplazar registros existentes. Para cambios de reglas, seguí las listas de compatibilidad de LEGAL-RELEASE.md.

OPERATIONS.md contiene las instrucciones para las alertas opcionales, crons privados, respaldo ZIP, archivo, variantes y verificación de la huella/CSP/HSTS. Activar esos servicios necesita configuración real; desplegar los archivos no envía correos ni activa crons. Conservá storage, ajustes y credenciales.

Schema-2 and scoped owner-link rollback requirements: [current implementation procedures](docs/development/2026-10-09/IMPLEMENTATION.md). Preserve cleanup/owner-action datasets and notification claim states; inspect compatibility before an older writer resumes.
