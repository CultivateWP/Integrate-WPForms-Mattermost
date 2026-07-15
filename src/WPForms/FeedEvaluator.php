<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms;

final class FeedEvaluator {
	/**
	 * @param array<string,mixed>              $feed   Feed configuration.
	 * @param array<int|string,array<string,mixed>> $fields Submitted fields.
	 */
	public function matches( array $feed, array $fields ): bool {
		$conditions = isset( $feed['conditions'] ) && is_array( $feed['conditions'] ) ? array_values( $feed['conditions'] ) : array();
		if ( array() === $conditions ) {
			return true;
		}
		$results = array_map( fn( array $condition ): bool => $this->condition_matches( $condition, $fields ), $conditions );
		return 'any' === ( $feed['condition_match'] ?? 'all' ) ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/** @param array<string,mixed> $condition @param array<int|string,array<string,mixed>> $fields */
	private function condition_matches( array $condition, array $fields ): bool {
		$field_id = (string) ( $condition['field_id'] ?? '' );
		$operator = (string) ( $condition['operator'] ?? 'equals' );
		$expected = $this->normalize( (string) ( $condition['value'] ?? '' ) );
		$value    = $this->normalize( (string) ( $fields[ $field_id ]['value'] ?? $fields[ (int) $field_id ]['value'] ?? '' ) );

		return match ( $operator ) {
			'not_equals'   => $value !== $expected,
			'contains'     => str_contains( $value, $expected ),
			'not_contains' => ! str_contains( $value, $expected ),
			'empty'        => '' === $value,
			'not_empty'    => '' !== $value,
			default        => $value === $expected,
		};
	}

	private function normalize( string $value ): string {
		return mb_strtolower( trim( wp_strip_all_tags( $value ) ) );
	}
}
