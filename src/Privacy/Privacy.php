<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Privacy;

use IntegrateWPFormsMattermost\Storage\MessageRepository;

final class Privacy {
	public function __construct( private MessageRepository $repository ) {}

	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'policy' ) );
		add_action( 'wpforms_entry_delete', array( $this, 'erase_entry' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			__( 'Integrate WPForms with Mattermost', 'integrate-wpforms-mattermost' ),
			wp_kses_post( '<p>' . __( 'Configured form values may be rendered into an encrypted queued message and sent to the selected Mattermost channel. Successful payloads are retained for up to 30 days, failed payloads for up to 90 days, and redacted operational metadata for up to one year.', 'integrate-wpforms-mattermost' ) . '</p>' )
		);
	}

	public function erase_entry( int $entry_id ): void {
		$this->repository->erase_source( 'wpforms', (string) $entry_id );
	}

	/** @param array<string,mixed> $erasers @return array<string,mixed> */
	public function erasers( array $erasers ): array {
		$erasers['iwmm'] = array(
			'eraser_friendly_name' => __( 'Mattermost form-message queue', 'integrate-wpforms-mattermost' ),
			'callback'             => array( $this, 'erase_email' ),
		);
		return $erasers;
	}

	/** @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool} */
	public function erase_email( string $email, int $page = 1 ): array {
		global $wpdb;
		$table   = $wpdb->prefix . 'wpforms_entries';
		$like    = '%' . $wpdb->esc_like( $email ) . '%';
		$offset  = max( 0, $page - 1 ) * 100;
		$entries = $wpdb->get_col( $wpdb->prepare( "SELECT entry_id FROM {$table} WHERE fields LIKE %s ORDER BY entry_id ASC LIMIT %d, 100", $like, $offset ) );
		$entries = is_array( $entries ) ? $entries : array();
		$removed = false;
		foreach ( $entries as $entry_id ) {
			$removed = 0 < $this->repository->erase_source( 'wpforms', (string) $entry_id ) || $removed;
		}
		return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => count( $entries ) < 100 );
	}
}
