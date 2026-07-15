<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Mattermost;

use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use WP_Error;

final class Client {
	public function __construct(
		private ConnectionSettings $settings,
		private string $base_url = '',
		private string $token = ''
	) {}

	public function create_post( string $channel_id, string $message, string $idempotency_id ): Response {
		return $this->request(
			'POST',
			'/api/v4/posts',
			array(
				'channel_id' => $channel_id,
				'message'    => $message,
				'props'      => array( 'iwmm_id' => $idempotency_id ),
			)
		);
	}

	public function test_connection(): Response {
		return $this->request( 'GET', '/api/v4/users/me' );
	}

	/** @return array<int,array{id:string,name:string,display_name:string,team_id:string,team_name:string}>|WP_Error */
	public function channels(): array|WP_Error {
		$response = $this->request( 'GET', '/api/v4/users/me/teams' );
		if ( ! $response->successful() ) {
			return new WP_Error( $response->error_code ?: 'iwmm_channels_failed', $response->error_message ?: 'Unable to load Mattermost teams.' );
		}
		$channels = array();
		foreach ( $response->body as $team ) {
			if ( empty( $team['id'] ) ) {
				continue;
			}
			$team_channels = $this->request( 'GET', '/api/v4/users/me/teams/' . rawurlencode( (string) $team['id'] ) . '/channels' );
			if ( ! $team_channels->successful() ) {
				continue;
			}
			foreach ( $team_channels->body as $channel ) {
				if ( empty( $channel['id'] ) ) {
					continue;
				}
				$channels[] = array(
					'id'           => (string) $channel['id'],
					'name'         => (string) ( $channel['name'] ?? '' ),
					'display_name' => (string) ( $channel['display_name'] ?? $channel['name'] ?? '' ),
					'team_id'      => (string) $team['id'],
					'team_name'    => (string) ( $team['display_name'] ?? $team['name'] ?? $team['id'] ),
				);
			}
		}
		return $channels;
	}

	public function find_post( string $channel_id, string $idempotency_id, int $since_ms ): string {
		$response = $this->request( 'GET', '/api/v4/channels/' . rawurlencode( $channel_id ) . '/posts?since=' . $since_ms );
		if ( ! $response->successful() || empty( $response->body['posts'] ) || ! is_array( $response->body['posts'] ) ) {
			return '';
		}
		foreach ( $response->body['posts'] as $post ) {
			if ( ( $post['props']['iwmm_id'] ?? '' ) === $idempotency_id ) {
				return (string) ( $post['id'] ?? '' );
			}
		}
		return '';
	}

	/** @param array<string,mixed>|null $body */
	private function request( string $method, string $path, ?array $body = null ): Response {
		$base_url = '' !== $this->base_url ? untrailingslashit( $this->base_url ) : $this->settings->base_url();
		$token    = '' !== $this->token ? $this->token : $this->settings->token();
		if ( '' === $base_url || '' === $token ) {
			return new Response( 0, array(), 'not_configured', 'Mattermost is not configured.' );
		}
		$args = array(
			'method'      => $method,
			'timeout'     => 10,
			'redirection' => 2,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $base_url . $path, $args );
		if ( is_wp_error( $response ) ) {
			$code      = $response->get_error_code();
			$ambiguous = in_array( $code, array( 'http_request_failed', 'connect_timeout', 'operation_timedout' ), true );
			return new Response( 0, array(), sanitize_key( $code ), 'Mattermost request failed.', null, $ambiguous );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();
		$headers = wp_remote_retrieve_headers( $response );
		$retry   = null;
		if ( isset( $headers['retry-after'] ) && is_numeric( $headers['retry-after'] ) ) {
			$retry = (int) $headers['retry-after'];
		} elseif ( isset( $headers['x-ratelimit-reset'] ) && is_numeric( $headers['x-ratelimit-reset'] ) ) {
			$retry = max( 1, (int) $headers['x-ratelimit-reset'] - time() );
		}

		return new Response(
			$status,
			$decoded,
			sanitize_key( (string) ( $decoded['id'] ?? '' ) ),
			$status >= 400 ? 'Mattermost returned an error.' : '',
			$retry
		);
	}
}
