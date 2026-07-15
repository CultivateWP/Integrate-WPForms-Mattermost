# Integrate WPForms with Mattermost Implementation Plan

Build a public WordPress plugin that lets administrators define multiple conditional Mattermost feeds inside each WPForms form. Each feed selects a channel and Smart Tag-aware Markdown template and can be disabled, shadowed, or live.

Use WPForms' native provider framework: connection credentials appear under WPForms Settings > Integrations, and per-form connections appear under the Marketing panel. Operational queue history remains a separate WPForms submenu.

The plugin captures successful submissions, renders messages immediately, stores encrypted queue records with deterministic idempotency keys, and performs Mattermost calls asynchronously through Action Scheduler. It provides bounded retries, remote reconciliation after ambiguous timeouts, redacted attempt history, Site Health diagnostics, WP-CLI recovery, privacy erasure, and retention cleanup.

The stable public API is `iwmm_enqueue_message()`, with status lookup and `iwmm_message_succeeded` / `iwmm_message_failed` completion hooks. No unauthenticated REST enqueue endpoint is provided.

Acceptance requires duplicate-hook protection, multiple and conditional feeds, shadow/live behavior, Mattermost error classification, encryption and redaction, admin diagnostics, CLI operations, automated tests, and a reproducible public release ZIP containing no organization-specific data.

## Stable identity

- Slug/main file: `integrate-wpforms-mattermost` / `integrate-wpforms-mattermost.php`
- Namespace/prefix: `IntegrateWPFormsMattermost` / `iwmm_`
- Tables: `{prefix}iwmm_messages`, `{prefix}iwmm_attempts`
- Queue group and CLI namespace: `integrate-wpforms-mattermost`
- API version: 1.0.0, with no arbitrary REST enqueue endpoint

## Delivery path

After successful WPForms processing, evaluate every enabled feed, render Smart Tags immediately, derive a stable per-form/submission/feed idempotency key, encrypt the rendered body, persist it, and schedule asynchronous delivery. A deterministic non-sensitive UUID is stored in Mattermost post properties. After an ambiguous timeout, query recent channel posts for that UUID before resending.

Imported/duplicated feeds start disabled and carry a non-secret site-origin fingerprint so a form moved between sites cannot post until an administrator explicitly saves its disabled state and re-enables it. WPForms Lite can capture live requests, but Site Health warns that missed-hook recovery and saved-entry previews require entry storage.

## Operations and release

- Admin connection test/channel discovery, saved-entry preview, explicit test-send confirmation, history, and retry
- Site Health checks for encryption, connection/dead letters, and WPForms entry recovery
- CLI status, message listing, retry, shadow promotion, reconciliation, connection test, and cleanup
- Successful payload purge at 30 days, failed payload purge at 90 days, metadata purge at one year, and WordPress privacy erasure
- PHP 8.1–8.4 CI with syntax, coding standards, PHPStan, unit tests, secret scanning, and deterministic ZIP build
- GPL-2.0-or-later GitHub release 1.0.0

Release acceptance is not complete until the ZIP is installed with WPForms and a Mattermost test server and duplicate, throttling, authentication, timeout-reconciliation, retention, and CLI recovery cases are exercised.
