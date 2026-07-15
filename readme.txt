=== Integrate WPForms with Mattermost ===
Contributors: billerickson
Tags: wpforms, mattermost, automation, webhook
Requires at least: 6.6
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send configurable WPForms submission messages to Mattermost through a reliable self-hosted queue.

== Description ==

Create multiple conditional Mattermost feeds for each WPForms form. Messages support WPForms Smart Tags and are captured before asynchronous delivery. The plugin includes encrypted queue storage, retries, duplicate protection, saved-entry previews, confirmed test sends, diagnostics, and WP-CLI recovery. Imported feeds start disabled and credentials are not included in form exports.

== Installation ==

1. Upload and activate the plugin.
2. Connect a Mattermost bot under WPForms > Settings > Integrations > Mattermost.
3. Edit a WPForms form and open Marketing > Mattermost.
4. Add one or more Mattermost connections for the form.
5. Validate feeds in shadow mode before enabling live delivery.

== Changelog ==

= 1.1.0 =
* Refactored account setup and form connections to use the native WPForms provider interface.
* Moved queue operations to WPForms > Mattermost Logs.
* Added automatic migration for 1.0 form feeds.

= 1.0.0 =
* Initial release.
