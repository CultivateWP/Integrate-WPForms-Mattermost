<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

final class BuilderPanel {
	public function hooks(): void {
		add_filter( 'wpforms_builder_settings_sections', array( $this, 'section' ), 20, 2 );
		add_action( 'wpforms_form_settings_panel_content', array( $this, 'content' ) );
		add_action( 'wpforms_builder_enqueues', array( $this, 'assets' ) );
	}

	/** @param array<string,mixed> $sections @return array<string,mixed> */
	public function section( array $sections, mixed $form_data = null ): array {
		$sections['iwmm'] = __( 'Mattermost', 'integrate-wpforms-mattermost' );
		return $sections;
	}

	public function assets(): void {
		wp_enqueue_script( 'iwmm-builder', IWMM_URL . 'assets/builder.js', array( 'jquery' ), IWMM_VERSION, true );
		wp_enqueue_style( 'iwmm-builder', IWMM_URL . 'assets/builder.css', array(), IWMM_VERSION );
	}

	/** @param object $instance WPForms builder instance. */
	public function content( object $instance ): void {
		$form_data = isset( $instance->form_data ) && is_array( $instance->form_data ) ? $instance->form_data : array();
		$feeds     = $form_data['settings']['iwmm']['feeds'] ?? array();
		$feeds     = is_array( $feeds ) ? $feeds : array();
		$channels  = get_option( 'iwmm_channels_cache', array() );
		$channels  = is_array( $channels ) ? $channels : array();
		?>
		<div class="wpforms-panel-content-section wpforms-panel-content-section-iwmm">
			<div class="wpforms-panel-content-section-title">
				<?php esc_html_e( 'Mattermost Feeds', 'integrate-wpforms-mattermost' ); ?>
			</div>
			<p><?php esc_html_e( 'Each matching feed is captured after a successful submission and delivered asynchronously. Use WPForms Smart Tags in message templates.', 'integrate-wpforms-mattermost' ); ?></p>
			<div id="iwmm-feeds">
				<?php foreach ( $feeds as $feed_id => $feed ) : ?>
					<?php $this->render_feed( (string) $feed_id, is_array( $feed ) ? $feed : array(), $channels ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button iwmm-add-feed"><?php esc_html_e( 'Add Mattermost Feed', 'integrate-wpforms-mattermost' ); ?></button>
			<script type="text/template" id="tmpl-iwmm-feed">
				<?php $this->render_feed( '__FEED__', array(), $channels ); ?>
			</script>
		</div>
		<?php
	}

	/** @param array<string,mixed> $feed @param array<int,array<string,string>> $channels */
	private function render_feed( string $id, array $feed, array $channels ): void {
		$base       = 'settings[iwmm][feeds][' . $id . ']';
		$conditions = isset( $feed['conditions'] ) && is_array( $feed['conditions'] ) ? $feed['conditions'] : array();
		$origin     = hash( 'sha256', untrailingslashit( home_url() ) );
		if ( isset( $feed['origin'] ) && ! hash_equals( $origin, (string) $feed['origin'] ) ) {
			$feed['mode'] = 'disabled';
		}
		?>
		<div class="iwmm-feed" data-feed-id="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $base . '[origin]' ); ?>" value="<?php echo esc_attr( $origin ); ?>">
			<p class="description"><?php esc_html_e( 'Feed ID:', 'integrate-wpforms-mattermost' ); ?> <code><?php echo esc_html( $id ); ?></code></p>
			<p><label><?php esc_html_e( 'Feed name', 'integrate-wpforms-mattermost' ); ?><input class="widefat" name="<?php echo esc_attr( $base . '[name]' ); ?>" value="<?php echo esc_attr( (string) ( $feed['name'] ?? '' ) ); ?>"></label></p>
			<p><label><?php esc_html_e( 'Mode', 'integrate-wpforms-mattermost' ); ?><select name="<?php echo esc_attr( $base . '[mode]' ); ?>">
				<?php foreach ( array( 'disabled' => 'Disabled', 'shadow' => 'Shadow', 'live' => 'Live' ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $feed['mode'] ?? 'disabled', $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
			<p><label><?php esc_html_e( 'Channel', 'integrate-wpforms-mattermost' ); ?><select class="widefat" name="<?php echo esc_attr( $base . '[channel_id]' ); ?>">
				<option value=""><?php esc_html_e( 'Select a channel', 'integrate-wpforms-mattermost' ); ?></option>
				<?php foreach ( $channels as $channel ) : ?>
					<option value="<?php echo esc_attr( (string) $channel['id'] ); ?>" <?php selected( $feed['channel_id'] ?? '', $channel['id'] ); ?>><?php echo esc_html( (string) ( ( $channel['team_name'] ?? '' ) ? $channel['team_name'] . ' — ' : '' ) . ( $channel['display_name'] ?? $channel['name'] ?? $channel['id'] ) ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
			<p><label><?php esc_html_e( 'Message', 'integrate-wpforms-mattermost' ); ?><textarea class="widefat wpforms-smart-tags-enabled" rows="5" name="<?php echo esc_attr( $base . '[message]' ); ?>"><?php echo esc_textarea( (string) ( $feed['message'] ?? '' ) ); ?></textarea></label></p>
			<p><label><?php esc_html_e( 'Run when', 'integrate-wpforms-mattermost' ); ?><select name="<?php echo esc_attr( $base . '[condition_match]' ); ?>"><option value="all" <?php selected( $feed['condition_match'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All conditions match', 'integrate-wpforms-mattermost' ); ?></option><option value="any" <?php selected( $feed['condition_match'] ?? 'all', 'any' ); ?>><?php esc_html_e( 'Any condition matches', 'integrate-wpforms-mattermost' ); ?></option></select></label></p>
			<div class="iwmm-conditions">
				<?php foreach ( $conditions as $condition_id => $condition ) : ?>
					<?php $this->render_condition( $base, (string) $condition_id, is_array( $condition ) ? $condition : array() ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button iwmm-add-condition"><?php esc_html_e( 'Add condition', 'integrate-wpforms-mattermost' ); ?></button> <button type="button" class="button-link-delete iwmm-remove-feed"><?php esc_html_e( 'Remove feed', 'integrate-wpforms-mattermost' ); ?></button></p>
		</div>
		<?php
	}

	/** @param array<string,mixed> $condition */
	private function render_condition( string $base, string $id, array $condition ): void {
		$name = $base . '[conditions][' . $id . ']';
		?>
		<div class="iwmm-condition">
			<input type="number" min="1" placeholder="<?php esc_attr_e( 'Field ID', 'integrate-wpforms-mattermost' ); ?>" name="<?php echo esc_attr( $name . '[field_id]' ); ?>" value="<?php echo esc_attr( (string) ( $condition['field_id'] ?? '' ) ); ?>">
			<select name="<?php echo esc_attr( $name . '[operator]' ); ?>">
				<?php foreach ( array( 'equals' => 'Equals', 'not_equals' => 'Does not equal', 'contains' => 'Contains', 'not_contains' => 'Does not contain', 'empty' => 'Is empty', 'not_empty' => 'Is not empty' ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $condition['operator'] ?? 'equals', $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" placeholder="<?php esc_attr_e( 'Value', 'integrate-wpforms-mattermost' ); ?>" name="<?php echo esc_attr( $name . '[value]' ); ?>" value="<?php echo esc_attr( (string) ( $condition['value'] ?? '' ) ); ?>">
			<button type="button" class="button-link-delete iwmm-remove-condition" aria-label="<?php esc_attr_e( 'Remove condition', 'integrate-wpforms-mattermost' ); ?>">&times;</button>
		</div>
		<?php
	}
}
