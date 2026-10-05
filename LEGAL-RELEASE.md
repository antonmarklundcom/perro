# Legal/privacy release: 2026-10-05

This is an implementation of platform rules and privacy safeguards, not a declaration of legal clearance. It introduces no payment processing or donor registry.

## Configure before release

Set the PHP process environment, or set these public values explicitly in config.php:

- PERRO_OPERATOR_NAME: actual legal operator's full name or registered entity name.
- PERRO_OPERATOR_ADDRESS: suitable public/business contact address. Do not invent an address or expose a private residence without agreement.
- PERRO_PRIVACY_EMAIL: monitored contact email.
- PERRO_OPERATOR_RUC: only the actual applicable RUC; optional if not applicable.

The existing project WhatsApp is the fallback contact. Confirm its ownership and monitor it. PHP does not automatically load .env. Confirm these values are present in rendered terms/privacy on Hostinger, not merely in a terminal session. The pages omit unset operator fields; an unset field does not constitute a complete legal notice. The operator identity/address/contact must be settled before calling this release ready for public launch.

Check the final terms/privacy with a competent Paraguayan reviewer, including the actual operator, hosting arrangement, custody/rescue activities, municipal rules and future fundraising status. Ley 7513/2025 is the animal-welfare framework. Ley 7593/2025 has a 24-month commencement period after official publication. No universal waiver of legal rights, certification of dogs, nonprofit status, tax deduction or payment method is claimed.

## Implemented behavior

- A fresh dog form selects No mostrar mi nombre. Full submitter name remains private. An explicit optional public alias/name is independent of private full name and public WhatsApp.
- Public names require an explicit boolean permission. Legacy records without that permission stay private, even if an old contact_name field holds the private name.
- Acceptance checks require value 1, including no sale/breeding/deposit/compulsory donation confirmation. Missing, false or old policy versions are rejected before storage. The session CSRF rejection returns 419.
- Each new submission stores the accepted terms/privacy versions, timestamp and specific confirmations/optional publication choices. Security controls store pseudonymous IP-derived counters, disclosed in the privacy page; hosting logs may separately contain IPs.
- Expired/withdrawn image URLs do not deliver photos; new media responses are no-store. Existing browser/CDN copies from older deployments cannot be recalled by this code change.
- Internal Markdown documentation is blocked by .htaccess and the development router.

## Requests and record handling

When an owner requests correction, withdrawal or deletion, verify their relationship to the listing through the existing private contact and reference. A reference alone is not identity verification. Do not request ID documents through a public report form.

The admin editor can correct the complete listing, inspect private pending photos, add authorized photos, remove photos and revoke public name/WhatsApp permissions. Approved submissions and public records update together. Administrators cannot grant new public-contact consent for an owner. Verify the request through the existing private contact; withdraw promptly when appropriate. Revocations and moderation actions are recorded privately. A complete deletion request still requires manual inspection of linked submissions, public records, photos, reports and relevant backups. There is no automatic data purge; decide retention periods and backup expiry based on actual needs/obligations.

## Deployment and remaining launch work

Do not deploy from this document alone. Verify Hostinger's deployed branch, webhook/auto-deploy behavior and destination. Back up production storage, uploads, settings and config. Preserve the existing admin password settings or provision the environment hash. Never replace production storage with local test files.

This release adds the complete admin editor/photo review, repeat-safe approval, active-listing lifecycle, explicit owner-confirmed renewal, truthful upload errors, persistent login/submission/report limits, credential-change session revocation and WhatsApp follow-up templates. Owners contact the team using their reference; there is no automatic email, authenticated owner dashboard or automatic retention purge. Donations/payment processing, verified organizations, advanced search and richer reporting remain future work. Server configuration and the actual legal notice must still be verified before public launch.

Follow REDEPLOY.md. Run syntax checks over all PHP files and node tools/privacy-smoke.cjs on a disposable copy. The script includes the 14-route smoke test, permissions, photo uploads when GD is enabled, editing, lifecycle, limits, interrupted storage recovery and six simultaneous PHP approval workers. Confirm server GD and upload/post/memory settings. New media rules do not purge historic CDN caches.

## Future donations

Public anonymity must be the initial choice in any future support form. Recognition consent, amount-publication consent and newsletter consent are separate. A public name or business logo can appear only after authorized, verified funding; it can later be removed. Financial records may still identify a payer. Identify the recipient, purpose, tax/documentation treatment, management budget and cancellation/refund rules before collecting funds. Adoption approval/publication/access never depends on contributions. Advertisements must be accounted for according to their real commercial nature.
