<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\WPForms\ImportGuard;
use PHPUnit\Framework\TestCase;

final class ImportGuardTest extends TestCase {
	public function test_new_imported_feeds_start_disabled(): void {
		$form = array( 'settings' => array( 'iwmm' => array( 'feeds' => array( 'feed-1' => array( 'mode' => 'live', 'message' => 'Test' ) ) ) ) );
		$data = ( new ImportGuard() )->disable_new_form_feeds( array( 'post_type' => 'wpforms', 'post_content' => json_encode( $form ) ), array(), array(), false );
		$decoded = json_decode( (string) $data['post_content'], true );
		self::assertSame( 'disabled', $decoded['settings']['iwmm']['feeds']['feed-1']['mode'] );
	}
}
