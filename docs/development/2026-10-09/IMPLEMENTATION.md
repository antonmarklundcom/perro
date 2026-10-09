# Reliability, team access and return visits — 9 October 2026

This branch implements the next ten-task portfolio backlog: Perro storage diagnostics/guards, durable cleanup, notification worker coordination, verified backups, team roles, browser-saved notices, RSS and owner confirmation links; Mascota's paired commercial branch implements gated clinic contacts and private business reporting. No SQL engine conversion or production data operation is required by these changes. Deployment remains a separate owner action.

## Storage status before further work

Perro remains PHP with private JSON. The previous release already had locks, transactional replay, backup creation and corruption checks. Concrete remaining gaps were initialization of any missing dataset, non-durable file-cleanup retry and unclaimed notification work; this release fixes them. Run the [status-first Claude prompt](CLAUDE-STATUS-FIRST.txt) for an independent audit; do not repeat completed setup/migrations.

`storage/data/storage-state.json` records schema version 2. Fresh empty installations initialize ten datasets. Complete unversioned/v1 installations validate existing eight datasets before adding only `owner_actions` and `cleanup`, then record the version. Existing bytes/settings remain intact. Marked installations with a missing required file or incompatible state fail 503. Partial installations need private investigation; no empty replacement or implicit reset. Journal replay validates all pending dataset payloads before replacing files. Synced temporary files and serialized replacement improve interruption recovery; no promise is made for every filesystem/power-loss failure.

The diagnostic command bypasses bootstrap and does not create directories/locks/files, normalize data on disk or replay a journal. Exit 0 means no issues; exit 2 means inspect its codes. Private reports contain counts/record IDs, never contact values.

```sh
php /REAL/PERRO/tools/maintenance.php --doctor
```

Run against a private isolated copy for initial audit. A pending transaction is reported; only normal startup with matching reviewed code performs recovery. Never delete the journal to hide errors.

## Cleanup and notifications

Permanent deletion removes associated records, owner tokens and public access in one transaction and creates a private cleanup job. Failed filesystem work remains pending; the panel warns. Retry is safe and idempotent. Completed jobs discard folder identifiers. Public/private media authorization remains authoritative even while pixels await cleanup.

```sh
php /REAL/PERRO/tools/maintenance.php --cleanup
```

Notifications use a stable claim, a five-minute lease, at most five attempts, and exponential retry delay starting at 60 seconds. Each dispatcher handles up to 50 items; acknowledgement checks the same claim. Only fake transports were used in tests. Existing sender/recipient and cron prerequisites remain required. Delivery after a crash can duplicate because PHP mail lacks an idempotent external delivery protocol; acceptance does not prove inbox delivery. Review failed jobs and actual transport evidence before any retry/reset; do not hand-edit JSON during service.

## Verified private backups

Storage backup now includes a manifest of hashes, byte sizes, counts, data version and release fingerprint; a checksum sidecar accompanies the ZIP. Rotation retains 14 completed archives. Settings/accounts, private uploads and ancillary security files remain private; code/config backups are still separate deployment requirements. The tool cannot identify every other domain's document root: the owner must choose genuinely private paths and off-site copies.

```sh
php /REAL/PERRO/tools/maintenance.php --backup-dir=/REAL/PRIVATE/perro-backups
php /REAL/PERRO/tools/maintenance.php --verify-backup=/REAL/PRIVATE/perro-backups/EXACT.zip --restore-dir=/REAL/PRIVATE/EMPTY-DRILL
```

The restore parent must already exist; the target must be empty and outside the source installation. Verification rejects traversal, links, duplicate entries, mismatched hashes/counts and invalid references. It extracts only private storage into the drill directory, never executes archived files or replaces source data. A successful drill does not authorize live restoration. Retain damaged/partial verification output privately for investigation; choose a new empty destination for reruns. Legacy ZIPs without the new manifest remain historical backups, but cannot use this new verification command.

## Team roles and owner links

Primary `admin` controls invitations/roles. Existing and malformed/unknown team roles default to moderator. Moderators review/edit/status/photos and issue owner links; managers additionally export private CSV and permanently delete. Neither team role manages accounts. Role changes are checked from current private settings; routes enforce capabilities and hide unavailable tools.

Issue an owner link only after verifying the authoritative private contact. The link is scope-specific, valid for 24 hours, single-use, and tied to an approved source revision/mapping. The admin response provides the manual draft immediately; it is not sent automatically. The app persists hashes only, including its public session state. Manual delivery channels and server access logs can retain URLs, so protect them and do not paste links in public content. GET only previews and strips the bearer token from the URL; POST requires CSRF. Confirmation extends current available notice validity; withdrawal immediately removes public availability and adds private audit history. A token cannot approve pending content, grant consent, change contact fields or reopen ended/expired notices. Reissue after substantive source edits and recheck contact.

## Saved notices and RSS

`/guardados` is noindex. With JavaScript, the browser stores up to 100 public slugs under `perro-saved-dogs:v1`, without accounts, photos or contacts. Remove/clear works offline; available states are rechecked on return and across tabs. Unavailable notices display a generic message without dog/contact links. Saving is not reservation. Without JavaScript, active notices remain browsable.

`/avisos.rss` is an RSS feed of up to 100 current public notices using original publication dates and minimal public name/type/city/URL. It excludes pending, expired, withdrawn and completed records, images and contacts. Validators are recalculated against current visibility before 304. Readers may retain downloaded copies; the current listing remains authoritative. Neither utility is a new indexable SEO landing page.

## Release and rollback

Deploy all root/includes/assets files together with the existing code-only builder, preserving production storage/settings/photos. New includes participate in the release fingerprint. `/docs`, CLI code and private storage remain denied publicly. Take a complete private backup and pause writes during release replacement. Schema-2 startup adds known private datasets/version metadata; no SQL/DNS/hosting conversion. For rollback, preserve current data and any journal, complete recovery with matching code, and assess newer owner-link/cleanup/claim states before older code is allowed to write. Do not blindly revert to a writer that does not recognize current data.

See OPERATIONS.md, REDEPLOY.md and the implementation verification report. Runtime cron, mail delivery, real backup/off-site locations, real hosting filesystem semantics and deployment remain unverified owner work.
