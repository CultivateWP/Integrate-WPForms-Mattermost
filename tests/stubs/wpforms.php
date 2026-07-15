<?php

declare(strict_types=1);

namespace WPForms\Providers\Provider {
	abstract class Core {
		public string $slug;
		public string $name;
		public string $icon;
		/** @param array<string,string> $params */
		public function __construct( array $params ) {}
		public static function get_instance(): static { return new static(); }
		abstract public function get_process();
		abstract public function get_page_integrations();
		abstract public function get_form_builder();
	}

	abstract class Process {
		public function __construct( Core $core ) {}
		abstract public function process( $fields, $entry, $form_data, $entry_id );
	}
}

namespace WPForms\Providers\Provider\Settings {
	use WPForms\Providers\Provider\Core;

	abstract class FormBuilder {
		protected Core $core;
		/** @var array<string,mixed> */
		protected array $form_data = array();
		public function __construct( Core $core ) { $this->core = $core; }
		protected function init_hooks(): void {}
		public function enqueue_assets(): void {}
		public function display_sidebar(): void {}
		abstract public function display_content(): void;
		public function builder_custom_templates(): void {}
	}

	abstract class PageIntegrations {
		protected Core $core;
		public function __construct( Core $core ) { $this->core = $core; }
		public function ajax_connect(): void {}
		public function ajax_disconnect(): void {}
		protected function display_add_new(): void {}
		protected function display_connected_account( $account_id, $account ): void {}
	}
}

namespace WPForms\Providers {
	use WPForms\Providers\Provider\Core;

	final class Providers {
		public static function get_instance(): self { return new self(); }
		public function register( Core $provider ): void {}
	}
}

namespace {
	/** @return array<string,mixed> */
	function wpforms_get_providers_options( string $provider = '' ): array { return array(); }
	/** @param array<string,mixed> $options */
	function wpforms_update_providers_options( string $provider, array $options, string $key ): void {}
	/** @param array<string,mixed> $form_data */
	function wpforms_encode( array $form_data ): string { return ''; }
	function wpforms_current_user_can( string $capability = '' ): bool { return true; }
	function delete_option( string $option ): bool { return true; }
}
