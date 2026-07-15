<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\WPForms\Migration;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase {
	public function test_legacy_feeds_move_to_native_provider_storage(): void {
		$form = array(
			'settings' => array(
				'iwmm' => array(
					'feeds' => array(
						'feed-1' => array( 'mode' => 'shadow', 'message' => 'Hello' ),
					),
				),
			),
		);

		$migrated = ( new Migration() )->migrate_form_data( $form );

		self::assertSame( 'default', $migrated['providers']['mattermost']['feed-1']['account_id'] );
		self::assertSame( 'shadow', $migrated['providers']['mattermost']['feed-1']['mode'] );
		self::assertArrayNotHasKey( 'iwmm', $migrated['settings'] );
	}

	public function test_existing_native_feeds_are_not_overwritten(): void {
		$form = array(
			'providers' => array( 'mattermost' => array( 'native' => array( 'message' => 'Native' ) ) ),
			'settings'  => array( 'iwmm' => array( 'feeds' => array( 'legacy' => array( 'message' => 'Legacy' ) ) ) ),
		);

		self::assertSame( $form, ( new Migration() )->migrate_form_data( $form ) );
	}
}
