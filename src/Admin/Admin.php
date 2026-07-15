<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Admin;

use IntegrateWPFormsMattermost\Mattermost\Client;
use IntegrateWPFormsMattermost\MessageService;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use IntegrateWPFormsMattermost\Storage\MessageRepository;
use IntegrateWPFormsMattermost\WPForms\FeedListener;

final class Admin {
	public function __construct(
		private MessageRepository $repository,
		private ConnectionSettings $settings,
		private MessageService $messages,
		private FeedListener $listener
	) {}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_iwmm_save_connection', array( $this, 'save_connection' ) );
		add_action( 'admin_post_iwmm_refresh_channels', array( $this, 'refresh_channels' ) );
		add_action( 'admin_post_iwmm_retry_message', array( $this, 'retry_message' ) );
		add_action( 'admin_post_iwmm_preview_message', array( $this, 'preview_message' ) );
		add_action( 'admin_post_iwmm_test_send', array( $this, 'test_send' ) );
		add_filter( 'site_status_tests', array( $this, 'site_health_tests' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	public function menu(): void {
		add_options_page(
			__( 'WPForms Mattermost', 'integrate-wpforms-mattermost' ),
			__( 'WPForms Mattermost', 'integrate-wpforms-mattermost' ),
			'manage_options',
			'integrate-wpforms-mattermost',
			array( $this, 'page' )
		);
	}

	public function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$messages = $this->repository->recent();
		$channels = get_option( 'iwmm_channels_cache', array() );
		$preview  = get_transient( 'iwmm_preview_' . get_current_user_id() );
		delete_transient( 'iwmm_preview_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Integrate WPForms with Mattermost', 'integrate-wpforms-mattermost' ); ?></h1>
			<?php if ( isset( $_GET['iwmm_notice'] ) ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['iwmm_notice'] ) ) ); ?></p></div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Connection', 'integrate-wpforms-mattermost' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iwmm_save_connection">
				<?php wp_nonce_field( 'iwmm_save_connection' ); ?>
				<table class="form-table"><tbody>
				<tr><th><label for="iwmm-base-url"><?php esc_html_e( 'Mattermost URL', 'integrate-wpforms-mattermost' ); ?></label></th><td><input class="regular-text" type="url" id="iwmm-base-url" name="base_url" value="<?php echo esc_attr( $this->settings->base_url() ); ?>" <?php disabled( defined( 'IWMM_MATTERMOST_URL' ) ); ?>></td></tr>
				<tr><th><label for="iwmm-token"><?php esc_html_e( 'Bot token', 'integrate-wpforms-mattermost' ); ?></label></th><td><input class="regular-text" type="password" id="iwmm-token" name="token" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->settings->token() ? 'Configured; leave blank to retain' : '' ); ?>" <?php disabled( defined( 'IWMM_MATTERMOST_TOKEN' ) ); ?>></td></tr>
				</tbody></table>
				<?php submit_button( __( 'Save connection', 'integrate-wpforms-mattermost' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Preview a saved entry', 'integrate-wpforms-mattermost' ); ?></h2>
			<p><?php esc_html_e( 'Render a feed without sending it. WPForms entry storage is required.', 'integrate-wpforms-mattermost' ); ?></p>
			<?php if ( is_string( $preview ) && '' !== $preview ) : ?><div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Rendered preview:', 'integrate-wpforms-mattermost' ); ?></strong></p><pre style="white-space:pre-wrap"><?php echo esc_html( $preview ); ?></pre></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iwmm_preview_message"><?php wp_nonce_field( 'iwmm_preview_message' ); ?>
				<label><?php esc_html_e( 'Form ID', 'integrate-wpforms-mattermost' ); ?> <input type="number" min="1" name="form_id" required></label>
				<label><?php esc_html_e( 'Entry ID', 'integrate-wpforms-mattermost' ); ?> <input type="number" min="1" name="entry_id" required></label>
				<label><?php esc_html_e( 'Feed ID', 'integrate-wpforms-mattermost' ); ?> <input name="feed_id" required></label>
				<?php submit_button( __( 'Render preview', 'integrate-wpforms-mattermost' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Send a test', 'integrate-wpforms-mattermost' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iwmm_test_send"><?php wp_nonce_field( 'iwmm_test_send' ); ?>
				<table class="form-table"><tr><th><?php esc_html_e( 'Channel', 'integrate-wpforms-mattermost' ); ?></th><td><select name="channel_id" required><option value=""></option><?php foreach ( is_array( $channels ) ? $channels : array() as $channel ) : ?><option value="<?php echo esc_attr( (string) $channel['id'] ); ?>"><?php echo esc_html( (string) ( $channel['display_name'] ?? $channel['name'] ?? $channel['id'] ) ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th><?php esc_html_e( 'Message', 'integrate-wpforms-mattermost' ); ?></th><td><textarea class="large-text" name="message" required></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Confirmation', 'integrate-wpforms-mattermost' ); ?></th><td><label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I understand this will post to Mattermost.', 'integrate-wpforms-mattermost' ); ?></label></td></tr></table>
				<?php submit_button( __( 'Queue test message', 'integrate-wpforms-mattermost' ), 'secondary' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iwmm_refresh_channels">
				<?php wp_nonce_field( 'iwmm_refresh_channels' ); ?>
				<?php submit_button( __( 'Test connection and refresh channels', 'integrate-wpforms-mattermost' ), 'secondary' ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent messages', 'integrate-wpforms-mattermost' ); ?></h2>
			<table class="widefat striped"><thead><tr><th>ID</th><th><?php esc_html_e( 'Source', 'integrate-wpforms-mattermost' ); ?></th><th><?php esc_html_e( 'Channel ID', 'integrate-wpforms-mattermost' ); ?></th><th><?php esc_html_e( 'Status', 'integrate-wpforms-mattermost' ); ?></th><th><?php esc_html_e( 'Attempts', 'integrate-wpforms-mattermost' ); ?></th><th><?php esc_html_e( 'Updated', 'integrate-wpforms-mattermost' ); ?></th><th></th></tr></thead><tbody>
			<?php if ( array() === $messages ) : ?><tr><td colspan="7"><?php esc_html_e( 'No messages captured yet.', 'integrate-wpforms-mattermost' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $messages as $message ) : ?>
				<tr><td><?php echo esc_html( (string) $message['id'] ); ?></td><td><?php echo esc_html( (string) $message['source'] ); ?></td><td><code><?php echo esc_html( (string) $message['channel_id'] ); ?></code></td><td><?php echo esc_html( (string) $message['status'] ); ?></td><td><?php echo esc_html( (string) $message['attempts'] ); ?></td><td><?php echo esc_html( (string) $message['updated_at_gmt'] ); ?> UTC</td><td>
				<?php if ( in_array( $message['status'], array( 'dead', 'failed', 'retry_scheduled' ), true ) ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iwmm_retry_message&id=' . absint( $message['id'] ) ), 'iwmm_retry_message_' . absint( $message['id'] ) ) ); ?>"><?php esc_html_e( 'Retry', 'integrate-wpforms-mattermost' ); ?></a>
				<?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	public function save_connection(): void {
		$this->authorize( 'iwmm_save_connection' );
		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : $this->settings->base_url();
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		try {
			$this->settings->save( $base_url, $token );
			$this->redirect( 'Connection saved.' );
		} catch ( \Throwable $error ) {
			$this->redirect( sanitize_text_field( $error->getMessage() ) ?: 'Connection could not be saved.' );
		}
	}

	public function refresh_channels(): void {
		$this->authorize( 'iwmm_refresh_channels' );
		$client   = new Client( $this->settings );
		$response = $client->test_connection();
		if ( ! $response->successful() ) {
			$this->redirect( 'Connection test failed.' );
		}
		$channels = $client->channels();
		if ( is_wp_error( $channels ) ) {
			$this->redirect( 'Connected, but channels could not be loaded.' );
		}
		update_option( 'iwmm_channels_cache', $channels, false );
		$this->redirect( sprintf( 'Connection succeeded; %d channels cached.', count( $channels ) ) );
	}

	public function retry_message(): void {
		$id = absint( $_GET['id'] ?? 0 );
		$this->authorize( 'iwmm_retry_message_' . $id );
		$this->redirect( $this->messages->retry( $id ) ? 'Message queued for retry.' : 'Message could not be retried.' );
	}

	public function preview_message(): void {
		$this->authorize( 'iwmm_preview_message' );
		$result = $this->listener->preview( absint( $_POST['form_id'] ?? 0 ), absint( $_POST['entry_id'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['feed_id'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) {
			$this->redirect( $result->get_error_message() );
		}
		set_transient( 'iwmm_preview_' . get_current_user_id(), (string) $result, 5 * MINUTE_IN_SECONDS );
		$this->redirect( 'Preview rendered. It was not sent.' );
	}

	public function test_send(): void {
		$this->authorize( 'iwmm_test_send' );
		if ( empty( $_POST['confirm'] ) ) {
			$this->redirect( 'Test send was not confirmed.' );
		}
		$result = $this->messages->enqueue(
			array(
				'idempotency_key' => 'admin-test:' . wp_generate_uuid4(),
				'channel_id'      => sanitize_text_field( wp_unslash( $_POST['channel_id'] ?? '' ) ),
				'message'         => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
				'source'          => 'admin-test',
				'source_id'       => 'user-' . get_current_user_id(),
			)
		);
		$this->redirect( is_wp_error( $result ) ? $result->get_error_message() : 'Test message queued.' );
	}

	/** @param array<string,mixed> $tests @return array<string,mixed> */
	public function site_health_tests( array $tests ): array {
		$tests['direct']['iwmm_connection'] = array(
			'label' => __( 'Mattermost automation connection', 'integrate-wpforms-mattermost' ),
			'test'  => function (): array {
				$dead = $this->repository->count_by_status( 'dead' );
				$good = $this->settings->configured() && function_exists( 'sodium_crypto_secretbox' ) && 0 === $dead;
				return array(
					'label'       => $good ? 'Mattermost automation is healthy' : 'Mattermost automation needs attention',
					'status'      => $good ? 'good' : 'critical',
					'badge'       => array( 'label' => 'Automation', 'color' => 'blue' ),
					'description' => sprintf( '<p>%s</p>', esc_html( $good ? 'The connection is configured and no messages are dead-lettered.' : 'Check encryption, connection settings, and dead-lettered messages.' ) ),
					'test'        => 'iwmm_connection',
				);
			},
		);
		$tests['direct']['iwmm_entry_recovery'] = array(
			'label' => __( 'WPForms entry recovery', 'integrate-wpforms-mattermost' ),
			'test'  => function (): array {
				global $wpdb;
				$table = $wpdb->prefix . 'wpforms_entries';
				$good  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
				return array(
					'label'       => $good ? 'WPForms entry recovery is available' : 'WPForms entry recovery is unavailable',
					'status'      => $good ? 'good' : 'recommended',
					'badge'       => array( 'label' => 'Automation', 'color' => 'blue' ),
					'description' => '<p>' . esc_html( $good ? 'Saved entries can be reconciled after missed hooks.' : 'Live capture works, but WPForms Lite cannot recover a submission after a missed hook because it does not store entries.' ) . '</p>',
					'test'        => 'iwmm_entry_recovery',
				);
			},
		);
		return $tests;
	}

	public function dependency_notice(): void {
		if ( class_exists( \WPForms::class ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Integrate WPForms with Mattermost requires WPForms for form feeds. The generic enqueue API remains available.', 'integrate-wpforms-mattermost' ) . '</p></div>';
	}

	private function authorize( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this integration.', 'integrate-wpforms-mattermost' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private function redirect( string $notice ): never {
		wp_safe_redirect( add_query_arg( 'iwmm_notice', rawurlencode( $notice ), admin_url( 'options-general.php?page=integrate-wpforms-mattermost' ) ) );
		exit;
	}
}
