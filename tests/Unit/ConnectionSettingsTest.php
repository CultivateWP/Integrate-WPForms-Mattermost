<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\Security\Crypto;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use PHPUnit\Framework\TestCase;

final class ConnectionSettingsTest extends TestCase {
	public function test_database_settings_are_clearable_without_a_fully_constant_managed_connection(): void {
		$settings = new ConnectionSettings( ( new ReflectionClass( Crypto::class ) )->newInstanceWithoutConstructor() );

		self::assertFalse( $settings->fully_managed_by_constants() );
		self::assertTrue( $settings->clear() );
	}
}
