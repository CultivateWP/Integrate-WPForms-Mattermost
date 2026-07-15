<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\WPForms\FeedEvaluator;
use PHPUnit\Framework\TestCase;

final class FeedEvaluatorTest extends TestCase {
	public function test_feed_without_conditions_matches(): void {
		$this->assertTrue( ( new FeedEvaluator() )->matches( array(), array() ) );
	}

	public function test_all_conditions_must_match(): void {
		$feed = array(
			'condition_match' => 'all',
			'conditions'      => array(
				array( 'field_id' => '1', 'operator' => 'equals', 'value' => 'Yes' ),
				array( 'field_id' => '2', 'operator' => 'contains', 'value' => 'example' ),
			),
		);
		$fields = array( 1 => array( 'value' => 'yes' ), 2 => array( 'value' => 'hello@example.com' ) );
		$this->assertTrue( ( new FeedEvaluator() )->matches( $feed, $fields ) );
		$fields[2]['value'] = 'no match';
		$this->assertFalse( ( new FeedEvaluator() )->matches( $feed, $fields ) );
	}

	public function test_any_condition_and_empty_operators(): void {
		$feed = array(
			'condition_match' => 'any',
			'conditions'      => array(
				array( 'field_id' => '1', 'operator' => 'not_empty' ),
				array( 'field_id' => '2', 'operator' => 'empty' ),
			),
		);
		$this->assertTrue( ( new FeedEvaluator() )->matches( $feed, array( 1 => array( 'value' => '' ), 2 => array( 'value' => '' ) ) ) );
	}
}
