<?php
/**
 * Main plugin coordinator.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag;

use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Admin\AdminPage;
use ProgressAgenticRag\Frontend\SearchWidget;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Indexing\Scheduler;
use ProgressAgenticRag\Proxy\ProxyController;
use ProgressAgenticRag\Settings\SettingsRepository;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;

	private AdminPage $admin_page;

	private ManualSync $manual_sync;

	private ProxyController $proxy_controller;

	private SearchWidget $search_widget;

	private SettingsRepository $settings;

	private function __construct() {
		$this->settings         = new SettingsRepository();
		$api_client             = new ApiClient( $this->settings );
		$scheduler              = new Scheduler();
		$this->manual_sync      = new ManualSync( $this->settings, $api_client, $scheduler );
		$this->admin_page       = new AdminPage( $this->settings, $this->manual_sync, $api_client );
		$this->proxy_controller = new ProxyController( $this->settings );
		$this->search_widget    = new SearchWidget( $this->settings );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		$this->proxy_controller->register();
		$this->search_widget->register();
		$this->manual_sync->register();
		$this->admin_page->register();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'progress-agentic-rag',
			false,
			dirname( PROGRESS_AGENTIC_RAG_BASENAME ) . '/languages'
		);
	}
}
