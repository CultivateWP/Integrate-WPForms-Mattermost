<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

use IntegrateWPFormsMattermost\Plugin;
use IntegrateWPFormsMattermost\WPForms\Provider\Account;

final class Migration {
	private const VERSION = '1.1.0';

	public function hooks(): void {
		add_action( 'init', array( $this, 'maybe_run' ), 30 );
	}

	public function maybe_run(): void {
		if (
			! function_exists( 'wpforms_encode' ) ||
			version_compare( (string) get_option( 'iwmm_form_data_version', '1.0.0' ), self::VERSION, '>=' )
		) {
			return;
		}

		$forms = get_posts(
			array(
				'post_type'      => 'wpforms',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
		foreach ( $forms as $form ) {
			$form_data = json_decode( (string) $form->post_content, true );
			if ( ! is_array( $form_data ) ) {
				continue;
			}
			$migrated = $this->migrate_form_data( $form_data );
			if ( $migrated === $form_data ) {
				continue;
			}
			wp_update_post(
				array(
					'ID'           => (int) $form->ID,
					'post_content' => wpforms_encode( $migrated ),
				)
			);
		}

		update_option( 'iwmm_form_data_version', self::VERSION, false );
	}

	/** @param array<string,mixed> $form_data @return array<string,mixed> */
	public function migrate_form_data( array $form_data ): array {
		$legacy = $form_data['settings']['iwmm']['feeds'] ?? null;
		if ( ! is_array( $legacy ) || array() === $legacy || ! empty( $form_data['providers'][ Plugin::PROVIDER_SLUG ] ) ) {
			return $form_data;
		}

		$form_data['providers'] ??= array();
		$form_data['providers'][ Plugin::PROVIDER_SLUG ] = array();
		foreach ( $legacy as $feed_id => $feed ) {
			if ( ! is_array( $feed ) ) {
				continue;
			}
			$feed['id']         = (string) ( $feed['id'] ?? $feed_id );
			$feed['account_id'] = Account::ID;
			$form_data['providers'][ Plugin::PROVIDER_SLUG ][ (string) $feed_id ] = $feed;
		}
		unset( $form_data['settings']['iwmm']['feeds'] );
		if ( empty( $form_data['settings']['iwmm'] ) ) {
			unset( $form_data['settings']['iwmm'] );
		}

		return $form_data;
	}
}
