# Perro SEO release — 9 October 2026

Prepared on `codex/seo-adoption-business-20261009` from main `aa3b5dd49bdca83e60fbab89ffcf526313a7869d` (PR8 merged). The final Git revision and ZIP checksum are recorded in the local operation manual and generated `.zip.sha256`. **Implemented and tested; not merged or deployed by this task.**

## Validation of this change

| Check | Result | Scope |
| --- | --- | --- |
| PHP syntax | PASS, 18 files | Root, includes and existing PHP tools, PHP 8.3.33 |
| Privacy without GD | PASS, 171 checks | Synthetic isolated copy; consent, accounts, moderation and concurrency |
| Privacy with GD/EXIF | PASS, 234 checks | Synthetic isolated copy; photo processing/revocation and private storage |
| Existing audit | PASS, 72 checks | Synthetic isolated copy; reliability/admin/contact/backup restore |
| SEO regression | PASS, 304 checks | 17 static pages, unique title/description/H1, canonical, HTML links/anchors, sitemap, preview noindex, private docs, pagination, empty states and six listing states |
| Browser | PASS, 32 checks | Headless Chrome at 375/1440 px, no overflow on selected routes, keyboard FAQ, no-JS navigation and lost-form preselection |
| Visual inspection | PASS | Mobile homepage and desktop adoption requirements guide reviewed; screenshot evidence local only |
| Production observations | READ ONLY | Perro public routes/robots/sitemap return 200 with existing release `perro-2a75d794a509`; current live sitemap has 10 routes. Mascota root is default hosting page and dog guide is 404 |

The development server exercises PHP/router, not Apache. Apache `.htaccess`, actual TLS/CDN headers, real hosting permissions, deliveries, operator facts and payment configuration still require host verification. No synthetic notices, contacts or uploads go into the release. No automated messaging, payments or deployment is added.

`tools/seo-smoke.cjs` is in PR-only CI with the existing suites. It creates an isolated temporary copy, checks active/pending/expired/withdrawn/adopted/reunited fixtures, and cleans up its own server. Existing regression scripts likewise use synthetic temporary copies. Do not point test scripts at production storage. Browser evidence and releases are under `C:/AI work/perro/seo-20261009`, outside OneDrive.

Local Windows commands:

```powershell
$env:PERRO_PHP_BIN = 'C:/php/php.exe'
$env:PERRO_PHP_GD_DIR = 'C:/php/ext'
$env:PERRO_PHP_EXIF = '1'
node tools/privacy-smoke.cjs
node tools/audit-smoke.cjs
node tools/seo-smoke.cjs
```

For the fallback pass, set `PERRO_PHP_DISABLE_GD=1`. Do not inherit `PERRO_NOINDEX=1` into regression checks intended to verify production indexing; the SEO suite starts a separate process for preview verification. For a manual isolated preview, set `PERRO_NOINDEX=1` before `php -S localhost:8080 router.php`.

## Prepare the upload

After reviewed changes are committed and the working tree is clean, run `python tools/build-release.py C:/AI-work/perro-release.zip` with the actual output destination. Windows has the bundled Python path available through workspace dependencies. The builder reads **committed blobs** using a narrow allowlist: index/config/router, favicon, `.htaccess`, includes, assets and `storage/.htaccess`. It excludes production datasets/uploads/settings, environment files, Git, tests, business documents and research CSV/JSON. It refuses a dirty tree and outputs a SHA-256 sidecar. Runtime hosting requires PHP/Apache, not Python or Node.

Inspect the ZIP file list and checksum before uploading. It has `index.php` at its root. `config.php` contains repository defaults and environment variable names, not production secret values; reconcile any production customizations before replacing it. The ZIP contains no `storage/data` or `storage/uploads`; startup initializes missing datasets as already documented, never substitute an empty directory for existing data.

## Authorized Hostinger upload, later

1. Confirm real account, domain, document root, selected Git branch and whether a merge triggers automatic deployment. Do not assume this PR deploys on merge.
2. Preserve private backups of code, production configuration and **complete storage/settings/uploads**, outside all public document roots. Pause form/admin writes for the code replacement; keep any transaction journal for matching-code recovery.
3. Upload/extract the checked ZIP to the actual Perro root with index.php directly at the root. Replace complete runtime PHP/includes/CSS together. Preserve all production data, custom configuration and environment. Review `REDEPLOY.md` and `LEGAL-RELEASE.md` for existing safeguards.
4. Ensure PHP 8.1+, GD/FreeType as appropriate, optional EXIF, Apache rewrites and protected writable storage. New `includes/editorial.php` must be uploaded together with the updated fingerprint code; omitting it breaks the release.
5. Set `PERRO_NOINDEX=1` for staging only. Confirm it is absent/disabled on production before reopening public indexing. Production robots allows public routes; private route rules are guidance, not access control.
6. Keep `PERRO_MASCOTA_GUIDES_ENABLED` disabled until Mascota is deployed and all five care URLs in [the handoff](../../seo/2026-10-09/MASCOTA-HANDOFF.md) have been checked. Then enable it explicitly; review both visible links and owner disclosure.
7. Compare the real `X-Perro-Release` with the isolated exact-revision fingerprint recorded in the manual. Run `node tools/check-release.cjs https://perro.com.py perro-EXPECTED` with the actual expected value; it is a read-only check, not full validation.
8. Verify 17 public static routes, individual real authorized notices, publishing/moderation/reporting, active/ended visibility and HTTP status, assets and mobile layout. Confirm `/docs/`, storage, config, includes and tools are inaccessible under actual Apache; check CSV/JSON docs explicitly. Check robots/sitemap/canonicals and preview noindex on the real staging host.
9. Reopen writes only after existing admin login and one real authorized workflow have been verified. Do not publish test dogs. Search Console setup/submission requires separate authorized account work.

This update changes no dataset shape or policy consent versions; no production migration or maintenance command is required for these SEO guides. Existing maintenance/backup work remains separately documented and requires real server configuration.

## Rollback

Pause writes. Keep a separate copy of current production data, settings, images and any transaction journal. If a journal exists, allow the matching current code to finish recovery before switching code versions; do not delete it. Restore the complete previous runtime code/assets/config together, preserving legitimate newer submissions. These editorial changes need no data rollback. Remove an optional Mascota link flag if the destination is unavailable. Confirm release header, login, publishing, private-route denial and actual notice visibility before reopening. Do not blindly restore old storage and lose submissions.
