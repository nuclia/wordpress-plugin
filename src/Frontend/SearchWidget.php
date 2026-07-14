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
	private const DEFAULT_FEATURES = [ 'answers', 'rephrase', 'filter', 'suggestions' ];

	public function __construct( private readonly SettingsRepository $settings ) {
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'init', [ $this, 'register_block' ] );
		add_shortcode( 'progress_agentic_rag_search', [ $this, 'shortcode' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
	}

	public function enqueue_assets(): void {
		if ( ! $this->can_render() || ! $this->request_has_widget_placement() ) {
			return;
		}

		$this->enqueue_frontend_assets();
	}

	private function enqueue_frontend_assets(): void {
		$frontend_style_version = filemtime( PROGRESS_AGENTIC_RAG_PATH . 'assets/css/frontend.css' );
		$frontend_script_version = filemtime( PROGRESS_AGENTIC_RAG_PATH . 'assets/js/frontend.js' );

		wp_enqueue_style(
			'progress-agentic-rag-frontend',
			PROGRESS_AGENTIC_RAG_URL . 'assets/css/frontend.css',
			[],
			false !== $frontend_style_version ? (string) $frontend_style_version : PROGRESS_AGENTIC_RAG_VERSION
		);

		wp_enqueue_script(
			'progress-agentic-rag-widget',
			'https://cdn.rag.progress.cloud/nuclia-widget.umd.js',
			[],
			null,
			true
		);

		wp_enqueue_script(
			'progress-agentic-rag-frontend',
			PROGRESS_AGENTIC_RAG_URL . 'assets/js/frontend.js',
			[ 'progress-agentic-rag-widget' ],
			false !== $frontend_script_version ? (string) $frontend_script_version : PROGRESS_AGENTIC_RAG_VERSION,
			true
		);
	}

	/**
	 * @param array<string, mixed> $attributes Widget attributes.
	 */
	public function render( array $attributes = [] ): void {
		echo $this->render_markup( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes all dynamic output.
	}

	/**
	 * @param array<string, mixed> $attributes Widget attributes.
	 */
	public function render_markup( array $attributes = [] ): string {
		if ( ! $this->can_render() ) {
			return '';
		}

		$zone           = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid           = $this->settings->get_string( SettingsRepository::OPTION_KBID );
		$proxy_url      = ProxyController::proxy_url( $zone );
		$widget_options = $this->widget_options( $attributes );
		$response_css_url = add_query_arg( [ 'ver' => PROGRESS_AGENTIC_RAG_VERSION ], PROGRESS_AGENTIC_RAG_URL . 'assets/css/widget-response.css' );
		$response_style   = $this->response_style( $this->settings->get_widget_appearance() );

		if ( '' === $proxy_url ) {
			return '';
		}

		$this->enqueue_frontend_assets();

		ob_start();
		require PROGRESS_AGENTIC_RAG_PATH . 'templates/frontend/search-widget.php';

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 */
	public function shortcode( array|string $attributes = [], ?string $content = null, string $tag = '' ): string {
		return $this->render_markup( is_array( $attributes ) ? $attributes : [] );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_block( array $attributes = [], string $content = '', mixed $block = null ): string {
		return $this->render_markup( $attributes );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) || ! function_exists( 'wp_register_script' ) ) {
			return;
		}

		wp_register_script(
			'progress-agentic-rag-search-block',
			PROGRESS_AGENTIC_RAG_URL . 'assets/js/search-block.js',
			[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
			PROGRESS_AGENTIC_RAG_VERSION,
			true
		);

		register_block_type(
			'progress-agentic-rag/search',
			[
				'api_version'     => 2,
				'attributes'      => [
					'features' => [
						'type'    => 'array',
						'default' => self::DEFAULT_FEATURES,
					],
				],
				'editor_script'   => 'progress-agentic-rag-search-block',
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	public function register_elementor_widget( mixed $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once PROGRESS_AGENTIC_RAG_PATH . 'src/Frontend/ElementorSearchWidget.php';

		$widget = new ElementorSearchWidget( $this );
		if ( is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( $widget );
			return;
		}

		if ( is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( $widget );
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function feature_options(): array {
		return [
			'answers'     => __( 'Answers', 'progress-agentic-rag' ),
			'rephrase'    => __( 'Rephrase', 'progress-agentic-rag' ),
			'filter'      => __( 'Filters', 'progress-agentic-rag' ),
			'suggestions' => __( 'Suggestions', 'progress-agentic-rag' ),
		];
	}

	/**
	 * @return list<string>
	 */
	public static function default_features(): array {
		return self::DEFAULT_FEATURES;
	}

	private function can_render(): bool {
		return ! is_admin()
			&& $this->settings->get_api_is_reachable()
			&& '' !== $this->settings->get_string( SettingsRepository::OPTION_ZONE )
			&& '' !== $this->settings->get_string( SettingsRepository::OPTION_KBID )
			&& $this->settings->has_token();
	}

	private function request_has_widget_placement(): bool {
		$post = $GLOBALS['post'] ?? null;
		$content = is_object( $post ) && isset( $post->post_content ) ? (string) $post->post_content : '';

		if ( '' === $content ) {
			return false;
		}

		if ( function_exists( 'has_shortcode' ) && has_shortcode( $content, 'progress_agentic_rag_search' ) ) {
			return true;
		}

		if ( function_exists( 'has_block' ) && has_block( 'progress-agentic-rag/search', $post ) ) {
			return true;
		}

		return str_contains( $content, '[progress_agentic_rag_search' ) || str_contains( $content, '<!-- wp:progress-agentic-rag/search' );
	}

	/**
	 * @param array<string, mixed> $attributes Widget attributes.
	 *
	 * @return array{features:string}
	 */
	private function widget_options( array $attributes ): array {
		return [
			'features' => implode( ',', $this->sanitize_features( $attributes['features'] ?? self::DEFAULT_FEATURES ) ),
		];
	}

	/**
	 * @param array<string, string|int|float> $appearance Saved widget appearance.
	 */
	private function response_style( array $appearance ): string {
		$font_families = [
			'roboto' => 'Roboto, Arial, sans-serif',
			'inter'  => 'Inter, Arial, sans-serif',
			'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
		];
		$shadows = [
			'none'   => 'none',
			'subtle' => '0 1px 2px rgb(16 24 40 / 5%)',
			'soft'   => '0 12px 32px rgb(16 24 40 / 8%)',
		];

		return implode(
			';',
			[
				'--progress-agentic-rag-widget-accent-color:' . $appearance['accent_color'],
				'--progress-agentic-rag-widget-text-color:' . $appearance['text_color'],
				'--progress-agentic-rag-widget-muted-color:' . $appearance['muted_color'],
				'--progress-agentic-rag-widget-surface-color:' . $appearance['surface_color'],
				'--progress-agentic-rag-widget-border-color:' . $appearance['border_color'],
				'--progress-agentic-rag-widget-font-family:' . $font_families[ (string) $appearance['font_family'] ],
				'--progress-agentic-rag-widget-font-size:' . $appearance['font_size'] . 'px',
				'--progress-agentic-rag-widget-line-height:' . $appearance['line_height'],
				'--progress-agentic-rag-widget-border-radius:' . $appearance['border_radius'] . 'px',
				'--progress-agentic-rag-widget-card-padding:' . $appearance['card_padding'] . 'px',
				'--progress-agentic-rag-widget-shadow:' . $shadows[ (string) $appearance['shadow'] ],
			]
		) . ';';
	}

	/**
	 * @return list<string>
	 */
	private function sanitize_features( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return self::DEFAULT_FEATURES;
		}

		$features = [];
		foreach ( $value as $feature ) {
			$feature = sanitize_key( (string) $feature );
			if ( in_array( $feature, self::DEFAULT_FEATURES, true ) ) {
				$features[] = $feature;
			}
		}

		$features = array_values( array_unique( $features ) );

		return empty( $features ) ? self::DEFAULT_FEATURES : $features;
	}
}
