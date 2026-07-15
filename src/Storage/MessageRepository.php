<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Storage;

use IntegrateWPFormsMattermost\Security\Crypto;
use WP_Error;

final class MessageRepository {
	public function __construct( private Crypto $crypto ) {}

	/**
	 * @param array<string,string> $data Message data.
	 * @return int|WP_Error
	 */
	public function insert( array $data ) {
		global $wpdb;
		try {
			$encrypted = $this->crypto->encrypt( $data['message'] );
		} catch ( \Throwable $error ) {
			return new WP_Error( 'iwmm_encrypt_failed', $error->getMessage() );
		}

		$now      = gmdate( 'Y-m-d H:i:s' );
		$inserted = $wpdb->insert(
			$this->messages_table(),
			array(
				'uuid'                => wp_generate_uuid4(),
				'idempotency_key'     => $data['idempotency_key'],
				'source'              => $data['source'],
				'source_id'           => $data['source_id'],
				'feed_id'             => $data['feed_id'],
				'channel_id'          => $data['channel_id'],
				'message_cipher'      => $encrypted['cipher'],
				'message_nonce'       => $encrypted['nonce'],
				'status'              => 'captured',
				'mode'                => $data['mode'],
				'created_at_gmt'      => $now,
				'updated_at_gmt'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$existing = $this->find_by_idempotency_key( $data['idempotency_key'] );
			return $existing ? (int) $existing['id'] : new WP_Error( 'iwmm_database_error', 'The message could not be stored.' );
		}
		return (int) $wpdb->insert_id;
	}

	/** @return array<string,mixed>|null */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->messages_table() . ' WHERE id = %d', $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	public function find_by_idempotency_key( string $key ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->messages_table() . ' WHERE idempotency_key = %s', $key ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public function plaintext( array $message ): string {
		return $this->crypto->decrypt( (string) $message['message_cipher'], (string) $message['message_nonce'] );
	}

	/** @param array<string,mixed> $changes */
	public function update( int $id, array $changes ): bool {
		global $wpdb;
		$changes['updated_at_gmt'] = gmdate( 'Y-m-d H:i:s' );
		return false !== $wpdb->update( $this->messages_table(), $changes, array( 'id' => $id ) );
	}

	public function claim( int $id ): ?array {
		global $wpdb;
		$now     = gmdate( 'Y-m-d H:i:s' );
		$claimed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->messages_table() . " SET status = 'processing', updated_at_gmt = %s WHERE id = %d AND status IN ('captured','queued','retry_scheduled')",
				$now,
				$id
			)
		);
		return 1 === $claimed ? $this->find( $id ) : null;
	}

	/** @param array<string,mixed> $attempt */
	public function record_attempt( int $message_id, array $attempt ): void {
		global $wpdb;
		$wpdb->insert(
			$this->attempts_table(),
			array(
				'message_id'       => $message_id,
				'attempt_number'   => (int) $attempt['attempt_number'],
				'status'           => sanitize_key( (string) $attempt['status'] ),
				'http_status'      => $attempt['http_status'] ?: null,
				'error_code'       => sanitize_key( (string) ( $attempt['error_code'] ?? '' ) ),
				'error_message'    => mb_substr( sanitize_text_field( (string) ( $attempt['error_message'] ?? '' ) ), 0, 255 ),
				'duration_ms'      => max( 0, (int) ( $attempt['duration_ms'] ?? 0 ) ),
				'created_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	public function reset_for_retry( int $id ): bool {
		$message = $this->find( $id );
		if ( ! $message || ! in_array( $message['status'], array( 'dead', 'failed', 'retry_scheduled' ), true ) ) {
			return false;
		}
		return $this->update(
			$id,
			array(
				'status'             => 'queued',
				'next_attempt_gmt'   => null,
				'last_error_code'    => '',
				'last_error_message' => '',
			)
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function recent( int $limit = 50, string $status = '' ): array {
		global $wpdb;
		$limit = max( 1, min( 200, $limit ) );
		if ( '' !== $status ) {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . $this->messages_table() . ' WHERE status = %s ORDER BY id DESC LIMIT %d', $status, $limit );
		} else {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . $this->messages_table() . ' ORDER BY id DESC LIMIT %d', $limit );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public function count_by_status( string $status ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->messages_table() . ' WHERE status = %s', $status ) );
	}

	/** @return array<int,int> */
	public function recoverable( int $limit = 100 ): array {
		global $wpdb;
		$stale_processing = gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );
		$stale_queued     = gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS );
		$now              = gmdate( 'Y-m-d H:i:s' );
		$ids              = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->messages_table() . " WHERE (status IN ('captured','queued') AND updated_at_gmt < %s) OR (status = 'processing' AND updated_at_gmt < %s) OR (status = 'retry_scheduled' AND next_attempt_gmt <= %s) ORDER BY id ASC LIMIT %d",
				$stale_queued,
				$stale_processing,
				$now,
				max( 1, min( 500, $limit ) )
			)
		);
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$this->update( (int) $id, array( 'status' => 'queued', 'next_attempt_gmt' => null ) );
		}
		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	public function cleanup(): void {
		global $wpdb;
		$success_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		$failure_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) );
		$meta_cutoff    = gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS );

		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $this->messages_table() . " SET message_cipher = '', message_nonce = '' WHERE status IN ('succeeded','shadowed') AND completed_at_gmt < %s", $success_cutoff ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $this->messages_table() . " SET message_cipher = '', message_nonce = '' WHERE status IN ('dead','failed') AND updated_at_gmt < %s", $failure_cutoff ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->attempts_table() . ' WHERE created_at_gmt < %s', $meta_cutoff ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->messages_table() . ' WHERE created_at_gmt < %s', $meta_cutoff ) );
	}

	public function erase_source( string $source, string $source_id ): int {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $this->messages_table() . ' WHERE source = %s AND source_id = %s', $source, $source_id ) );
		if ( $ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->attempts_table() . " WHERE message_id IN ({$placeholders})", ...array_map( 'intval', $ids ) ) );
		}
		return (int) $wpdb->delete( $this->messages_table(), array( 'source' => $source, 'source_id' => $source_id ) );
	}

	private function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'iwmm_messages';
	}

	private function attempts_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'iwmm_attempts';
	}
}
