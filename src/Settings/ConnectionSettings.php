<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Settings;

use IntegrateWPFormsMattermost\Security\Crypto;
use InvalidArgumentException;

final class ConnectionSettings {
	private const OPTION = 'iwmm_connection';

	public function __construct( private Crypto $crypto ) {}

	public function base_url(): string {
		if ( defined( 'IWMM_MATTERMOST_URL' ) ) {
			return untrailingslashit( (string) IWMM_MATTERMOST_URL );
		}
		$settings = $this->raw();
		return untrailingslashit( (string) ( $settings['base_url'] ?? '' ) );
	}

	public function token(): string {
		if ( defined( 'IWMM_MATTERMOST_TOKEN' ) ) {
			return (string) IWMM_MATTERMOST_TOKEN;
		}
		$settings = $this->raw();
		if ( empty( $settings['token_cipher'] ) || empty( $settings['token_nonce'] ) ) {
			return '';
		}
		try {
			return $this->crypto->decrypt( (string) $settings['token_cipher'], (string) $settings['token_nonce'] );
		} catch ( \Throwable ) {
			return '';
		}
	}

	public function configured(): bool {
		return $this->accepts_url( $this->base_url() ) && '' !== $this->token();
	}

	public function fully_managed_by_constants(): bool {
		return defined( 'IWMM_MATTERMOST_URL' ) && defined( 'IWMM_MATTERMOST_TOKEN' );
	}

	public function save( string $base_url, string $token ): bool {
		$current = $this->raw();
		$url     = esc_url_raw( untrailingslashit( $base_url ) );
		if ( '' !== $url && ! $this->accepts_url( $url ) ) {
			throw new InvalidArgumentException( 'Mattermost must use HTTPS outside local development.' );
		}
		$data = array( 'base_url' => $url );
		if ( '' !== $token ) {
			$encrypted            = $this->crypto->encrypt_secret( $token );
			$data['token_cipher'] = $encrypted['cipher'];
			$data['token_nonce']  = $encrypted['nonce'];
		} else {
			$data['token_cipher'] = $current['token_cipher'] ?? '';
			$data['token_nonce']  = $current['token_nonce'] ?? '';
		}
		return update_option( self::OPTION, $data, false );
	}

	public function clear(): bool {
		if ( $this->fully_managed_by_constants() ) {
			return false;
		}

		delete_option( self::OPTION );
		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function raw(): array {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public function accepts_url( string $url ): bool {
		$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
		return '' !== $host && ( 'https' === $scheme || ( 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) );
	}
}
