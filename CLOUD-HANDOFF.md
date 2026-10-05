# Continue development

Source: archived live-matching v1, from C:\Users\anton\Documents\Paraguay-Local-Site\_archive\site-variants\perro-com-py-v1-original.

The current local perro-com-py folder was a different revision and was not imported. This baseline matches the public site verified on 2026-10-05, rather than later local revisions.

Run `php -S localhost:8080 router.php` with PHP 8.1+. There is no dependency installation or build step. Follow AGENTS.md for checks and secret setup. Initial admin login needs PERRO_ADMIN_PASSWORD_SHA256; runtime storage starts empty and is ignored by Git. The bootstrap creates empty JSON datasets on first request.

Private production data and current admin settings require a separate Hostinger backup. This repository is a public-build baseline, not a full server backup. The original admin credential hash was replaced by an environment lookup; public pages are unaffected.
