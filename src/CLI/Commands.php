<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\CLI;

use IntegrateWPFormsMattermost\Mattermost\Client;
use IntegrateWPFormsMattermost\MessageService;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use IntegrateWPFormsMattermost\Storage\MessageRepository;
use WP_CLI;

final class Commands {
	public function __construct(
		private MessageRepository $repository,
		private ConnectionSettings $settings,
		private MessageService $messages
	) {}

	public static function register( MessageRepository $repository, ConnectionSettings $settings, MessageService $messages ): void {
		WP_CLI::add_command( 'integrate-wpforms-mattermost', new self( $repository, $settings, $messages ) );
	}

	/** Show connection and queue health. */
	public function status(): void {
		WP_CLI::line( 'Configured: ' . ( $this->settings->configured() ? 'yes' : 'no' ) );
		foreach ( array( 'captured', 'queued', 'processing', 'retry_scheduled', 'succeeded', 'shadowed', 'dead' ) as $status ) {
			WP_CLI::line( sprintf( '%s: %d', $status, $this->repository->count_by_status( $status ) ) );
		}
	}

	/** List recent messages. */
	public function messages( array $args, array $assoc_args ): void {
		$rows = $this->repository->recent( (int) ( $assoc_args['limit'] ?? 50 ), sanitize_key( (string) ( $assoc_args['status'] ?? '' ) ) );
		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'source', 'source_id', 'channel_id', 'status', 'attempts', 'updated_at_gmt' ) );
	}

	/** Retry a dead or failed message. */
	public function retry( array $args ): void {
		$id = absint( $args[0] ?? 0 );
		$this->messages->retry( $id ) ? WP_CLI::success( 'Message queued.' ) : WP_CLI::error( 'Message could not be retried.' );
	}

	/** Promote a captured shadow message to live delivery. */
	public function promote( array $args ): void {
		$id = absint( $args[0] ?? 0 );
		$this->messages->promote_shadow( $id ) ? WP_CLI::success( 'Shadow message queued for live delivery.' ) : WP_CLI::error( 'Message could not be promoted.' );
	}

	/** Reconcile saved WPForms entries. */
	public function reconcile(): void {
		do_action( 'iwmm_reconcile_wpforms' );
		$count = $this->messages->reconcile_queue();
		WP_CLI::success( sprintf( 'WPForms reconciliation completed; %d stale queue records recovered.', $count ) );
	}

	/** Test the configured Mattermost credentials. */
	public function connection(): void {
		$response = ( new Client( $this->settings ) )->test_connection();
		$response->successful() ? WP_CLI::success( 'Connection succeeded.' ) : WP_CLI::error( 'Connection failed.' );
	}

	/** Purge records according to retention policy. */
	public function cleanup(): void {
		$this->repository->cleanup();
		WP_CLI::success( 'Retention cleanup completed.' );
	}
}
