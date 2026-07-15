<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost;

use IntegrateWPFormsMattermost\Queue\Scheduler;
use IntegrateWPFormsMattermost\Security\Crypto;
use IntegrateWPFormsMattermost\Storage\MessageRepository;
use WP_Error;

final class MessageService {
	public function __construct(
		private MessageRepository $repository,
		private Scheduler $scheduler,
		private Crypto $crypto
	) {}

	/**
	 * @param array<string,mixed> $request Message request.
	 * @return int|WP_Error
	 */
	public function enqueue( array $request ) {
		$required = array( 'idempotency_key', 'channel_id', 'message' );
		foreach ( $required as $key ) {
			if ( ! isset( $request[ $key ] ) || '' === trim( (string) $request[ $key ] ) ) {
				return new WP_Error( 'iwmm_invalid_request', sprintf( 'Missing required field: %s.', $key ) );
			}
		}

		if ( ! $this->crypto->available() ) {
			return new WP_Error( 'iwmm_crypto_unavailable', 'The sodium PHP extension is required.' );
		}

		$channel = sanitize_text_field( (string) $request['channel_id'] );
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $channel ) ) {
			return new WP_Error( 'iwmm_invalid_channel', 'The Mattermost channel ID is invalid.' );
		}

		$idempotency = hash( 'sha256', (string) $request['idempotency_key'] );
		$existing    = $this->repository->find_by_idempotency_key( $idempotency );
		if ( $existing ) {
			return (int) $existing['id'];
		}

		$mode = isset( $request['mode'] ) && in_array( $request['mode'], array( 'shadow', 'live' ), true ) ? $request['mode'] : 'live';
		$id   = $this->repository->insert(
			array(
				'idempotency_key' => $idempotency,
				'channel_id'      => $channel,
				'message'         => (string) $request['message'],
				'mode'            => $mode,
				'source'          => sanitize_key( (string) ( $request['source'] ?? 'api' ) ),
				'source_id'       => sanitize_text_field( (string) ( $request['source_id'] ?? '' ) ),
				'feed_id'         => sanitize_text_field( (string) ( $request['feed_id'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$this->scheduler->enqueue( (int) $id );
		return (int) $id;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function public_status( int $message_id ) {
		$message = $this->repository->find( $message_id );
		if ( ! $message ) {
			return new WP_Error( 'iwmm_message_not_found', 'Message not found.' );
		}

		return array_intersect_key(
			$message,
			array_flip( array( 'id', 'uuid', 'source', 'source_id', 'feed_id', 'channel_id', 'status', 'mode', 'attempts', 'remote_post_id', 'created_at_gmt', 'updated_at_gmt', 'completed_at_gmt', 'last_error_code', 'last_error_message' ) )
		);
	}

	public function retry( int $message_id ): bool {
		if ( ! $this->repository->reset_for_retry( $message_id ) ) {
			return false;
		}
		$this->scheduler->enqueue( $message_id );
		return true;
	}

	public function promote_shadow( int $message_id ): bool {
		$message = $this->repository->find( $message_id );
		if ( ! $message || 'shadowed' !== $message['status'] || '' === (string) $message['message_cipher'] ) {
			return false;
		}
		if ( ! $this->repository->update( $message_id, array( 'mode' => 'live', 'status' => 'queued', 'completed_at_gmt' => null ) ) ) {
			return false;
		}
		$this->scheduler->enqueue( $message_id );
		return true;
	}

	public function reconcile_queue(): int {
		$ids = $this->repository->recoverable();
		foreach ( $ids as $id ) {
			$this->scheduler->enqueue( $id );
		}
		return count( $ids );
	}
}
