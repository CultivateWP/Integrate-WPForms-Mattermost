<?php

declare(strict_types=1);

namespace IntegrateWPFormsMattermost;

use IntegrateWPFormsMattermost\Admin\Admin;
use IntegrateWPFormsMattermost\CLI\Commands;
use IntegrateWPFormsMattermost\Privacy\Privacy;
use IntegrateWPFormsMattermost\Queue\Scheduler;
use IntegrateWPFormsMattermost\Queue\Worker;
use IntegrateWPFormsMattermost\Security\Crypto;
use IntegrateWPFormsMattermost\Settings\ConnectionSettings;
use IntegrateWPFormsMattermost\Storage\Installer;
use IntegrateWPFormsMattermost\Storage\MessageRepository;
use IntegrateWPFormsMattermost\WPForms\FeedListener;
use IntegrateWPFormsMattermost\WPForms\ImportGuard;
use IntegrateWPFormsMattermost\WPForms\Reconciler;

final class Plugin {
	private static ?self $instance = null;
	private bool $booted = false;
	private bool $loaded = false;
	private ?MessageService $messages = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public static function activate(): void {
		if ( class_exists( Installer::class ) ) {
			( new Installer() )->install();
		}
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$action_scheduler = IWMM_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		if ( is_readable( $action_scheduler ) ) {
			require_once $action_scheduler;
		}

		add_action( 'plugins_loaded', array( $this, 'load' ), 20 );
	}

	public function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		$crypto     = new Crypto();
		$repository = new MessageRepository( $crypto );
		$scheduler  = new Scheduler();
		$settings   = new ConnectionSettings( $crypto );

		$this->messages = new MessageService( $repository, $scheduler, $crypto );

		( new Worker( $repository, $scheduler, $settings ) )->hooks();
		$listener = new FeedListener( $this->messages );
		$listener->hooks();
		( new ImportGuard() )->hooks();
		( new Reconciler( $listener ) )->hooks();
		( new Admin( $repository, $settings, $this->messages, $listener ) )->hooks();
		( new Privacy( $repository ) )->hooks();

		add_action( 'iwmm_cleanup', array( $repository, 'cleanup' ) );
		add_action( 'iwmm_reconcile_queue', array( $this->messages, 'reconcile_queue' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'ensure_schedules' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register( $repository, $settings, $this->messages );
		}
	}

	public function messages(): MessageService {
		if ( null === $this->messages ) {
			$this->load();
		}

		return $this->messages;
	}

	public function ensure_schedules(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_has_scheduled_action( 'iwmm_cleanup', array(), 'integrate-wpforms-mattermost' ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'iwmm_cleanup', array(), 'integrate-wpforms-mattermost', true );
			}
			if ( ! as_has_scheduled_action( 'iwmm_reconcile_wpforms', array(), 'integrate-wpforms-mattermost' ) ) {
				as_schedule_recurring_action( time() + 15 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, 'iwmm_reconcile_wpforms', array(), 'integrate-wpforms-mattermost', true );
			}
			if ( ! as_has_scheduled_action( 'iwmm_reconcile_queue', array(), 'integrate-wpforms-mattermost' ) ) {
				as_schedule_recurring_action( time() + 15 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, 'iwmm_reconcile_queue', array(), 'integrate-wpforms-mattermost', true );
			}
			return;
		}

		if ( ! wp_next_scheduled( 'iwmm_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'iwmm_cleanup' );
		}
		if ( ! wp_next_scheduled( 'iwmm_reconcile_wpforms' ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'iwmm_fifteen_minutes', 'iwmm_reconcile_wpforms' );
		}
		if ( ! wp_next_scheduled( 'iwmm_reconcile_queue' ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'iwmm_fifteen_minutes', 'iwmm_reconcile_queue' );
		}
	}

	/** @param array<string,array<string,int|string>> $schedules @return array<string,array<string,int|string>> */
	public function cron_schedules( array $schedules ): array {
		$schedules['iwmm_fifteen_minutes'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes' );
		return $schedules;
	}
}
