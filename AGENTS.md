# Perro development

PHP 8.1+ application for perro.com.py. No Node.js build or database is required.

This repository starts from the archived v1 build verified against production on 2026-10-05. Public rendered output matched all 14 checked routes, excluding dynamic form tokens/timestamps; all four referenced assets matched byte for byte.

## Local checks

- Syntax: `php -l index.php`, `php -l config.php`, `php -l router.php`, and each PHP file in includes/.
- Preview: `php -S localhost:8080 router.php` from the repository root.
- Check /, /perros, /dar-perro-en-adopcion, /perros-perdidos-paraguay, /como-funciona, /centros-de-adopcion, /seguridad, /terminos, /privacidad, /cachorros-en-adopcion, /perros-de-raza-en-adopcion, /admin, /robots.txt, /sitemap.xml.
- Use an isolated copy for form/admin tests: requests may initialize or write storage files.

## Secrets and production

`PERRO_ADMIN_PASSWORD_SHA256` is read from the process environment. No live credential is included. An empty value disables initial admin login. PHP does not automatically load .env files. Set the variable in the shell/server environment before starting PHP; use a SHA-256 hash of a strong local development password. Existing deployed storage/data/settings.json may override the initial admin hash.

Production submissions, uploads and settings are not included. Never commit storage data or credentials. Back up deployed storage and config.php before deployment. Replacing live storage destroys production state. No automatic deployment is configured by this import.

## Workflow

Fetch and check open PRs before editing. Work on a branch and open a PR. Never deploy or write production data without explicit authorization. Preserve Paraguay Spanish/voseo and truthful listings. No fabricated dogs, reviews or organizations.
