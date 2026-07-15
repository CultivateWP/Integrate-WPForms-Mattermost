<?php

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $value ): string {
		return strip_tags( $value );
	}
}

function wp_json_encode( mixed $value ): string|false { return json_encode( $value ); }
