<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms\Provider\Settings;

use IntegrateWPFormsMattermost\Mattermost\Client;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use IntegrateWPFormsMattermost\WPForms\Provider\Account;
use IntegrateWPFormsMattermost\WPForms\Provider\Core;
use Throwable;

final class PageIntegrations extends \WPForms\Providers\Provider\Settings\PageIntegrations {
	public function __construct( Core $core, private ConnectionSettings $settings, private Account $account ) {
		parent::__construct( $core );
	}

	public function ajax_connect(): void {
		parent::ajax_connect();

		// The WPForms nonce and capability checks run in the parent method.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['data'] ) ? wp_parse_args( wp_unslash( $_POST['data'] ) ) : array();
		$url  = defined( 'IWMM_MATTERMOST_URL' ) ? $this->settings->base_url() : esc_url_raw( (string) ( $data['base_url'] ?? '' ) );
		$token = defined( 'IWMM_MATTERMOST_TOKEN' )
			? $this->settings->token()
			: sanitize_text_field( (string) ( $data['token'] ?? '' ) );
		if ( '' === $token ) {
			$token = $this->settings->token();
		}

		try {
			if ( ! $this->settings->accepts_url( $url ) ) {
				throw new \InvalidArgumentException( __( 'Enter a valid HTTPS Mattermost URL without /api/v4.', 'integrate-wpforms-mattermost' ) );
			}
			if ( '' === $token ) {
				throw new \InvalidArgumentException( __( 'Enter a Mattermost bot token.', 'integrate-wpforms-mattermost' ) );
			}

			$client = new Client( $this->settings, $url, $token );
			if ( ! $client->test_connection()->successful() ) {
				throw new \RuntimeException( __( 'Mattermost could not authenticate this bot token.', 'integrate-wpforms-mattermost' ) );
			}
			$channels = $client->channels();
			if ( is_wp_error( $channels ) ) {
				throw new \RuntimeException( __( 'Mattermost connected, but its available channels could not be loaded.', 'integrate-wpforms-mattermost' ) );
			}

			$this->settings->save( $url, defined( 'IWMM_MATTERMOST_TOKEN' ) ? '' : $token );
			update_option( 'iwmm_channels_cache', $channels, false );
			$label = sanitize_text_field( (string) ( $data['account_name'] ?? '' ) );
			$this->account->save_marker( '' !== $label ? $label : 'Mattermost' );

			$accounts = $this->account->all();
			ob_start();
			$this->display_connected_account( Account::ID, $accounts[ Account::ID ] ?? array( 'label' => 'Mattermost', 'date' => time() ) );
			wp_send_json_success( array( 'html' => ob_get_clean() ) );
		} catch ( Throwable $error ) {
			wp_send_json_error( array( 'error_msg' => esc_html( $error->getMessage() ) ) );
		}
	}

	public function ajax_disconnect(): void {
		if ( ! check_ajax_referer( 'wpforms-admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'error_msg' => __( 'Your session expired. Please reload the page.', 'integrate-wpforms-mattermost' ) ) );
		}
		if ( ! wpforms_current_user_can() ) {
			wp_send_json_error( array( 'error_msg' => __( 'You do not have permission to manage this connection.', 'integrate-wpforms-mattermost' ) ) );
		}
		if ( $this->settings->fully_managed_by_constants() ) {
			wp_send_json_error( array( 'error_msg' => __( 'This connection is managed by IWMM_* constants in wp-config.php.', 'integrate-wpforms-mattermost' ) ) );
		}

		$this->account->remove();
		wp_send_json_success();
	}

	protected function display_add_new(): void {
		if ( ! $this->settings->configured() ) {
			parent::display_add_new();
			return;
		}

		if ( $this->settings->fully_managed_by_constants() ) {
			echo '<p>' . esc_html__( 'The Mattermost connection is managed by IWMM_* constants in wp-config.php.', 'integrate-wpforms-mattermost' ) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Disconnect the current Mattermost account before replacing its URL or bot token.', 'integrate-wpforms-mattermost' ) . '</p>';
	}

	protected function display_add_new_connection_fields(): void {
		?>
		<p>
			<label>
				<input type="text" name="account_name" placeholder="<?php esc_attr_e( 'Connection name (optional)', 'integrate-wpforms-mattermost' ); ?>">
			</label>
		</p>
		<p>
			<label>
				<?php if ( defined( 'IWMM_MATTERMOST_URL' ) ) : ?>
					<input type="url" value="<?php echo esc_attr( $this->settings->base_url() ); ?>" disabled>
				<?php else : ?>
					<input type="url" name="base_url" class="wpforms-required" required placeholder="https://mattermost.example.com">
				<?php endif; ?>
			</label>
		</p>
		<p>
			<label>
				<?php if ( defined( 'IWMM_MATTERMOST_TOKEN' ) ) : ?>
					<input type="password" value="configured-in-wp-config" disabled>
				<?php else : ?>
					<input type="password" name="token" class="wpforms-required" required autocomplete="new-password" placeholder="<?php esc_attr_e( 'Bot token', 'integrate-wpforms-mattermost' ); ?>">
				<?php endif; ?>
			</label>
		</p>
		<p><?php esc_html_e( 'The bot token is encrypted before storage. Only teams and channels available to the bot will be listed.', 'integrate-wpforms-mattermost' ); ?></p>
		<?php
	}

	/** @param mixed $account_id @param mixed $account */
	protected function display_connected_account( $account_id, $account ): void {
		$account = is_array( $account ) ? $account : array();
		echo '<li class="wpforms-clear">';
		echo '<span class="label">' . esc_html( (string) ( $account['label'] ?? 'Mattermost' ) ) . '</span>';
		echo '<span class="date">' . esc_html__( 'Connected and channels verified', 'integrate-wpforms-mattermost' ) . '</span>';
		if ( ! $this->settings->fully_managed_by_constants() ) {
			echo '<span class="remove"><a href="#" data-provider="' . esc_attr( Core::SLUG ) . '" data-key="' . esc_attr( (string) $account_id ) . '">' . esc_html__( 'Disconnect', 'integrate-wpforms-mattermost' ) . '</a></span>';
		}
		echo '</li>';
	}
}
