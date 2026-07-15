<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Queue;

final class Scheduler {
	public function enqueue( int $message_id, int $delay = 0 ): void {
		$args = array( $message_id );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$timestamp = time() + max( 1, $delay );
			if ( ! as_has_scheduled_action( 'iwmm_process_message', $args, 'integrate-wpforms-mattermost' ) ) {
				as_schedule_single_action( $timestamp, 'iwmm_process_message', $args, 'integrate-wpforms-mattermost', true );
			}
			return;
		}
		if ( ! wp_next_scheduled( 'iwmm_process_message', $args ) ) {
			wp_schedule_single_event( time() + max( 1, $delay ), 'iwmm_process_message', $args );
		}
	}
}
