<?php
/**
 * Test-only fixtures for Progress Agentic RAG Playwright coverage.
 *
 * @package ProgressAgenticRag\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PROGRESS_AGENTIC_RAG_E2E_KEY_OPTION = 'progress_agentic_rag_e2e_key';
const PROGRESS_AGENTIC_RAG_E2E_LOG_OPTION = 'progress_agentic_rag_e2e_requests';
const PROGRESS_AGENTIC_RAG_E2E_STATE_OPTION = 'progress_agentic_rag_e2e_state';
const PROGRESS_AGENTIC_RAG_E2E_BACKUP_OPTION = 'progress_agentic_rag_e2e_option_backup';

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'progress-agentic-rag-e2e/v1',
			'/reset',
			[
				'methods'             => 'POST',
				'callback'            => 'progress_agentic_rag_e2e_reset',
				'permission_callback' => 'progress_agentic_rag_e2e_can_access',
			]
		);

		register_rest_route(
			'progress-agentic-rag-e2e/v1',
			'/configure',
			[
				'methods'             => 'POST',
				'callback'            => 'progress_agentic_rag_e2e_configure',
				'permission_callback' => 'progress_agentic_rag_e2e_can_access',
			]
		);

		register_rest_route(
			'progress-agentic-rag-e2e/v1',
			'/scenario',
			[
				'methods'             => 'POST',
				'callback'            => 'progress_agentic_rag_e2e_scenario',
				'permission_callback' => 'progress_agentic_rag_e2e_can_access',
			]
		);

		register_rest_route(
			'progress-agentic-rag-e2e/v1',
			'/settings',
			[
				'methods'             => 'GET',
				'callback'            => 'progress_agentic_rag_e2e_settings',
				'permission_callback' => 'progress_agentic_rag_e2e_can_access',
			]
		);

		register_rest_route(
			'progress-agentic-rag-e2e/v1',
			'/logs',
			[
				'methods'             => 'GET',
				'callback'            => 'progress_agentic_rag_e2e_logs',
				'permission_callback' => 'progress_agentic_rag_e2e_can_access',
			]
		);
	}
);

add_filter( 'pre_http_request', 'progress_agentic_rag_e2e_intercept_request', 10, 3 );
add_action( 'wp_footer', 'progress_agentic_rag_e2e_render_shortcode_widget' );

function progress_agentic_rag_e2e_settings(): WP_REST_Response {
	$token = (string) get_option( 'nuclia_token', '' );

	return rest_ensure_response(
		[
			'zone'             => (string) get_option( 'nuclia_zone', '' ),
			'kbid'             => (string) get_option( 'nuclia_kbid', '' ),
			'account_id'       => (string) get_option( 'nuclia_account_id', '' ),
			'api_is_reachable' => (string) get_option( 'nuclia_api_is_reachable', '' ),
			'token_saved'      => '' !== $token,
			'token_hash'       => '' !== $token ? hash( 'sha256', $token ) : '',
			'backup_exists'    => is_array( get_option( PROGRESS_AGENTIC_RAG_E2E_BACKUP_OPTION, null ) ),
		]
	);
}

function progress_agentic_rag_e2e_enabled(): bool {
	return '' !== (string) get_option( PROGRESS_AGENTIC_RAG_E2E_KEY_OPTION, '' );
}

function progress_agentic_rag_e2e_render_shortcode_widget(): void {
	if ( ! progress_agentic_rag_e2e_enabled() || ! isset( $_GET['progress_agentic_rag_e2e_widget'] ) ) {
		return;
	}

	echo do_shortcode( '[progress_agentic_rag_search features="answers,rephrase,filter,suggestions"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode renderer escapes its template output.
}

function progress_agentic_rag_e2e_can_access( WP_REST_Request $request ): bool|WP_Error {
	$configured_key = (string) get_option( PROGRESS_AGENTIC_RAG_E2E_KEY_OPTION, '' );
	$request_key    = (string) $request->get_header( 'x-progress-agentic-rag-e2e-key' );

	if ( '' !== $configured_key && hash_equals( $configured_key, $request_key ) ) {
		return true;
	}

	return new WP_Error(
		'progress_agentic_rag_e2e_forbidden',
		'E2E fixture access is not configured.',
		[ 'status' => 403 ]
	);
}

function progress_agentic_rag_e2e_reset(): WP_REST_Response {
	progress_agentic_rag_e2e_restore_options();
	update_option( PROGRESS_AGENTIC_RAG_E2E_LOG_OPTION, [] );
	update_option(
		PROGRESS_AGENTIC_RAG_E2E_STATE_OPTION,
		[
			'scenario' => 'success',
			'delay_ms' => 0,
		]
	);

	return rest_ensure_response( [ 'ok' => true ] );
}

function progress_agentic_rag_e2e_configure( WP_REST_Request $request ): WP_REST_Response {
	$params    = $request->get_json_params();
	$params    = is_array( $params ) ? $params : [];
	$connected = array_key_exists( 'connected', $params ) ? (bool) $params['connected'] : true;
	$zone      = isset( $params['zone'] ) ? sanitize_text_field( (string) $params['zone'] ) : 'europe-1';
	$kbid      = isset( $params['kbid'] ) ? sanitize_text_field( (string) $params['kbid'] ) : 'ci-kb';
	$token     = isset( $params['token'] ) ? sanitize_text_field( (string) $params['token'] ) : 'progress-agentic-rag-secret';

	progress_agentic_rag_e2e_backup_options();
	update_option( 'nuclia_zone', $connected ? $zone : '' );
	update_option( 'nuclia_kbid', $connected ? $kbid : '' );
	update_option( 'nuclia_token', $connected ? $token : '' );
	update_option( 'nuclia_account_id', isset( $params['account_id'] ) ? sanitize_text_field( (string) $params['account_id'] ) : 'ci-account' );
	update_option( 'nuclia_api_is_reachable', $connected && ! empty( $params['reachable'] ?? true ) ? 'yes' : 'no' );

	return rest_ensure_response(
		[
			'ok'        => true,
			'connected' => $connected,
		]
	);
}

function progress_agentic_rag_e2e_option_names(): array {
	return [
		'nuclia_zone',
		'nuclia_kbid',
		'nuclia_token',
		'nuclia_account_id',
		'nuclia_api_is_reachable',
		'progress_agentic_rag_widget_appearance',
	];
}

function progress_agentic_rag_e2e_backup_options(): void {
	$existing = get_option( PROGRESS_AGENTIC_RAG_E2E_BACKUP_OPTION, null );
	if ( is_array( $existing ) ) {
		return;
	}

	$missing = '__progress_agentic_rag_e2e_missing__';
	$backup  = [];
	foreach ( progress_agentic_rag_e2e_option_names() as $option_name ) {
		$value = get_option( $option_name, $missing );
		$backup[ $option_name ] = [
			'exists' => $missing !== $value,
			'value'  => $missing !== $value ? $value : null,
		];
	}

	update_option( PROGRESS_AGENTIC_RAG_E2E_BACKUP_OPTION, $backup, false );
}

function progress_agentic_rag_e2e_restore_options(): void {
	$backup = get_option( PROGRESS_AGENTIC_RAG_E2E_BACKUP_OPTION, null );
	if ( ! is_array( $backup ) ) {
		return;
	}

	foreach ( progress_agentic_rag_e2e_option_names() as $option_name ) {
		$item = is_array( $backup[ $option_name ] ?? null ) ? $backup[ $option_name ] : [];
		if ( empty( $item['exists'] ) ) {
			delete_option( $option_name );
			continue;
		}

		update_option( $option_name, $item['value'] ?? '' );
	}

	delete_option( PROGRESS_AGENTIC_RAG_E2E_BACKUP_OPTION );
}

function progress_agentic_rag_e2e_scenario( WP_REST_Request $request ): WP_REST_Response {
	$params   = $request->get_json_params();
	$params   = is_array( $params ) ? $params : [];
	$scenario = isset( $params['scenario'] ) ? sanitize_key( (string) $params['scenario'] ) : 'success';
	$allowed  = [ 'success', 'empty', 'error', 'slow' ];

	if ( ! in_array( $scenario, $allowed, true ) ) {
		$scenario = 'success';
	}

	update_option(
		PROGRESS_AGENTIC_RAG_E2E_STATE_OPTION,
		[
			'scenario' => $scenario,
			'delay_ms' => isset( $params['delay_ms'] ) ? min( absint( $params['delay_ms'] ), 500 ) : ( 'slow' === $scenario ? 150 : 0 ),
		]
	);

	return rest_ensure_response(
		[
			'ok'       => true,
			'scenario' => $scenario,
		]
	);
}

function progress_agentic_rag_e2e_logs(): WP_REST_Response {
	$requests = get_option( PROGRESS_AGENTIC_RAG_E2E_LOG_OPTION, [] );

	return rest_ensure_response(
		[
			'requests' => is_array( $requests ) ? array_values( $requests ) : [],
		]
	);
}

function progress_agentic_rag_e2e_intercept_request( mixed $preempt, array $args, string $url ): mixed {
	if ( ! progress_agentic_rag_e2e_enabled() ) {
		return $preempt;
	}

	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || ! str_ends_with( $host, '.rag.progress.cloud' ) ) {
		return $preempt;
	}

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( str_contains( $path, '/resource' ) || str_contains( $path, '/resources' ) ) {
		return $preempt;
	}

	progress_agentic_rag_e2e_log_request( $url, $args );

	$state    = get_option( PROGRESS_AGENTIC_RAG_E2E_STATE_OPTION, [] );
	$scenario = is_array( $state ) && isset( $state['scenario'] ) ? sanitize_key( (string) $state['scenario'] ) : 'success';
	$delay_ms = is_array( $state ) && isset( $state['delay_ms'] ) ? absint( $state['delay_ms'] ) : 0;

	if ( $delay_ms > 0 ) {
		usleep( min( $delay_ms, 500 ) * 1000 );
	}

	return progress_agentic_rag_e2e_response( $url, $scenario );
}

function progress_agentic_rag_e2e_log_request( string $url, array $args ): void {
	$headers            = progress_agentic_rag_e2e_headers( $args['headers'] ?? [] );
	$method             = isset( $args['method'] ) ? strtoupper( sanitize_text_field( (string) $args['method'] ) ) : 'GET';
	$body               = isset( $args['body'] ) && is_scalar( $args['body'] ) ? (string) $args['body'] : '';
	$service_header_key = progress_agentic_rag_e2e_header_key( $headers, 'X-NUCLIA-SERVICEACCOUNT' );
	$requests           = get_option( PROGRESS_AGENTIC_RAG_E2E_LOG_OPTION, [] );

	if ( ! is_array( $requests ) ) {
		$requests = [];
	}

	$requests[] = [
		'method'           => $method,
		'url'              => progress_agentic_rag_e2e_redact_url( $url ),
		'path'             => (string) wp_parse_url( $url, PHP_URL_PATH ),
		'query'            => (string) wp_parse_url( progress_agentic_rag_e2e_redact_url( $url ), PHP_URL_QUERY ),
		'headers'          => $headers,
		'hasServiceHeader' => null !== $service_header_key,
		'bodyLength'       => strlen( $body ),
	];

	update_option( PROGRESS_AGENTIC_RAG_E2E_LOG_OPTION, array_slice( $requests, -50 ), false );
}

function progress_agentic_rag_e2e_headers( mixed $headers ): array {
	$clean = [];

	if ( ! is_iterable( $headers ) ) {
		return $clean;
	}

	foreach ( $headers as $name => $value ) {
		$name  = sanitize_text_field( (string) $name );
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

		if ( '' === $name || '' === $value ) {
			continue;
		}

		if ( in_array( strtolower( $name ), [ 'authorization', 'cookie', 'x-nuclia-serviceaccount' ], true ) ) {
			$value = str_starts_with( $value, 'Bearer ' ) ? 'Bearer [redacted]' : '[redacted]';
		}

		$clean[ $name ] = $value;
	}

	return $clean;
}

function progress_agentic_rag_e2e_header_key( array $headers, string $needle ): ?string {
	foreach ( $headers as $name => $value ) {
		if ( strtolower( (string) $name ) === strtolower( $needle ) ) {
			return (string) $name;
		}
	}

	return null;
}

function progress_agentic_rag_e2e_redact_url( string $url ): string {
	return (string) preg_replace( '/(?<=[?&])(eph-token|token|apikey|api_key|authorization)=[^&]*/i', '$1=REDACTED', $url );
}

