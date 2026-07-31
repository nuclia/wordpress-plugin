<?php
/**
 * Frontend widget and proxy tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Frontend\SearchWidget;
use ProgressAgenticRag\Proxy\ProxyController;
use ProgressAgenticRag\Settings\SettingsRepository;

final class FrontendProxyTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_options'] = [];
		$GLOBALS['progress_agentic_rag_test_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_filters'] = [];
		$GLOBALS['progress_agentic_rag_test_enqueued_styles'] = [];
		$GLOBALS['progress_agentic_rag_test_enqueued_scripts'] = [];
		$GLOBALS['progress_agentic_rag_test_registered_scripts'] = [];
		$GLOBALS['progress_agentic_rag_test_registered_blocks'] = [];
		$GLOBALS['progress_agentic_rag_test_shortcodes'] = [];
		$GLOBALS['progress_agentic_rag_test_http_requests'] = [];
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
		$GLOBALS['progress_agentic_rag_test_query_vars'] = [];
		$GLOBALS['progress_agentic_rag_test_rewrite_rules'] = [];
		$GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] = [];
		$GLOBALS['progress_agentic_rag_test_is_admin'] = false;
		$GLOBALS['progress_agentic_rag_test_is_front_page'] = true;
		unset( $GLOBALS['post'] );
		$_SERVER = [];

		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		update_option( SettingsRepository::OPTION_KBID, 'kb-123' );
		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
	}

	public function test_search_widget_registers_explicit_placements_and_renders_without_token(): void {
		$widget = new SearchWidget( new SettingsRepository() );
		$widget->register();

		self::assertContains( 'wp_enqueue_scripts', array_column( $GLOBALS['progress_agentic_rag_test_actions'], 'hook_name' ) );
		self::assertContains( 'init', array_column( $GLOBALS['progress_agentic_rag_test_actions'], 'hook_name' ) );
		self::assertContains( 'elementor/widgets/register', array_column( $GLOBALS['progress_agentic_rag_test_actions'], 'hook_name' ) );
		self::assertArrayHasKey( 'progress_agentic_rag_search', $GLOBALS['progress_agentic_rag_test_shortcodes'] );

		$widget->register_block();

		self::assertArrayHasKey( 'progress-agentic-rag/search', $GLOBALS['progress_agentic_rag_test_registered_blocks'] );
		self::assertSame( 'progress-agentic-rag-search-block', $GLOBALS['progress_agentic_rag_test_registered_blocks']['progress-agentic-rag/search']['editor_script'] );

		$GLOBALS['post'] = (object) [
			'post_content' => '[progress_agentic_rag_search]',
		];
		$widget->enqueue_assets();

		self::assertSame( 'progress-agentic-rag-frontend', $GLOBALS['progress_agentic_rag_test_enqueued_styles'][0]['handle'] );
		self::assertSame( (string) filemtime( PROGRESS_AGENTIC_RAG_PATH . 'assets/css/frontend.css' ), $GLOBALS['progress_agentic_rag_test_enqueued_styles'][0]['ver'] );
		self::assertSame( 'progress-agentic-rag-widget', $GLOBALS['progress_agentic_rag_test_enqueued_scripts'][0]['handle'] );
		self::assertSame( 'progress-agentic-rag-frontend', $GLOBALS['progress_agentic_rag_test_enqueued_scripts'][1]['handle'] );
		self::assertSame( [ 'progress-agentic-rag-widget' ], $GLOBALS['progress_agentic_rag_test_enqueued_scripts'][1]['deps'] );
		self::assertSame( (string) filemtime( PROGRESS_AGENTIC_RAG_PATH . 'assets/js/frontend.js' ), $GLOBALS['progress_agentic_rag_test_enqueued_scripts'][1]['ver'] );

		$first_render = $widget->shortcode( [ 'features' => 'answers,filter,bad<script>' ] );
		$block_render = $widget->render_block( [ 'features' => [ 'suggestions' ] ] );

		self::assertStringContainsString( 'nuclia-search-bar', $first_render );
		self::assertStringContainsString( 'knowledgebox="kb-123"', $first_render );
		self::assertStringContainsString( 'backend="https://example.test/index.php/nuclia-proxy/europe-1"', $first_render );
		self::assertStringContainsString( 'features="answers,filter"', $first_render );
		self::assertStringContainsString( 'features="suggestions"', $block_render );
		self::assertStringContainsString( 'assets/css/widget-response.css?ver=1.0.0', $first_render );
		self::assertStringContainsString( '--progress-agentic-rag-widget-accent-color:#054bff', $first_render );
		self::assertStringContainsString( '--progress-agentic-rag-widget-card-padding:24px', $first_render );
		self::assertStringNotContainsString( 'secret-token', $first_render );
	}

	public function test_search_widget_does_not_render_when_context_or_settings_are_missing(): void {
		$GLOBALS['progress_agentic_rag_test_is_admin'] = true;
		$widget = new SearchWidget( new SettingsRepository() );

		$widget->enqueue_assets();
		ob_start();
		$widget->render();

		self::assertSame( '', ob_get_clean() );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_enqueued_styles'] );

		$GLOBALS['progress_agentic_rag_test_is_admin'] = false;
		update_option( SettingsRepository::OPTION_TOKEN, '' );

		$widget = new SearchWidget( new SettingsRepository() );
		ob_start();
		$widget->render();

		self::assertSame( '', ob_get_clean() );
	}

	public function test_search_widget_skips_blank_proxy_url(): void {
		update_option( SettingsRepository::OPTION_ZONE, '!!!' );

		ob_start();
		( new SearchWidget( new SettingsRepository() ) )->render();

		self::assertSame( '', ob_get_clean() );
	}

	public function test_proxy_rewrite_rules_query_vars_and_urls(): void {
		ProxyController::add_rewrite_rules();

		self::assertSame( '^nuclia-proxy/([a-z0-9-]+)/?(.*)?$', $GLOBALS['progress_agentic_rag_test_rewrite_rules'][0]['regex'] );
		self::assertSame( 'top', $GLOBALS['progress_agentic_rag_test_rewrite_rules'][0]['after'] );

		self::assertContains( 'progress_agentic_rag_proxy', ProxyController::query_vars( [] ) );
		self::assertSame( 'https://example.test/index.php/nuclia-proxy/europe-1', ProxyController::proxy_url( 'Europe 1!' ) );

		update_option( 'permalink_structure', '/%postname%/' );

		self::assertSame( 'https://example.test/nuclia-proxy/europe-1', ProxyController::proxy_url( 'Europe 1!' ) );
		self::assertSame( '', ProxyController::proxy_url( '!!!' ) );
	}

	public function test_proxy_flushes_rewrite_rules_once_per_version(): void {
		ProxyController::maybe_flush_rewrite_rules();

		self::assertSame( PROGRESS_AGENTIC_RAG_VERSION, get_option( 'progress_agentic_rag_proxy_rewrite_version' ) );
		self::assertSame( [ false ], $GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] );

		ProxyController::maybe_flush_rewrite_rules();

		self::assertSame( [ false ], $GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] );
	}

	public function test_proxy_execute_redacts_ephemeral_token_and_filters_headers(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 207,
			],
			'headers'  => [
				'Content-Type'     => 'application/json',
				'content-encoding' => 'gzip',
				'X-NUCLIA-TRACE-ID' => [ 'trace-1', 'trace-2' ],
				"Bad\nHeader"     => 'bad',
				'Empty'           => '',
			],
			'body'     => '{"ok":true}',
		];

		$result = $this->execute_proxy(
			'Europe-1',
			'v1/search',
			'POST',
			'progress_agentic_rag_proxy=1&eph-token=abc123&foo=bar',
			'application/json',
			'application/json',
			'{"query":"example"}',
			[ 'x-synchronous' => 'true' ]
		);

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];

		self::assertSame( 207, $result['status'] );
		self::assertSame( '{"ok":true}', $result['body'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/search?eph-token=abc123&foo=bar', $request['url'] );
		self::assertArrayNotHasKey( 'X-NUCLIA-SERVICEACCOUNT', $request['args']['headers'] );
		self::assertSame( 'true', $request['args']['headers']['x-synchronous'] );
		self::assertSame( '{"query":"example"}', $request['args']['body'] );
		self::assertSame( '207', $result['headers']['X-Nuclia-Upstream-Status'] );
		self::assertSame( 'application/json', $result['headers']['X-Nuclia-Upstream-Content-Type'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/search?eph-token=REDACTED&foo=bar', $result['headers']['X-Nuclia-Upstream-URL'] );
		self::assertArrayNotHasKey( 'content-encoding', $result['headers'] );
		self::assertSame( 'trace-1, trace-2', $result['headers']['X-NUCLIA-TRACE-ID'] );
	}

	public function test_proxy_execute_validates_zone_method_token_and_path(): void {
		self::assertSame( 400, $this->execute_proxy( 'bad zone', 'v1/search' )['status'] );
		self::assertSame( 405, $this->execute_proxy( 'europe-1', 'v1/search', 'DELETE' )['status'] );
		self::assertSame( 200, $this->execute_proxy( 'europe-1', 'v1/search', 'OPTIONS' )['status'] );
		self::assertSame( 400, $this->execute_proxy( 'europe-1', '../secret' )['status'] );

		update_option( SettingsRepository::OPTION_TOKEN, '' );

		self::assertSame( 500, $this->execute_proxy( 'europe-1', 'v1/search' )['status'] );
	}

	public function test_proxy_path_request_supports_query_vars_and_request_uri(): void {
		$GLOBALS['progress_agentic_rag_test_query_vars'] = [
			'progress_agentic_rag_proxy'      => '1',
			'progress_agentic_rag_proxy_zone' => 'europe-1',
			'progress_agentic_rag_proxy_path' => '/v1/search',
		];

		self::assertSame( [ 'zone' => 'europe-1', 'path' => 'v1/search' ], $this->path_request() );

		$GLOBALS['progress_agentic_rag_test_query_vars'] = [];
		$_SERVER['REQUEST_URI'] = '/index.php/nuclia-proxy/aws-us-east-2-1/v1/ask?rest_route=/ignored';

		self::assertSame( [ 'zone' => 'aws-us-east-2-1', 'path' => 'v1/ask' ], $this->path_request() );

		$_SERVER['REQUEST_URI'] = '/ordinary-page/';

		self::assertSame( [ 'zone' => '', 'path' => '' ], $this->path_request() );
	}

	public function test_proxy_path_handler_ignores_non_proxy_requests(): void {
		$_SERVER['REQUEST_URI'] = '/ordinary-page/';

		ob_start();
		( new ProxyController( new SettingsRepository() ) )->handle_path_request();

		self::assertSame( '', ob_get_clean() );
	}

	public function test_proxy_cleans_query_string_and_passes_allowlisted_headers(): void {
		$_SERVER['QUERY_STRING'] = 'rest_route=/nuclia&foo=bar&progress_agentic_rag_proxy=1';
		$_SERVER['HTTP_X_SYNCHRONOUS'] = 'yes';
		$_SERVER['HTTP_RANGE'] = 'bytes=0-10';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer client';

		self::assertSame( 'foo=bar', $this->client_query_string_cleaned() );
		self::assertSame(
			[
				'x-synchronous' => 'yes',
				'range'         => 'bytes=0-10',
			],
			$this->passthrough_headers()
		);
	}

	public function test_proxy_helpers_cover_canonical_upstream_and_normalized_paths(): void {
		$controller = new ProxyController( new SettingsRepository() );

		self::assertFalse( $controller->disable_canonical_redirect( 'https://example.test/page/', 'https://example.test/nuclia-proxy/europe-1/v1/search' ) );
		self::assertSame( 'https://example.test/page/', $controller->disable_canonical_redirect( 'https://example.test/page/', 'https://example.test/page/' ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Upstream down' );

		$failed = $this->execute_proxy( 'europe-1', 'v1/search' );

		self::assertSame( 502, $failed['status'] );
		self::assertStringContainsString( 'Upstream down', $failed['body'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{}',
		];

		$this->execute_proxy( 'europe-1', 'api/v1/search' );

		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/search', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{}',
		];

		$this->execute_proxy( 'europe-1', 'search', 'GET', 'foo=bar' );

		self::assertSame( 'https://europe-1.rag.progress.cloud/api/search?foo=bar', $GLOBALS['progress_agentic_rag_test_http_requests'][2]['url'] );

		unset( $_SERVER['QUERY_STRING'] );
		$_SERVER['REQUEST_URI'] = '/nuclia-proxy/europe-1/v1/search?foo=bar&rest_route=/ignored';

		self::assertSame( 'foo=bar', $this->client_query_string_cleaned() );
	}

	/**
	 * @param array<string,string> $headers
	 * @return array{status:int, headers:array<string,string>, body:string}
	 */
	private function execute_proxy(
		string $zone,
		string $path,
		string $method = 'GET',
		string $query_string = '',
		string $content_type = '',
		string $accept = '',
		string $body = '',
		array $headers = []
	): array {
		$controller = new ProxyController( new SettingsRepository() );
		$method_ref = new ReflectionMethod( $controller, 'execute' );
		$method_ref->setAccessible( true );

		return $method_ref->invoke( $controller, $zone, $path, $method, $query_string, $content_type, $accept, $body, $headers );
	}

	/**
	 * @return array{zone:string,path:string}
	 */
	private function path_request(): array {
		$controller = new ProxyController( new SettingsRepository() );
		$method_ref = new ReflectionMethod( $controller, 'path_request' );
		$method_ref->setAccessible( true );

		return $method_ref->invoke( $controller );
	}

	private function client_query_string_cleaned(): string {
		$controller = new ProxyController( new SettingsRepository() );
		$query_ref = new ReflectionMethod( $controller, 'client_query_string' );
		$query_ref->setAccessible( true );
		$clean_ref = new ReflectionMethod( $controller, 'clean_query_string' );
		$clean_ref->setAccessible( true );

		return $clean_ref->invoke( $controller, $query_ref->invoke( $controller ) );
	}

	/**
	 * @return array<string,string>
	 */
	private function passthrough_headers(): array {
		$controller = new ProxyController( new SettingsRepository() );
		$method_ref = new ReflectionMethod( $controller, 'passthrough_headers' );
		$method_ref->setAccessible( true );

		return $method_ref->invoke( $controller );
	}
}
