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
- Each new submission stores the accepted terms/privacy versions, timestamp and specific confirmations/optional publication choices. No new IP tracking is added.
- Expired/withdrawn image URLs do not deliver photos; new media responses are no-store. Existing browser/CDN copies from older deployments cannot be recalled by this code change.
- Internal Markdown documentation is blocked by .htaccess and the development router.

## Requests and record handling

When an owner requests correction, withdrawal or deletion, verify their relationship to the listing through the existing private contact and reference. A reference alone is not identity verification. Do not request ID documents through a public report form.

The current admin can withdraw the complete listing. Name/phone-only correction and deletion remain manual operations for an authorized operator until an editor/privacy-management workflow is implemented. Withdraw first when permission is revoked; do not leave unauthorized details public while arranging a correction. Keep a minimal record of the request and action, and inspect linked submissions, published records, photos, reports and relevant backups. There is no automatic data purge in this release; the privacy page states this explicitly. Decide and implement retention periods and backup expiry based on actual needs/obligations.

## Deployment and remaining launch work

Do not deploy from this document alone. Verify Hostinger's deployed branch, webhook/auto-deploy behavior and destination. Back up production storage, uploads, settings and config. Preserve the existing admin password settings or provision the environment hash. Never replace production storage with local test files.

The full admin editor/photo review, duplicate approval, closed-listing lifecycle, upload errors, durable abuse controls and renewal/owner notifications from the audit are still outstanding. This legal/privacy patch does not mark the whole product launch-ready.

Before release, run syntax checks over all PHP files, the 14-route smoke test, and isolated tests for private/default/opt-in names, forged permissions, consent version mismatch, legacy names and expired photos. Confirm server GD and upload/post/memory settings. New deployed media rules do not purge historic CDN caches.

## Future donations

Public anonymity must be the initial choice in any future support form. Recognition consent, amount-publication consent and newsletter consent are separate. A public name or business logo can appear only after authorized, verified funding; it can later be removed. Financial records may still identify a payer. Identify the recipient, purpose, tax/documentation treatment, management budget and cancellation/refund rules before collecting funds. Adoption approval/publication/access never depends on contributions. Advertisements must be accounted for according to their real commercial nature.
