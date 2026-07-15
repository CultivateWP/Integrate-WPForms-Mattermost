# Integrate WPForms with Mattermost

A self-hosted WordPress plugin for sending configurable WPForms submission messages to Mattermost without Zapier.

## Features

- Multiple Mattermost feeds per form
- Native WPForms provider UI for account and form connections
- WPForms Smart Tag message templates
- Conditional all/any delivery rules
- Disabled, shadow, and live modes
- Encrypted durable queue records and bounded retries
- Duplicate-submission protection and ambiguous-timeout reconciliation
- Admin diagnostics, Site Health tests, retention cleanup, and WP-CLI recovery
- Saved-entry previews and explicitly confirmed test sends
- Form export/import safety: credentials stay site-wide and moved feeds start disabled
- A stable PHP API for messages originating outside WPForms

## Requirements

- WordPress 6.6+
- PHP 8.1+ with sodium
- WPForms Lite or Pro 1.9.5+ for form feeds
- A Mattermost bot account with access only to the intended channels

## Installation

Install a release ZIP as a normal WordPress plugin. Configure the Mattermost URL and bot token under **WPForms → Settings → Integrations → Mattermost**. Then edit a form and add one or more connections under **Marketing → Mattermost**.

Queue history, saved-entry previews, channel refresh, confirmed test sends, and retries are available under **WPForms → Mattermost Logs**.

Production sites should run Action Scheduler from real cron:

```cron
* * * * * wp action-scheduler run --group=integrate-wpforms-mattermost --batches=5
*/5 * * * * wp cron event run --due-now
```

New feeds default to disabled. Use shadow mode to validate captures without posting before switching a feed to live.

## Configuration constants

The admin UI can store encrypted connection details, or production can define:

```php
define( 'IWMM_MATTERMOST_URL', 'https://mattermost.example.com' );
define( 'IWMM_MATTERMOST_TOKEN', 'replace-with-secret' );
define( 'IWMM_ENCRYPTION_KEY', 'base64-encoded-32-byte-key' );
```

Never commit those values.

## PHP API

```php
$message_id = iwmm_enqueue_message(
	array(
		'idempotency_key' => 'invoice:123:paid:2026-07-15T16:00:00Z',
		'channel_id'      => 'channel-id',
		'message'         => 'A deterministic message',
		'source'          => 'example-plugin',
		'source_id'       => '123',
	)
);
```

Use `iwmm_get_message_status()` to read redacted status. Completion actions are `iwmm_message_succeeded` and `iwmm_message_failed`.
The current contract is exposed as `IWMM_API_VERSION` 1.0.0. Consumers should accept compatible 1.x versions.

## Privacy

Rendered messages are encrypted in the WordPress database while queued. Successful payloads are purged after 30 days, failed payloads after 90 days, and redacted metadata after one year. Mattermost retains delivered posts according to its own policy.

File-upload Smart Tags may render text or URLs into a message, but the plugin never downloads or attaches WPForms uploads.

## Development

```bash
composer install
composer test
composer syntax
composer lint
composer analyse
```

Build a release with `./scripts/build.sh`.
