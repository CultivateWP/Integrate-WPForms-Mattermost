<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

final class Reconciler {
	public function __construct( private FeedListener $listener ) {}

	public function hooks(): void {
		add_action( 'iwmm_reconcile_wpforms', array( $this, 'run' ) );
	}

	public function run(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpforms_entries';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$activated = (string) get_option( 'iwmm_activated_at_gmt', gmdate( 'Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS ) );
		$since     = max( strtotime( $activated . ' UTC' ), time() - 48 * HOUR_IN_SECONDS );
		$forms     = get_posts(
			array(
				'post_type'      => 'wpforms',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		foreach ( $forms as $form ) {
			$form_data = json_decode( (string) $form->post_content, true );
			if ( ! is_array( $form_data ) || empty( $form_data['settings']['iwmm']['feeds'] ) ) {
				continue;
			}
			$form_data['id'] = (int) $form->ID;
			$last_entry_id = 0;
			do {
				$entries = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT entry_id, fields FROM {$table} WHERE form_id = %d AND entry_id > %d AND date >= %s AND status != 'trash' ORDER BY entry_id ASC LIMIT 100",
						$form->ID,
						$last_entry_id,
						gmdate( 'Y-m-d H:i:s', $since )
					),
					ARRAY_A
				);
				$entries = is_array( $entries ) ? $entries : array();
				foreach ( $entries as $entry ) {
					$last_entry_id = (int) $entry['entry_id'];
					$fields        = json_decode( (string) $entry['fields'], true );
					if ( is_array( $fields ) ) {
						$this->listener->capture( $fields, array(), $form_data, $last_entry_id );
					}
				}
			} while ( 100 === count( $entries ) );
		}
	}
}
