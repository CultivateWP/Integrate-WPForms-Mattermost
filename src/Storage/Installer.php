<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Storage;

final class Installer {
	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$messages = $wpdb->prefix . 'iwmm_messages';
		$attempts = $wpdb->prefix . 'iwmm_attempts';

		dbDelta(
			"CREATE TABLE {$messages} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				idempotency_key char(64) NOT NULL,
				source varchar(64) NOT NULL DEFAULT 'api',
				source_id varchar(191) NOT NULL DEFAULT '',
				feed_id varchar(191) NOT NULL DEFAULT '',
				channel_id varchar(64) NOT NULL,
				message_cipher longtext NOT NULL,
				message_nonce varchar(64) NOT NULL,
				status varchar(32) NOT NULL DEFAULT 'captured',
				mode varchar(16) NOT NULL DEFAULT 'live',
				attempts smallint unsigned NOT NULL DEFAULT 0,
				next_attempt_gmt datetime NULL,
				remote_post_id varchar(64) NOT NULL DEFAULT '',
				last_error_code varchar(64) NOT NULL DEFAULT '',
				last_error_message varchar(255) NOT NULL DEFAULT '',
				created_at_gmt datetime NOT NULL,
				updated_at_gmt datetime NOT NULL,
				completed_at_gmt datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idempotency_key (idempotency_key),
				KEY status_next (status,next_attempt_gmt),
				KEY source_lookup (source,source_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$attempts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				message_id bigint(20) unsigned NOT NULL,
				attempt_number smallint unsigned NOT NULL,
				status varchar(32) NOT NULL,
				http_status smallint unsigned NULL,
				error_code varchar(64) NOT NULL DEFAULT '',
				error_message varchar(255) NOT NULL DEFAULT '',
				duration_ms int unsigned NOT NULL DEFAULT 0,
				created_at_gmt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY message_id (message_id),
				KEY created_at_gmt (created_at_gmt)
			) {$charset};"
		);

		update_option( 'iwmm_db_version', IWMM_VERSION, false );
		add_option( 'iwmm_activated_at_gmt', gmdate( 'Y-m-d H:i:s' ), '', false );
	}
}
