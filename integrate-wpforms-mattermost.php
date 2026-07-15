<?php
/**
 * Plugin Name:       Integrate WPForms with Mattermost
 * Plugin URI:        https://github.com/CultivateWP/Integrate-WPForms-Mattermost
 * Description:       Send configurable WPForms submission messages to Mattermost reliably.
 * Version:           1.1.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Bill Erickson
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       integrate-wpforms-mattermost
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IWMM_VERSION', '1.1.1' );
define( 'IWMM_API_VERSION', '1.0.0' );
define( 'IWMM_FILE', __FILE__ );
define( 'IWMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'IWMM_URL', plugin_dir_url( __FILE__ ) );

$iwmm_autoload = IWMM_DIR . 'vendor/autoload.php';
if ( is_readable( $iwmm_autoload ) ) {
	require_once $iwmm_autoload;
}

if ( class_exists( IntegrateWPFormsMattermost\Plugin::class ) ) {
	IntegrateWPFormsMattermost\Plugin::instance()->boot();
}

register_activation_hook( __FILE__, array( IntegrateWPFormsMattermost\Plugin::class, 'activate' ) );

/**
 * Enqueue a Mattermost message through the durable public API.
 *
 * @param array<string,mixed> $request Message request.
 * @return int|WP_Error
 */
function iwmm_enqueue_message( array $request ) {
	if ( ! class_exists( IntegrateWPFormsMattermost\Plugin::class ) ) {
		return new WP_Error( 'iwmm_unavailable', __( 'Integrate WPForms with Mattermost is unavailable.', 'integrate-wpforms-mattermost' ) );
	}

	return IntegrateWPFormsMattermost\Plugin::instance()->messages()->enqueue( $request );
}

/**
 * Read a queued message status without exposing its encrypted body.
 *
 * @return array<string,mixed>|WP_Error
 */
function iwmm_get_message_status( int $message_id ) {
	if ( ! class_exists( IntegrateWPFormsMattermost\Plugin::class ) ) {
		return new WP_Error( 'iwmm_unavailable', __( 'Integrate WPForms with Mattermost is unavailable.', 'integrate-wpforms-mattermost' ) );
	}

	return IntegrateWPFormsMattermost\Plugin::instance()->messages()->public_status( $message_id );
}
