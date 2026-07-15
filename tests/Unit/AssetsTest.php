<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase {
	public function test_wpforms_provider_icon_is_a_square_png(): void {
		$icon = dirname( __DIR__, 2 ) . '/assets/mattermost.png';
		$size = getimagesize( $icon );

		self::assertIsArray( $size );
		self::assertSame( 400, $size[0] );
		self::assertSame( 400, $size[1] );
		self::assertSame( 'image/png', $size['mime'] );
	}

	public function test_bot_setup_guide_is_packaged(): void {
		$guide = dirname( __DIR__, 2 ) . '/docs/MATTERMOST-BOT-SETUP.md';

		self::assertFileExists( $guide );
		self::assertStringContainsString( 'generated **bot access token**', (string) file_get_contents( $guide ) );
	}
}
