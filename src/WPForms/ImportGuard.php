<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

use IntegrateWPFormsMattermost\Plugin;

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
		if ( ! is_array( $form ) ) {
			return $data;
		}
		if ( isset( $form['providers'][ Plugin::PROVIDER_SLUG ] ) && is_array( $form['providers'][ Plugin::PROVIDER_SLUG ] ) ) {
			$this->disable( $form['providers'][ Plugin::PROVIDER_SLUG ] );
		}
		if ( isset( $form['settings']['iwmm']['feeds'] ) && is_array( $form['settings']['iwmm']['feeds'] ) ) {
			$this->disable( $form['settings']['iwmm']['feeds'] );
		}
		$data['post_content'] = wp_json_encode( $form );
		return $data;
	}

	/** @param array<string,mixed> $feeds */
	private function disable( array &$feeds ): void {
		foreach ( $feeds as &$feed ) {
			if ( is_array( $feed ) ) {
				$feed['mode'] = 'disabled';
			}
		}
		unset( $feed );
	}
}
