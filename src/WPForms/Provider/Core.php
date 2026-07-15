<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost\WPForms\Provider;

use IntegrateWPFormsMattermost\Plugin;
use IntegrateWPFormsMattermost\WPForms\Provider\Settings\FormBuilder;
use IntegrateWPFormsMattermost\WPForms\Provider\Settings\PageIntegrations;

final class Core extends \WPForms\Providers\Provider\Core {
	public const PRIORITY = 40;
	public const SLUG = Plugin::PROVIDER_SLUG;

	public function __construct() {
		parent::__construct(
			array(
				'slug' => self::SLUG,
				'name' => __( 'Mattermost', 'integrate-wpforms-mattermost' ),
				'icon' => IWMM_URL . 'assets/mattermost.svg',
			)
		);
	}

	public function get_process(): Process {
		static $process;
		return $process ??= new Process( $this, Plugin::instance()->listener() );
	}

	public function get_page_integrations(): PageIntegrations {
		static $integrations;
		return $integrations ??= new PageIntegrations( $this, Plugin::instance()->connection_settings(), Plugin::instance()->provider_account() );
	}

	public function get_form_builder(): FormBuilder {
		static $builder;
		return $builder ??= new FormBuilder( $this );
	}
}