function progress_agentic_rag_e2e_response( string $url, string $scenario ): array {
	if ( 'error' === $scenario ) {
		return progress_agentic_rag_e2e_http_response(
			503,
			[
				'detail' => 'Fixture upstream failure',
			]
		);
	}

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( 'empty' === $scenario ) {
		return progress_agentic_rag_e2e_http_response( 200, progress_agentic_rag_e2e_empty_find_body() );
	}

	if ( str_contains( $path, '/ask' ) ) {
		return progress_agentic_rag_e2e_http_response(
			200,
			[
				'answer'  => 'Fixture answer from the mocked Progress Agentic RAG upstream.',
				'sources' => [],
			]
		);
	}

	if ( str_contains( $path, 'suggest' ) || str_contains( $path, 'autocomplete' ) ) {
		return progress_agentic_rag_e2e_http_response(
			200,
			[
				'suggestions' => [
					[
						'value' => 'fixture query',
					],
				],
			]
		);
	}

	if ( str_contains( $path, 'label' ) || str_contains( $path, 'filter' ) ) {
		return progress_agentic_rag_e2e_http_response(
			200,
			[
				'labelsets' => [],
				'labels'    => [],
			]
		);
	}

	if ( str_contains( $path, '/resources' ) ) {
		return progress_agentic_rag_e2e_http_response(
			200,
			[
				'resources' => [
					[
						'id'    => 'rid-fixture',
						'slug'  => 'fixture-result',
						'title' => 'Fixture search result',
					],
				],
			]
		);
	}

	return progress_agentic_rag_e2e_http_response( 200, progress_agentic_rag_e2e_success_find_body() );
}

