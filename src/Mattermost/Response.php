<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\Mattermost;

final class Response {
	/** @param array<string,mixed> $body */
	public function __construct(
		public readonly int $status,
		public readonly array $body = array(),
		public readonly string $error_code = '',
		public readonly string $error_message = '',
		public readonly ?int $retry_after = null,
		public readonly bool $ambiguous = false
	) {}

	public function successful(): bool {
		return $this->status >= 200 && $this->status < 300;
	}
}
