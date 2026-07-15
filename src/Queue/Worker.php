<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Queue;

use IntegrateWPFormsMattermost\Mattermost\Client;
use IntegrateWPFormsMattermost\Mattermost\Response;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use IntegrateWPFormsMattermost\Storage\MessageRepository;

final class Worker {
	private RetryPolicy $retry_policy;
	private Client $client;

	public function __construct(
		private MessageRepository $repository,
		private Scheduler $scheduler,
		ConnectionSettings $settings
	) {
		$this->retry_policy = new RetryPolicy();
		$this->client       = new Client( $settings );
	}

	public function hooks(): void {
		add_action( 'iwmm_process_message', array( $this, 'process' ) );
	}

	public function process( int $message_id ): void {
		$message = $this->repository->claim( $message_id );
		if ( ! $message ) {
			return;
		}

		if ( 'shadow' === $message['mode'] ) {
			$this->repository->update(
				$message_id,
				array(
					'status'           => 'shadowed',
					'completed_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			do_action( 'iwmm_message_succeeded', $message_id, 'shadowed' );
			return;
		}

		$attempt = (int) $message['attempts'] + 1;
		$started = microtime( true );
		if ( $attempt > 1 && 'ambiguous' === $message['last_error_code'] ) {
			$since_ms = strtotime( (string) $message['created_at_gmt'] . ' UTC' ) * 1000;
			$post_id  = $this->client->find_post( (string) $message['channel_id'], (string) $message['uuid'], $since_ms );
			if ( '' !== $post_id ) {
				$this->succeed( $message_id, $message, $attempt, $post_id, $started, 200 );
				return;
			}
		}

		try {
			$plaintext = $this->repository->plaintext( $message );
		} catch ( \Throwable ) {
			$this->fail_permanently( $message_id, $message, $attempt, new Response( 0, array(), 'decrypt_failed', 'Message payload could not be decrypted.' ), $started );
			return;
		}

		$response = $this->client->create_post( (string) $message['channel_id'], $plaintext, (string) $message['uuid'] );
		if ( $response->successful() ) {
			$this->succeed( $message_id, $message, $attempt, (string) ( $response->body['id'] ?? '' ), $started, $response->status );
			return;
		}

		$is_transient = 0 === $response->status || $this->retry_policy->transient( $response->status );
		if ( $is_transient && $attempt < $this->retry_policy->max_attempts() ) {
			$this->retry( $message_id, $message, $attempt, $response, $started );
			return;
		}

		$this->fail_permanently( $message_id, $message, $attempt, $response, $started );
	}

	/** @param array<string,mixed> $message */
	private function succeed( int $id, array $message, int $attempt, string $post_id, float $started, int $http_status ): void {
		$this->repository->record_attempt(
			$id,
			array(
				'attempt_number' => $attempt,
				'status'         => 'succeeded',
				'http_status'    => $http_status,
				'duration_ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
		$this->repository->update(
			$id,
			array(
				'status'             => 'succeeded',
				'attempts'           => $attempt,
				'remote_post_id'     => sanitize_text_field( $post_id ),
				'completed_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
				'last_error_code'    => '',
				'last_error_message' => '',
			)
		);
		do_action( 'iwmm_message_succeeded', $id, $post_id, $message['source'], $message['source_id'] );
	}

	/** @param array<string,mixed> $message */
	private function retry( int $id, array $message, int $attempt, Response $response, float $started ): void {
		$delay = $this->retry_policy->delay( $attempt, $response->retry_after );
		$code  = $response->ambiguous ? 'ambiguous' : ( $response->error_code ?: 'transient_failure' );
		$this->repository->record_attempt(
			$id,
			array(
				'attempt_number' => $attempt,
				'status'         => 'retry_scheduled',
				'http_status'    => $response->status,
				'error_code'     => $code,
				'error_message'  => $response->error_message,
				'duration_ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
		$this->repository->update(
			$id,
			array(
				'status'             => 'retry_scheduled',
				'attempts'           => $attempt,
				'next_attempt_gmt'   => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'last_error_code'    => $code,
				'last_error_message' => mb_substr( $response->error_message ?: 'Transient Mattermost failure.', 0, 255 ),
			)
		);
		$this->scheduler->enqueue( $id, $delay );
	}

	/** @param array<string,mixed> $message */
	private function fail_permanently( int $id, array $message, int $attempt, Response $response, float $started ): void {
		$code = $response->error_code ?: 'delivery_failed';
		$this->repository->record_attempt(
			$id,
			array(
				'attempt_number' => $attempt,
				'status'         => 'dead',
				'http_status'    => $response->status,
				'error_code'     => $code,
				'error_message'  => $response->error_message,
				'duration_ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
		$this->repository->update(
			$id,
			array(
				'status'             => 'dead',
				'attempts'           => $attempt,
				'last_error_code'    => $code,
				'last_error_message' => mb_substr( $response->error_message ?: 'Mattermost delivery failed.', 0, 255 ),
			)
		);
		do_action( 'iwmm_message_failed', $id, $code, $message['source'], $message['source_id'] );
		$this->alert( $id, $code );
	}

	private function alert( int $id, string $code ): void {
		$last = (int) get_transient( 'iwmm_failure_alert' );
		if ( $last > time() - HOUR_IN_SECONDS ) {
			return;
		}
		set_transient( 'iwmm_failure_alert', time(), HOUR_IN_SECONDS );
		wp_mail( get_option( 'admin_email' ), 'Mattermost automation needs attention', sprintf( 'Message %d entered the dead-letter queue (%s). Review it in WordPress.', $id, $code ) );
	}
}
