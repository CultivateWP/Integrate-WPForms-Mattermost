<?php

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'IWMM_REMOVE_DATA_ON_UNINSTALL' ) || ! IWMM_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'iwmm_attempts' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'iwmm_messages' );
delete_option( 'iwmm_connection' );
delete_option( 'iwmm_channels_cache' );
delete_option( 'iwmm_db_version' );
delete_option( 'iwmm_activated_at_gmt' );
