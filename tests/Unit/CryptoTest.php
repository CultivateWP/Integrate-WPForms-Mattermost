<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\Security\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase {
	public function test_ciphertext_round_trip_and_authentication(): void {
		$crypto    = new Crypto();
		$encrypted = $crypto->encrypt( 'private message' );
		self::assertNotSame( 'private message', $encrypted['cipher'] );
		self::assertSame( 'private message', $crypto->decrypt( $encrypted['cipher'], $encrypted['nonce'] ) );
	}
}
