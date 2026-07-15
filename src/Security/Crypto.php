<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Security;

use RuntimeException;

final class Crypto {
	public function available(): bool {
		return function_exists( 'sodium_crypto_secretbox' );
	}

	/**
	 * @return array{cipher:string,nonce:string}
	 */
	public function encrypt( string $plaintext ): array {
		if ( ! $this->available() ) {
			throw new RuntimeException( 'The sodium PHP extension is required.' );
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return array(
			'cipher' => base64_encode( sodium_crypto_secretbox( $plaintext, $nonce, $this->key() ) ),
			'nonce'  => base64_encode( $nonce ),
		);
	}

	public function decrypt( string $cipher, string $nonce ): string {
		if ( ! $this->available() ) {
			throw new RuntimeException( 'The sodium PHP extension is required.' );
		}
		$opened = sodium_crypto_secretbox_open( base64_decode( $cipher, true ), base64_decode( $nonce, true ), $this->key() );
		if ( false === $opened ) {
			throw new RuntimeException( 'Encrypted data could not be authenticated.' );
		}
		return $opened;
	}

	/**
	 * @return array{cipher:string,nonce:string}
	 */
	public function encrypt_secret( string $secret ): array {
		return $this->encrypt( $secret );
	}

	private function key(): string {
		$configured = defined( 'IWMM_ENCRYPTION_KEY' ) ? (string) IWMM_ENCRYPTION_KEY : '';
		$decoded    = '' !== $configured ? base64_decode( $configured, true ) : false;
		if ( is_string( $decoded ) && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $decoded ) ) {
			return $decoded;
		}

		$material = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) : __FILE__;
		return sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
