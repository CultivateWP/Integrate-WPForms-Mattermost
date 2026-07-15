<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\Queue\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase {
	public function test_default_backoff(): void {
		$policy = new RetryPolicy();
		$this->assertSame( 60, $policy->delay( 1 ) );
		$this->assertSame( 300, $policy->delay( 2 ) );
		$this->assertSame( 86400, $policy->delay( 6 ) );
	}

	public function test_vendor_delay_is_bounded(): void {
		$policy = new RetryPolicy();
		$this->assertSame( 120, $policy->delay( 1, 120 ) );
		$this->assertSame( 86400, $policy->delay( 1, 999999 ) );
	}

	public function test_transient_statuses(): void {
		$policy = new RetryPolicy();
		$this->assertTrue( $policy->transient( 408 ) );
		$this->assertTrue( $policy->transient( 429 ) );
		$this->assertTrue( $policy->transient( 503 ) );
		$this->assertFalse( $policy->transient( 400 ) );
	}
}
