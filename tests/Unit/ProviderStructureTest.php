<?php

declare(strict_types=1);

use IntegrateWPFormsMattermost\WPForms\Provider\Core;
use IntegrateWPFormsMattermost\WPForms\Provider\Process;
use IntegrateWPFormsMattermost\WPForms\Provider\Settings\FormBuilder;
use IntegrateWPFormsMattermost\WPForms\Provider\Settings\PageIntegrations;
use PHPUnit\Framework\TestCase;

final class ProviderStructureTest extends TestCase {
	public function test_classes_use_the_native_wpforms_provider_contracts(): void {
		self::assertTrue( is_subclass_of( Core::class, \WPForms\Providers\Provider\Core::class ) );
		self::assertTrue( is_subclass_of( Process::class, \WPForms\Providers\Provider\Process::class ) );
		self::assertTrue( is_subclass_of( FormBuilder::class, \WPForms\Providers\Provider\Settings\FormBuilder::class ) );
		self::assertTrue( is_subclass_of( PageIntegrations::class, \WPForms\Providers\Provider\Settings\PageIntegrations::class ) );
	}
}
