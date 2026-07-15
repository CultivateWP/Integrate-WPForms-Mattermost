<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\WPForms\ImportGuard;
use PHPUnit\Framework\TestCase;

final class ImportGuardTest extends TestCase {
	public function test_new_imported_feeds_start_disabled(): void {
		$form = array(
			'providers' => array( 'mattermost' => array( 'feed-1' => array( 'mode' => 'live', 'message' => 'Native' ) ) ),
			'settings'  => array( 'iwmm' => array( 'feeds' => array( 'feed-2' => array( 'mode' => 'shadow', 'message' => 'Legacy' ) ) ) ),
		);
		$data = ( new ImportGuard() )->disable_new_form_feeds( array( 'post_type' => 'wpforms', 'post_content' => json_encode( $form ) ), array(), array(), false );
		$decoded = json_decode( (string) $data['post_content'], true );
		self::assertSame( 'disabled', $decoded['providers']['mattermost']['feed-1']['mode'] );
		self::assertSame( 'disabled', $decoded['settings']['iwmm']['feeds']['feed-2']['mode'] );
	}
}
