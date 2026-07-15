<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

use IntegrateWPFormsMattermost\MessageService;
use IntegrateWPFormsMattermost\Plugin;
use WP_Error;

final class FeedListener {
	private FeedEvaluator $evaluator;
	private static string $request_uuid = '';

	public function __construct( private MessageService $messages ) {
		$this->evaluator = new FeedEvaluator();
	}

	/**
	 * @param array<int|string,array<string,mixed>> $fields Submitted fields.
	 * @param array<string,mixed> $entry Raw entry metadata.
	 * @param array<string,mixed> $form_data Form definition.
	 */
	public function capture( array $fields, array $entry, array $form_data, int $entry_id ): void {
		$feeds = $this->feeds( $form_data );
		if ( array() === $feeds ) {
			return;
		}
		$form_id = absint( $form_data['id'] ?? 0 );
		$source  = $entry_id > 0 ? (string) $entry_id : $this->request_uuid();

		foreach ( $feeds as $feed_id => $feed ) {
			if ( ! is_array( $feed ) ) {
				continue;
			}
			$origin = hash( 'sha256', untrailingslashit( home_url() ) );
			$mode   = isset( $feed['origin'] ) && hash_equals( $origin, (string) $feed['origin'] ) && in_array( $feed['mode'] ?? 'disabled', array( 'shadow', 'live' ), true ) ? $feed['mode'] : 'disabled';
			if ( 'disabled' === $mode || ! $this->evaluator->matches( $feed, $fields ) ) {
				continue;
			}
			$template = (string) ( $feed['message'] ?? '' );
			$message  = $this->render_smart_tags( $template, $form_data, $fields, $entry_id );
			if ( '' === trim( $message ) ) {
				continue;
			}
			$this->messages->enqueue(
				array(
					'idempotency_key' => implode( ':', array( home_url(), $form_id, $source, (string) $feed_id ) ),
					'channel_id'      => (string) ( $feed['channel_id'] ?? '' ),
					'message'         => $message,
					'mode'            => $mode,
					'source'          => 'wpforms',
					'source_id'       => $source,
					'feed_id'         => (string) $feed_id,
				)
			);
		}
	}

	/** @return string|WP_Error */
	public function preview( int $form_id, int $entry_id, string $feed_id ) {
		global $wpdb;
		$form = get_post( $form_id );
		if ( ! $form || 'wpforms' !== $form->post_type ) {
			return new WP_Error( 'iwmm_preview_form', 'Form not found.' );
		}
		$form_data = json_decode( (string) $form->post_content, true );
		if ( ! is_array( $form_data ) ) {
			return new WP_Error( 'iwmm_preview_form', 'Form data is invalid.' );
		}
		$feeds = $this->feeds( $form_data );
		$feed  = $feeds[ $feed_id ] ?? null;
		if ( ! is_array( $feed ) ) {
			return new WP_Error( 'iwmm_preview_feed', 'Feed not found.' );
		}
		$table = $wpdb->prefix . 'wpforms_entries';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT fields FROM {$table} WHERE entry_id = %d AND form_id = %d", $entry_id, $form_id ), ARRAY_A );
		$fields = is_array( $row ) ? json_decode( (string) $row['fields'], true ) : null;
		if ( ! is_array( $fields ) ) {
			return new WP_Error( 'iwmm_preview_entry', 'Saved entry not found.' );
		}
		if ( ! $this->evaluator->matches( $feed, $fields ) ) {
			return new WP_Error( 'iwmm_preview_conditions', 'This saved entry does not match the feed conditions.' );
		}
		$form_data['id'] = $form_id;
		return $this->render_smart_tags( (string) ( $feed['message'] ?? '' ), $form_data, $fields, $entry_id );
	}

	/** @param array<string,mixed> $form_data @param array<int|string,array<string,mixed>> $fields */
	private function render_smart_tags( string $template, array $form_data, array $fields, int $entry_id ): string {
		if ( function_exists( 'wpforms' ) && isset( wpforms()->smart_tags ) && is_callable( array( wpforms()->smart_tags, 'process' ) ) ) {
			return (string) wpforms()->smart_tags->process( $template, $form_data, $fields, $entry_id );
		}
		foreach ( $fields as $field_id => $field ) {
			$template = str_replace( '{field_id="' . $field_id . '"}', (string) ( $field['value'] ?? '' ), $template );
		}
		return $template;
	}

	/** @param array<string,mixed> $form_data @return array<string,array<string,mixed>> */
	public function feeds( array $form_data ): array {
		$feeds = $form_data['providers'][ Plugin::PROVIDER_SLUG ] ?? null;
		if ( is_array( $feeds ) && array() !== $feeds ) {
			return $feeds;
		}

		$legacy = $form_data['settings']['iwmm']['feeds'] ?? array();
		return is_array( $legacy ) ? $legacy : array();
	}

	private function request_uuid(): string {
		if ( '' === self::$request_uuid ) {
			self::$request_uuid = wp_generate_uuid4();
		}
		return self::$request_uuid;
	}
}
