<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\WPForms\FeedListener;
use PHPUnit\Framework\TestCase;

final class FeedStorageTest extends TestCase {
	public function test_native_provider_feeds_take_precedence_over_legacy_storage(): void {
		$listener = ( new ReflectionClass( FeedListener::class ) )->newInstanceWithoutConstructor();
		$form = array(
			'providers' => array( 'mattermost' => array( 'native' => array( 'message' => 'Native' ) ) ),
			'settings'  => array( 'iwmm' => array( 'feeds' => array( 'legacy' => array( 'message' => 'Legacy' ) ) ) ),
		);

		self::assertSame( array( 'native' ), array_keys( $listener->feeds( $form ) ) );
	}

	public function test_legacy_storage_remains_readable_during_upgrade(): void {
		$listener = ( new ReflectionClass( FeedListener::class ) )->newInstanceWithoutConstructor();
		$form = array( 'settings' => array( 'iwmm' => array( 'feeds' => array( 'legacy' => array( 'message' => 'Legacy' ) ) ) ) );

		self::assertSame( array( 'legacy' ), array_keys( $listener->feeds( $form ) ) );
	}
}
