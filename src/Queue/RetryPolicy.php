<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Queue;

final class RetryPolicy {
	private const DELAYS = array( 60, 300, 1800, 7200, 43200, 86400 );

	public function max_attempts(): int {
		return count( self::DELAYS );
	}

	public function delay( int $attempt, ?int $vendor_delay = null ): int {
		if ( null !== $vendor_delay && $vendor_delay > 0 ) {
			return min( DAY_IN_SECONDS, $vendor_delay );
		}
		$index = max( 0, min( count( self::DELAYS ) - 1, $attempt - 1 ) );
		return self::DELAYS[ $index ];
	}

	public function transient( int $status ): bool {
		return 408 === $status || 429 === $status || $status >= 500;
	}
}
