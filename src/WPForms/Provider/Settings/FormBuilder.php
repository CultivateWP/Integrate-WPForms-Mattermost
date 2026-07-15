<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms\Provider\Settings;

use IntegrateWPFormsMattermost\WPForms\Provider\Account;
use IntegrateWPFormsMattermost\WPForms\Provider\Core;

final class FormBuilder extends \WPForms\Providers\Provider\Settings\FormBuilder {
	protected function init_hooks(): void {
		parent::init_hooks();
		add_filter( 'wpforms_save_form_args', array( $this, 'save_form' ), 11, 3 );
	}

	public function enqueue_assets(): void {
		parent::enqueue_assets();
		wp_enqueue_script( 'iwmm-builder', IWMM_URL . 'assets/builder.js', array( 'jquery', 'wpforms-admin-builder-providers' ), IWMM_VERSION, true );
		wp_enqueue_style( 'iwmm-builder', IWMM_URL . 'assets/builder.css', array(), IWMM_VERSION );
	}

	public function display_content(): void {
		$feeds      = $this->feeds();
		$configured = ! empty( wpforms_get_providers_options( Core::SLUG ) );
		?>
		<div class="wpforms-panel-content-section wpforms-builder-provider wpforms-panel-content-section-<?php echo esc_attr( Core::SLUG ); ?>" id="<?php echo esc_attr( Core::SLUG ); ?>-provider" data-provider="<?php echo esc_attr( Core::SLUG ); ?>" data-provider-name="<?php esc_attr_e( 'Mattermost', 'integrate-wpforms-mattermost' ); ?>">
			<div class="wpforms-builder-provider-title wpforms-panel-content-section-title">
				<?php esc_html_e( 'Mattermost', 'integrate-wpforms-mattermost' ); ?>
				<?php if ( $configured ) : ?>
					<button type="button" class="wpforms-builder-provider-title-add iwmm-add-feed"><?php esc_html_e( 'Add New Connection', 'integrate-wpforms-mattermost' ); ?></button>
				<?php endif; ?>
			</div>

			<?php if ( ! $configured ) : ?>
				<div class="wpforms-builder-provider-connections-default">
					<img src="<?php echo esc_url( IWMM_URL . 'assets/mattermost.png' ); ?>" alt="">
					<div class="wpforms-builder-provider-settings-default-content">
						<p><?php esc_html_e( 'Connect Mattermost under WPForms → Settings → Integrations before adding a form connection.', 'integrate-wpforms-mattermost' ); ?></p>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforms-settings&view=integrations&wpforms-integration=mattermost' ) ); ?>"><?php esc_html_e( 'Configure Mattermost', 'integrate-wpforms-mattermost' ); ?></a></p>
					</div>
				</div>
			<?php endif; ?>

			<div class="wpforms-builder-provider-body">
				<input type="hidden" name="providers[<?php echo esc_attr( Core::SLUG ); ?>][__present__]" value="1">
				<div class="wpforms-provider-connections-wrap wpforms-clear">
					<div id="iwmm-feeds" class="wpforms-builder-provider-connections">
						<?php foreach ( $feeds as $feed_id => $feed ) : ?>
							<?php $this->render_feed( (string) $feed_id, is_array( $feed ) ? $feed : array() ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function builder_custom_templates(): void {
		?>
		<script type="text/template" id="tmpl-iwmm-feed">
			<?php $this->render_feed( '__FEED__', array() ); ?>
		</script>
		<script type="text/template" id="tmpl-iwmm-condition">
			<?php $this->render_condition( '__FEED__', '__CONDITION__', array() ); ?>
		</script>
		<?php
	}

	/**
	 * @param array|mixed $form Form post data.
	 * @param array<mixed> $data Submitted builder data.
	 * @param array<mixed> $args Save arguments.
	 * @return array<mixed>
	 */
	public function save_form( $form, $data, $args ): array {
		$form      = (array) $form;
		$form_data = isset( $form['post_content'] ) ? json_decode( stripslashes( (string) $form['post_content'] ), true ) : null;
		if ( ! is_array( $form_data ) ) {
			return $form;
		}

		if ( isset( $form_data['providers'][ Core::SLUG ] ) && is_array( $form_data['providers'][ Core::SLUG ] ) ) {
			$feeds = $this->sanitize_feeds( $form_data['providers'][ Core::SLUG ] );
			if ( array() === $feeds ) {
				unset( $form_data['providers'][ Core::SLUG ] );
			} else {
				$form_data['providers'][ Core::SLUG ] = $feeds;
			}
			$form['post_content'] = wpforms_encode( $form_data );
			return $form;
		}

		$form_id  = is_array( $data ) ? absint( $data['id'] ?? 0 ) : 0;
		$form_obj = wpforms()->obj( 'form' );
		$previous = $form_id > 0 && $form_obj ? $form_obj->get( $form_id, array( 'content_only' => true ) ) : array();
		if ( ! empty( $previous['providers'][ Core::SLUG ] ) ) {
			$form_data['providers'] ??= array();
			$form_data['providers'][ Core::SLUG ] = $previous['providers'][ Core::SLUG ];
			$form['post_content'] = wpforms_encode( $form_data );
		}

		return $form;
	}

	/** @return array<string,array<string,mixed>> */
	private function feeds(): array {
		$feeds = $this->form_data['providers'][ Core::SLUG ] ?? array();
		return is_array( $feeds ) ? $feeds : array();
	}

	/** @param array<string,mixed> $feed */
	private function render_feed( string $id, array $feed ): void {
		$base       = 'providers[' . Core::SLUG . '][' . $id . ']';
		$conditions = isset( $feed['conditions'] ) && is_array( $feed['conditions'] ) ? $feed['conditions'] : array();
		$channels   = get_option( 'iwmm_channels_cache', array() );
		$channels   = is_array( $channels ) ? $channels : array();
		$origin     = hash( 'sha256', untrailingslashit( home_url() ) );
		if ( isset( $feed['origin'] ) && ! hash_equals( $origin, (string) $feed['origin'] ) ) {
			$feed['mode'] = 'disabled';
		}
		?>
		<div class="iwmm-feed wpforms-builder-provider-connection" data-feed-id="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $base . '[account_id]' ); ?>" value="<?php echo esc_attr( Account::ID ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $base . '[origin]' ); ?>" value="<?php echo esc_attr( $origin ); ?>">
			<div class="wpforms-builder-provider-connection-title">
				<?php echo esc_html( (string) ( $feed['name'] ?? __( 'New Mattermost Connection', 'integrate-wpforms-mattermost' ) ) ); ?>
				<button type="button" class="wpforms-builder-provider-connection-delete iwmm-remove-feed" aria-label="<?php esc_attr_e( 'Remove connection', 'integrate-wpforms-mattermost' ); ?>"><i class="fa fa-trash-o"></i></button>
			</div>
			<div class="wpforms-builder-provider-connection-block">
				<label><?php esc_html_e( 'Connection Name', 'integrate-wpforms-mattermost' ); ?><span class="required">*</span></label>
				<input class="widefat wpforms-required" name="<?php echo esc_attr( $base . '[name]' ); ?>" value="<?php echo esc_attr( (string) ( $feed['name'] ?? '' ) ); ?>" required>
			</div>
			<div class="wpforms-builder-provider-connection-block">
				<label><?php esc_html_e( 'Delivery Mode', 'integrate-wpforms-mattermost' ); ?></label>
				<select name="<?php echo esc_attr( $base . '[mode]' ); ?>">
					<?php foreach ( array( 'disabled' => __( 'Disabled', 'integrate-wpforms-mattermost' ), 'shadow' => __( 'Shadow — capture without sending', 'integrate-wpforms-mattermost' ), 'live' => __( 'Live', 'integrate-wpforms-mattermost' ) ) as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $feed['mode'] ?? 'disabled', $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpforms-builder-provider-connection-block">
				<label><?php esc_html_e( 'Channel', 'integrate-wpforms-mattermost' ); ?><span class="required">*</span></label>
				<select class="widefat wpforms-required" name="<?php echo esc_attr( $base . '[channel_id]' ); ?>" required>
					<option value=""><?php esc_html_e( '--- Select a channel ---', 'integrate-wpforms-mattermost' ); ?></option>
					<?php foreach ( $channels as $channel ) : ?>
						<?php if ( is_array( $channel ) ) : ?>
							<option value="<?php echo esc_attr( (string) ( $channel['id'] ?? '' ) ); ?>" <?php selected( $feed['channel_id'] ?? '', $channel['id'] ?? '' ); ?>><?php echo esc_html( (string) ( ! empty( $channel['team_name'] ) ? $channel['team_name'] . ' — ' : '' ) . ( $channel['display_name'] ?? $channel['name'] ?? $channel['id'] ?? '' ) ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="wpforms-builder-provider-connection-block wpforms-panel-field wpforms-panel-field-textarea">
				<label for="iwmm-message-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Message', 'integrate-wpforms-mattermost' ); ?><span class="required">*</span></label>
				<textarea id="iwmm-message-<?php echo esc_attr( $id ); ?>" class="widefat wpforms-required wpforms-smart-tags-enabled" data-type="all" data-fields rows="5" name="<?php echo esc_attr( $base . '[message]' ); ?>" required><?php echo esc_textarea( (string) ( $feed['message'] ?? '' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Mattermost Markdown and WPForms Smart Tags are supported.', 'integrate-wpforms-mattermost' ); ?></p>
			</div>
			<div class="wpforms-builder-provider-connection-block">
				<label><?php esc_html_e( 'Run When', 'integrate-wpforms-mattermost' ); ?></label>
				<select name="<?php echo esc_attr( $base . '[condition_match]' ); ?>"><option value="all" <?php selected( $feed['condition_match'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All conditions match', 'integrate-wpforms-mattermost' ); ?></option><option value="any" <?php selected( $feed['condition_match'] ?? 'all', 'any' ); ?>><?php esc_html_e( 'Any condition matches', 'integrate-wpforms-mattermost' ); ?></option></select>
				<div class="iwmm-conditions">
					<?php foreach ( $conditions as $condition_id => $condition ) : ?>
						<?php $this->render_condition( $id, (string) $condition_id, is_array( $condition ) ? $condition : array() ); ?>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button iwmm-add-condition"><?php esc_html_e( 'Add Condition', 'integrate-wpforms-mattermost' ); ?></button></p>
			</div>
		</div>
		<?php
	}

	/** @param array<string,mixed> $condition */
	private function render_condition( string $feed_id, string $id, array $condition ): void {
		$name   = 'providers[' . Core::SLUG . '][' . $feed_id . '][conditions][' . $id . ']';
		$fields = isset( $this->form_data['fields'] ) && is_array( $this->form_data['fields'] ) ? $this->form_data['fields'] : array();
		?>
		<div class="iwmm-condition">
			<select name="<?php echo esc_attr( $name . '[field_id]' ); ?>" aria-label="<?php esc_attr_e( 'Form field', 'integrate-wpforms-mattermost' ); ?>">
				<option value=""><?php esc_html_e( '--- Select Field ---', 'integrate-wpforms-mattermost' ); ?></option>
				<?php foreach ( $fields as $field_id => $field ) : ?>
					<?php if ( is_array( $field ) ) : ?>
						<option value="<?php echo esc_attr( (string) $field_id ); ?>" <?php selected( (string) ( $condition['field_id'] ?? '' ), (string) $field_id ); ?>><?php echo esc_html( (string) ( $field['label'] ?? __( 'Field', 'integrate-wpforms-mattermost' ) . ' #' . $field_id ) ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<select name="<?php echo esc_attr( $name . '[operator]' ); ?>" aria-label="<?php esc_attr_e( 'Operator', 'integrate-wpforms-mattermost' ); ?>">
				<?php foreach ( array( 'equals' => __( 'Equals', 'integrate-wpforms-mattermost' ), 'not_equals' => __( 'Does not equal', 'integrate-wpforms-mattermost' ), 'contains' => __( 'Contains', 'integrate-wpforms-mattermost' ), 'not_contains' => __( 'Does not contain', 'integrate-wpforms-mattermost' ), 'empty' => __( 'Is empty', 'integrate-wpforms-mattermost' ), 'not_empty' => __( 'Is not empty', 'integrate-wpforms-mattermost' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $condition['operator'] ?? 'equals', $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" placeholder="<?php esc_attr_e( 'Value', 'integrate-wpforms-mattermost' ); ?>" name="<?php echo esc_attr( $name . '[value]' ); ?>" value="<?php echo esc_attr( (string) ( $condition['value'] ?? '' ) ); ?>">
			<button type="button" class="button-link-delete iwmm-remove-condition" aria-label="<?php esc_attr_e( 'Remove condition', 'integrate-wpforms-mattermost' ); ?>">&times;</button>
		</div>
		<?php
	}

	/** @param array<string,mixed> $feeds @return array<string,array<string,mixed>> */
	private function sanitize_feeds( array $feeds ): array {
		$sanitized = array();
		$origin    = hash( 'sha256', untrailingslashit( home_url() ) );
		foreach ( $feeds as $feed_id => $feed ) {
			if ( '__lock__' === $feed_id || ! is_array( $feed ) ) {
				continue;
			}
			$id = sanitize_key( (string) ( $feed['id'] ?? $feed_id ) );
			if ( '' === $id ) {
				continue;
			}
			$mode = in_array( $feed['mode'] ?? '', array( 'disabled', 'shadow', 'live' ), true ) ? (string) $feed['mode'] : 'disabled';
			$conditions = array();
			foreach ( is_array( $feed['conditions'] ?? null ) ? $feed['conditions'] : array() as $condition_id => $condition ) {
				if ( ! is_array( $condition ) || '' === (string) ( $condition['field_id'] ?? '' ) ) {
					continue;
				}
				$operator = in_array( $condition['operator'] ?? '', array( 'equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty' ), true ) ? (string) $condition['operator'] : 'equals';
				$conditions[ sanitize_key( (string) $condition_id ) ] = array(
					'field_id' => sanitize_text_field( (string) $condition['field_id'] ),
					'operator' => $operator,
					'value'    => sanitize_text_field( (string) ( $condition['value'] ?? '' ) ),
				);
			}
			$sanitized[ $id ] = array(
				'id'              => $id,
				'account_id'      => Account::ID,
				'origin'          => $origin,
				'name'            => sanitize_text_field( (string) ( $feed['name'] ?? '' ) ),
				'mode'            => $mode,
				'channel_id'      => sanitize_text_field( (string) ( $feed['channel_id'] ?? '' ) ),
				'message'         => sanitize_textarea_field( (string) ( $feed['message'] ?? '' ) ),
				'condition_match' => 'any' === ( $feed['condition_match'] ?? 'all' ) ? 'any' : 'all',
				'conditions'      => $conditions,
			);
		}
		return $sanitized;
	}
}
