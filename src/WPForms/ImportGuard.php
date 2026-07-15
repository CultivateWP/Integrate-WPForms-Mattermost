<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

final class ImportGuard {
	public function hooks(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'disable_new_form_feeds' ), 20, 4 );
	}

	/** @param array<string,mixed> $data @param array<string,mixed> $postarr @param array<string,mixed> $unsanitized_postarr @return array<string,mixed> */
	public function disable_new_form_feeds( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		if ( $update || 'wpforms' !== ( $data['post_type'] ?? '' ) || empty( $data['post_content'] ) ) {
			return $data;
		}
		$form = json_decode( (string) $data['post_content'], true );
		if ( ! is_array( $form ) || empty( $form['settings']['iwmm']['feeds'] ) || ! is_array( $form['settings']['iwmm']['feeds'] ) ) {
			return $data;
		}
		foreach ( $form['settings']['iwmm']['feeds'] as &$feed ) {
			if ( is_array( $feed ) ) {
				$feed['mode'] = 'disabled';
			}
		}
		unset( $feed );
		$data['post_content'] = wp_json_encode( $form );
		return $data;
	}
}
