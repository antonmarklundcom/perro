# Follow-up review — 9 October 2026

Reviewed the prepared draft releases after the initial implementation. These changes remain on Perro draft PR11 and Mascota draft PR21; neither is merged or deployed. All changing tests used synthetic local copies. No SQL migration or production data operation is justified by this review.

| Confirmed Perro issue | Resulting behavior |
| --- | --- |
| Loss of the entire data directory could appear fresh because its marker was lost too | A separate private storage/installation.json marker survives data loss; installed directories/state must exist before startup can recreate anything |
| Interrupted fresh setup after one empty dataset could not resume | A durable initializing phase permits only validated known empty scaffolding to resume; nonempty/unknown storage fails |
| A verified backup with no uploaded files omitted uploads, preventing startup | Restore verification creates only fixed data/uploads directories after integrity checks, then validates startup without executing archived code |
| The first 20 blocked cleanup jobs starved later deletions | Attempted failures rotate behind unattempted jobs, retaining attempts/idempotent retries |
| 100 saved public slugs could exceed web-server request-line limits | The browser uses sequential batches of at most 20 and encoded URLs at most 2,400 characters, fencing stale responses; existing expanded slugs through 512 characters remain supported |

The saved-request reproduction uses a simulated 414 response boundary with actual Chrome and local PHP responses. Apache's [LimitRequestLine documentation](https://httpd.apache.org/docs/2.4/mod/core.html#limitrequestline) lists the default 8,190-byte limit. Actual Hostinger configuration remains unverified.

| Follow-up verification | Result |
| --- | --- |
| Storage/recovery/claims/cleanup/backups, scoped synthetic E: copies | PASS 81 checks, expanded from 57 |
| Saved-state/RSS API/privacy | PASS 23 checks |
| Actual Chrome at 375px/1440px, including 100 saved references and stale-response clearing | PASS 43 checks, expanded from 37 |
| Changed PHP and JavaScript syntax, independent focused diff reviews | PASS; no further confirmed defect in reviewed storage or saved-request changes |

The initial b3280bd full local suite and its hosted CI remain recorded in [VERIFICATION.md](VERIFICATION.md). The current commit's hosted CI and final extracted code-only ZIP are recorded separately in the handoff/manually maintained project register, avoiding a self-referential committed release hash. Fresh isolated package initialization must produce ten empty datasets, schema 2 and an installed marker; the upload excludes all runtime data/markers.

Mascota's paired fix accepts reordered JSON keys without accepting missing/unknown counter keys and preserves the contact no-referrer response under Apache via setifempty. Its current verification records 49 commercial checks (three consecutive strict 24-worker passes after one initial concurrency failure), three release/checker tests and 231 package checks. Actual Apache/FastCGI behavior remains unverified. Commercial gates stay disabled; clicks remain separate from confirmed outcomes and collected money.

Use the [status-first Claude prompt](CLAUDE-STATUS-FIRST.txt) for an independent data audit. It must check implemented/merged/deployed state and stop if no confirmed gap remains. Production storage completeness, actual hosting ACL/filesystem semantics, real backup recovery, mail/cron, and clinic permission remain owner work. Losing the entire storage root together with all markers cannot be distinguished from a new installation by files in that root; retain external private backups and deployment provenance. No runtime data was inspected, migrated or replaced.
