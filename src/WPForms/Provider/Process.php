<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms\Provider;

use IntegrateWPFormsMattermost\WPForms\FeedListener;

final class Process extends \WPForms\Providers\Provider\Process {
	public function __construct( Core $core, private FeedListener $listener ) {
		parent::__construct( $core );
	}

	/**
	 * @param array<int|string,array<string,mixed>> $fields Submitted fields.
	 * @param array<string,mixed> $entry Raw entry metadata.
	 * @param array<string,mixed> $form_data Form definition.
	 */
	public function process( $fields, $entry, $form_data, $entry_id ): void {
		if ( ! is_array( $fields ) || ! is_array( $entry ) || ! is_array( $form_data ) ) {
			return;
		}

		$this->listener->capture( $fields, $entry, $form_data, (int) $entry_id );
	}
}
