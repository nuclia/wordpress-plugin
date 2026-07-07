<?php
/**
 * Frontend Progress Agentic RAG search widget output.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Frontend;

use ProgressAgenticRag\Proxy\ProxyController;
use ProgressAgenticRag\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

final class SearchWidget {
	private bool $rendered = false;

	public function __construct( private readonly SettingsRepository $settings ) {
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_body_open', [ $this, 'render' ] );
		add_action( 'wp_footer', [ $this, 'render' ], 1 );
	}

	public function enqueue_assets(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		wp_enqueue_style(
			'progress-agentic-rag-frontend',
			PROGRESS_AGENTIC_RAG_URL . 'assets/css/frontend.css',
			[],
			PROGRESS_AGENTIC_RAG_VERSION
		);

		wp_enqueue_script(
			'progress-agentic-rag-widget',
			'https://cdn.rag.progress.cloud/nuclia-widget.umd.js',
			[],
			null,
			true
		);
	}

	public function render(): void {
		if ( $this->rendered || ! $this->should_render() ) {
			return;
		}

		$zone      = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid      = $this->settings->get_string( SettingsRepository::OPTION_KBID );
		$proxy_url = ProxyController::proxy_url( $zone );

		if ( '' === $proxy_url ) {
			return;
		}

		$this->rendered = true;

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/frontend/search-widget.php';
	}

	private function should_render(): bool {
		return ! is_admin()
			&& is_front_page()
			&& $this->settings->get_api_is_reachable()
			&& '' !== $this->settings->get_string( SettingsRepository::OPTION_ZONE )
			&& '' !== $this->settings->get_string( SettingsRepository::OPTION_KBID )
			&& $this->settings->has_token();
	}
}
