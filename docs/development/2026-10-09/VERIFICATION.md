# Local implementation verification — 9 October 2026

Branch `codex/reliability-retention-20261009`, based on merged main `46b3297e8995b85e00d38f06272f6d96ed604a03`. Results apply to the reviewed implementation committed with this report. All mutation, account, delivery and recovery checks used disposable synthetic copies; no production data, real recipient or hosting account was used.

| Check | Result |
| --- | --- |
| PHP 8.3.33 syntax | PASS 24 files |
| Development/browser JavaScript syntax | PASS all tool CJS and application JS |
| Privacy without image functions | PASS 171 assertions |
| Privacy with GD and EXIF | PASS 234 assertions |
| SEO and public visibility | PASS 355 assertions; 17 indexable static routes |
| Existing audit suite, including delayed notification retry | PASS 73 assertions |
| Storage, interruption, claims, cleanup and backup drills | PASS 57 assertions |
| Team permissions and scoped owner links | PASS 71 assertions |
| Saved references and RSS privacy | PASS 23 assertions |
| Actual Chrome mobile/desktop interactions | PASS 37 assertions |

Audited notification retry expectations now match the deliberate backoff. Storage checks include concurrent fake workers, stale acknowledgements, retry exhaustion, corrupted/missing/duplicate datasets, read-only diagnostic source hashes, interrupted multi-file replacement/replay, valid archived relationships, cleanup failure/retry, backup tampering/path traversal, source-target rejection and incomplete backup removal. Browser checks use 375px and 1440px viewports and cover save clicks/keyboard focus, slug-only local storage, cross-tab updates, offline removal/clear, current withdrawn states, noscript, admin restrictions and owner forms.

The PC encountered a memory-allocation failure while independent suites were run in parallel. Remaining no-image, audit and syntax suites were rerun sequentially and passed; the interrupted results are not counted as successful. The browser suite was rerun after the saved-page wrapper correction. Synthetic public screenshots were visually reviewed.

## Repeat locally

Use PHP 8.3 with ZIP; use GD/EXIF for the image suite. On Windows set `PERRO_PHP_BIN` to the verified PHP executable. `PERRO_PHP_GD_DIR`/`PERRO_PHP_EXIF=1` load the extensions for the privacy image suite. The no-image suite uses `PERRO_PHP_DISABLE_GD=1`. Each test creates its own isolated copy.

```sh
node tools/privacy-smoke.cjs
node tools/seo-smoke.cjs
node tools/audit-smoke.cjs
node tools/storage-smoke.cjs
node tools/access-owner-smoke.cjs
node tools/retention-smoke.cjs
```

The optional local `tools/retention-browser.cjs` requires Playwright and Chrome supplied by the development environment; neither is a runtime or upload dependency. The relevant PR workflow runs the PHP suites; hosted CI status is checked separately after publishing the PR.

## Limits and owner work

No new SQL engine/migration is justified. Deployment performs a guarded, additive JSON schema-2 setup for complete legacy storage; audit a private isolated copy first. This branch is not production-deployed. Real storage completeness, filesystem/ACL semantics, private backup paths/off-site copies, delivery transport/cron, owner-contact verification and deployed mobile/admin behavior remain unverified. New owner drafts are manual and no notification was sent. Existing adoption/contact/consent and commercial defaults remain authoritative.
