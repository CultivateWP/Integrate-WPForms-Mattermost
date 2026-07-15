<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/stubs/wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );
define( 'IWMM_DIR', dirname( __DIR__ ) . '/' );
define( 'IWMM_URL', 'https://example.test/wp-content/plugins/integrate-wpforms-mattermost/' );

function wpforms(): object { return new stdClass(); }
function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '', bool $unique = false ): int { return 1; }
function as_schedule_recurring_action( int $timestamp, int $interval, string $hook, array $args = array(), string $group = '', bool $unique = false ): int { return 1; }
function as_has_scheduled_action( string $hook, array $args = array(), string $group = '' ): int|false { return false; }