function progress_agentic_rag_e2e_http_response( int $status, array $body ): array {
	return [
		'headers'  => [
			'Content-Type'        => 'application/json; charset=utf-8',
			'X-E2E-Upstream'      => 'ok',
			'Nuclia-Learning-Id'  => 'learning-fixture',
			'X-NUCLIA-TRACE-ID'   => 'trace-fixture',
			'Content-Encoding'    => 'gzip',
			'Connection'          => 'close',
			'X-Unsafe-E2E-Header' => "line\r\nbreak",
		],
		'body'     => (string) wp_json_encode( $body ),
		'response' => [
			'code'    => $status,
			'message' => 200 === $status ? 'OK' : 'Fixture Error',
		],
		'cookies'  => [],
		'filename' => null,
	];
}

function progress_agentic_rag_e2e_empty_find_body(): array {
	return [
		'type'      => 'findResults',
		'resources' => new stdClass(),
		'paragraphs' => new stdClass(),
		'sentences' => new stdClass(),
		'relations' => [
			'entities'  => new stdClass(),
			'relations' => [],
		],
		'fulltext'  => [
			'results' => [],
		],
		'total'     => 0,
	];
}

function progress_agentic_rag_e2e_success_find_body(): array {
	return [
		'type'       => 'findResults',
		'resources'  => [
			'rid-fixture' => [
				'id'       => 'rid-fixture',
				'uuid'     => 'rid-fixture',
				'slug'     => 'fixture-result',
				'title'    => 'Fixture search result',
				'summary'  => 'Deterministic mocked search result for Playwright.',
				'icon'     => 'text/plain',
				'metadata' => [
					'metadata' => [],
				],
				'fields'   => [
					'text' => [
						'text' => [
							'paragraphs' => [],
						],
					],
				],
			],
		],
		'paragraphs' => [
			'rid-fixture/text/text/0-120' => [
				'rid'        => 'rid-fixture',
				'field'      => 'text',
				'field_type' => 'text',
				'field_id'   => 'text',
				'score'      => 0.97,
				'order'      => 0,
				'text'       => 'Fixture search result from the mocked Progress Agentic RAG upstream.',
				'position'   => [
					'index' => 0,
					'start' => 0,
					'end'   => 80,
				],
				'labels'     => [],
			],
		],
		'sentences'  => new stdClass(),
		'relations'  => [
			'entities'  => new stdClass(),
			'relations' => [],
		],
		'fulltext'   => [
			'results' => [],
		],
		'total'      => 1,
	];
}
