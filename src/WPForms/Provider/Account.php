<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms\Provider;

use IntegrateWPFormsMattermost\Plugin;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;

final class Account {
	public const ID = 'default';

	public function __construct( private ConnectionSettings $settings ) {}

	public function sync(): void {
		if ( ! function_exists( 'wpforms_get_providers_options' ) ) {
			return;
		}

		$accounts = wpforms_get_providers_options( Plugin::PROVIDER_SLUG );
		$accounts = is_array( $accounts ) ? $accounts : array();
		if ( ! $this->settings->configured() ) {
			if ( isset( $accounts[ self::ID ] ) ) {
				$this->remove_marker();
			}
			return;
		}

		if ( isset( $accounts[ self::ID ] ) ) {
			return;
		}

		$this->save_marker( 'Mattermost' );
	}

	public function save_marker( string $label ): void {
		if ( ! function_exists( 'wpforms_update_providers_options' ) ) {
			return;
		}

		wpforms_update_providers_options(
			Plugin::PROVIDER_SLUG,
			array(
				'label'    => '' !== trim( $label ) ? sanitize_text_field( $label ) : 'Mattermost',
				'date'     => time(),
				'base_url' => esc_url_raw( $this->settings->base_url() ),
			),
			self::ID
		);
	}

	public function remove(): bool {
		if ( ! $this->settings->clear() ) {
			return false;
		}

		$this->remove_marker();
		delete_option( 'iwmm_channels_cache' );
		return true;
	}

	/** @return array<string,array<string,mixed>> */
	public function all(): array {
		if ( ! function_exists( 'wpforms_get_providers_options' ) ) {
			return array();
		}

		$accounts = wpforms_get_providers_options( Plugin::PROVIDER_SLUG );
		return is_array( $accounts ) ? $accounts : array();
	}

	private function remove_marker(): void {
		$providers = wpforms_get_providers_options();
		$providers = is_array( $providers ) ? $providers : array();
		unset( $providers[ Plugin::PROVIDER_SLUG ][ self::ID ] );
		if ( empty( $providers[ Plugin::PROVIDER_SLUG ] ) ) {
			unset( $providers[ Plugin::PROVIDER_SLUG ] );
		}
		update_option( 'wpforms_providers', $providers, false );
	}
}
