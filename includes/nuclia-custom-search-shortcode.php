<?php
/**
 * Nuclia custom search shortcode.
 *
 * Shortcode: [agentic_rag_custom_search]
 */

namespace Progress\WPSWN;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

\add_shortcode( 'agentic_rag_custom_search', __NAMESPACE__ . '\\nuclia_custom_search_shortcode' );
\add_action( 'rest_api_init', __NAMESPACE__ . '\\nuclia_register_custom_search_routes' );

function nuclia_custom_search_shortcode( $atts = [] ): string {
	$zone  = (string) get_option( 'nuclia_zone', '' );
	$kbid  = (string) get_option( 'nuclia_kbid', '' );
	$token = (string) get_option( 'nuclia_token', '' );

	if ( empty( $zone ) || empty( $kbid ) || empty( $token ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return sprintf(
				'<div style="color:red; border: 2px dotted red; padding: .5em;">%s</div>',
				esc_html__( 'Nuclia custom search is not configured. Please set your zone, KB ID, and token in the plugin settings.', 'progress-agentic-rag' )
			);
		}
		return '';
	}

	$script_handle = 'nuclia-custom-search';
	$style_handle  = 'nuclia-custom-search';

	$atts = shortcode_atts(
		[
			'show_config'  => 'true',
			'search_config' => '',
		],
		$atts,
		'agentic_rag_custom_search'
	);
	$show_config_raw = (string) $atts['show_config'];
	$show_config     = filter_var( $show_config_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	if ( $show_config === null ) {
		$show_config = true;
	}
	$search_config = sanitize_text_field( (string) $atts['search_config'] );
	$data_attrs    = sprintf(
		' data-show-config="%1$s"%2$s',
		$show_config ? 'true' : 'false',
		$search_config !== '' ? ' data-search-config="' . esc_attr( $search_config ) . '"' : ''
	);

	wp_enqueue_script(
		$script_handle,
		PROGRESS_NUCLIA_PLUGIN_URL . 'includes/public/js/nuclia-custom-search.js',
		[],
		PROGRESS_NUCLIA_VERSION,
		true
	);
	wp_enqueue_style(
		$style_handle,
		PROGRESS_NUCLIA_PLUGIN_URL . 'includes/public/css/nuclia-custom-search.css',
		[],
		PROGRESS_NUCLIA_VERSION
	);

	wp_localize_script(
		$script_handle,
		'progressNucliaSearch',
		[
			'restUrl'   => esc_url_raw( rest_url( 'progress-agentic-rag/v1' ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'strings'   => [
				'loadingConfigs' => esc_html__( 'Loading configurations…', 'progress-agentic-rag' ),
				'noConfigs'      => esc_html__( 'No configurations available', 'progress-agentic-rag' ),
				'searching'      => esc_html__( 'Searching…', 'progress-agentic-rag' ),
				'noAnswer'       => esc_html__( 'No answer returned.', 'progress-agentic-rag' ),
				'noCitations'    => esc_html__( 'No citations returned.', 'progress-agentic-rag' ),
			],
		]
	);

	$id = esc_attr( wp_unique_id( 'pl-nuclia-search-' ) );

	$config_markup = '';
	if ( $show_config ) {
		$config_markup = sprintf(
			'<div class="pl-nuclia-search-config">
					<label class="pl-nuclia-label">%1$s</label>
					<select class="pl-nuclia-select" aria-label="%1$s"></select>
					<div class="pl-nuclia-config-status" role="status" aria-live="polite"></div>
				</div>',
			esc_html__( 'Search configuration', 'progress-agentic-rag' )
		);
	}

	return sprintf(
		'<div id="%1$s" class="pl-nuclia-search-widget"%2$s>
			<div class="pl-nuclia-search-header">
				<h2 class="pl-nuclia-search-title">%3$s</h2>
				<p class="pl-nuclia-search-subtitle">%4$s</p>
			</div>
			<div class="pl-nuclia-search-controls">
				<div class="pl-nuclia-search-input">
					<input type="search" class="pl-nuclia-input" placeholder="%5$s" aria-label="%6$s" />
					<button type="button" class="pl-nuclia-button">%7$s</button>
					<button type="button" class="pl-nuclia-button pl-nuclia-button-secondary pl-nuclia-clear" hidden>%8$s</button>
				</div>
				%9$s
			</div>
			<div class="pl-nuclia-search-status" role="status" aria-live="polite"></div>
			<div class="pl-nuclia-search-results" hidden>
				<section class="pl-nuclia-answer">
					<h3 class="pl-nuclia-section-title">%10$s</h3>
					<div class="pl-nuclia-answer-text"></div>
				</section>
				<section class="pl-nuclia-citations">
					<h3 class="pl-nuclia-section-title">%11$s</h3>
					<div class="pl-nuclia-citations-list"></div>
				</section>
			</div>
		</div>',
		$id,
		$data_attrs,
		esc_html__( 'Search', 'progress-agentic-rag' ),
		esc_html__( 'Ask a question and review citations.', 'progress-agentic-rag' ),
		esc_attr__( 'Type your question', 'progress-agentic-rag' ),
		esc_attr__( 'Search question', 'progress-agentic-rag' ),
		esc_html__( 'Search', 'progress-agentic-rag' ),
		esc_html__( 'Clear', 'progress-agentic-rag' ),
		$config_markup,
		esc_html__( 'Answer', 'progress-agentic-rag' ),
		esc_html__( 'Citations', 'progress-agentic-rag' )
	);
}

function nuclia_register_custom_search_routes(): void {
	register_rest_route(
		'progress-agentic-rag/v1',
		'/search-configurations',
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\nuclia_handle_search_configurations',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'progress-agentic-rag/v1',
		'/ask',
		[
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\nuclia_handle_search_ask',
			'permission_callback' => '__return_true',
			'args'                => [
				'question' => [
					'required' => true,
					'type'     => 'string',
				],
				'search_configuration' => [
					'required' => false,
					'type'     => 'string',
				],
			],
		]
	);
}

function nuclia_handle_search_configurations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$settings = nuclia_get_settings();
	if ( is_wp_error( $settings ) ) {
		return $settings;
	}

	$configs = nuclia_fetch_search_configurations( $settings );
	return new WP_REST_Response( [ 'configs' => $configs ], 200 );
}

function nuclia_handle_search_ask( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$settings = nuclia_get_settings();
	if ( is_wp_error( $settings ) ) {
		return $settings;
	}

	$question = sanitize_text_field( (string) $request->get_param( 'question' ) );
	if ( $question === '' ) {
		return new WP_Error( 'nuclia_invalid_question', 'Question is required.', [ 'status' => 400 ] );
	}

	$search_configuration = sanitize_text_field( (string) $request->get_param( 'search_configuration' ) );

	$payload = [
		'query' => $question,
	];
	if ( $search_configuration !== '' ) {
		$payload['search_configuration'] = $search_configuration;
	}

	$response = nuclia_post_to_api( $settings, 'ask', $payload );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return new WP_REST_Response( $response, 200 );
}

function nuclia_get_settings(): array|WP_Error {
	$zone  = sanitize_text_field( (string) get_option( 'nuclia_zone', '' ) );
	$kbid  = sanitize_text_field( (string) get_option( 'nuclia_kbid', '' ) );
	$token = sanitize_text_field( (string) get_option( 'nuclia_token', '' ) );

	if ( $zone === '' || $kbid === '' || $token === '' ) {
		return new WP_Error( 'nuclia_missing_settings', 'Nuclia settings are not configured.', [ 'status' => 400 ] );
	}

	return [
		'zone'  => $zone,
		'kbid'  => $kbid,
		'token' => $token,
	];
}

function nuclia_get_api_base_url( array $settings ): string {
	return sprintf( 'https://%1$s.rag.progress.cloud/api/v1/kb/%2$s/', rawurlencode( $settings['zone'] ), rawurlencode( $settings['kbid'] ) );
}

function nuclia_post_to_api( array $settings, string $path, array $payload ): array|WP_Error {
	$uri  = nuclia_get_api_base_url( $settings ) . ltrim( $path, '/' );
	$args = [
		'method'  => 'POST',
		'timeout' => 30,
		'headers' => [
			'Content-type'            => 'application/json',
			'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $settings['token'],
		],
		'body'    => wp_json_encode( $payload ),
	];
	$response = wp_remote_request( $uri, $args );
	if ( is_wp_error( $response ) ) {
		nuclia_error_log( 'Ask request failed: ' . $response->get_error_message() );
		return new WP_Error( 'nuclia_request_failed', 'Request failed.', [ 'status' => 502 ] );
	}

	$status = wp_remote_retrieve_response_code( $response );
	$body   = wp_remote_retrieve_body( $response );
	$data   = json_decode( $body, true );
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		$ndjson_items = nuclia_parse_ndjson_items( $body );
		if ( ! empty( $ndjson_items ) ) {
			return [
				'stream_items' => $ndjson_items,
			];
		}
		nuclia_error_log( 'Ask request JSON decode failed: ' . json_last_error_msg() . ' (bytes=' . strlen( (string) $body ) . ')' );
	}
	if ( $status < 200 || $status >= 300 ) {
		$message = is_array( $data ) && isset( $data['detail'] ) ? (string) $data['detail'] : 'Request failed.';
		nuclia_error_log( 'Ask request error: ' . $status . ' ' . $message );
		return new WP_Error( 'nuclia_request_failed', $message, [ 'status' => $status ] );
	}

	return is_array( $data ) ? $data : [];
}

function nuclia_parse_ndjson_items( string $body ): array {
	$items = [];
	$body = trim( $body );
	if ( $body === '' ) {
		return $items;
	}

	$lines = preg_split( "/\\r?\\n/", $body );
	if ( is_array( $lines ) && count( $lines ) <= 1 && str_contains( $body, '\\n' ) ) {
		$lines = preg_split( "/\\r?\\n/", str_replace( '\\n', "\n", $body ) );
	}
	if ( ! is_array( $lines ) ) {
		return $items;
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		if ( str_starts_with( $line, 'data:' ) ) {
			$line = trim( substr( $line, 5 ) );
		}
		if ( $line === '' || $line === '[DONE]' ) {
			continue;
		}
		$decoded = json_decode( $line, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			$items[] = $decoded;
		}
	}

	return $items;
}

function nuclia_fetch_search_configurations( array $settings ): array {
	$base       = nuclia_get_api_base_url( $settings );
	$uri        = $base . 'search_configurations';
	$args = [
		'method'  => 'GET',
		'timeout' => 20,
		'headers' => [
			'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $settings['token'],
		],
	];

	$response = wp_remote_get( $uri, $args );
	if ( is_wp_error( $response ) ) {
		return [];
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( $status !== 200 ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$configs = nuclia_normalize_search_configurations( $data );
	if ( ! empty( $configs ) ) {
		return $configs;
	}

	return [];
}

function nuclia_normalize_search_configurations( mixed $data ): array {
	if ( ! is_array( $data ) ) {
		return [];
	}

	$payload = $data['search_configurations'] ?? $data['configs'] ?? $data['configurations'] ?? $data;
	if ( ! is_array( $payload ) ) {
		return [];
	}

	$configs = [];
	$is_assoc = array_keys( $payload ) !== range( 0, count( $payload ) - 1 );
	if ( $is_assoc ) {
		foreach ( $payload as $key => $item ) {
			$name = sanitize_text_field( (string) $key );
			if ( $name !== '' ) {
				$configs[] = [ 'name' => $name ];
			}
		}
	} else {
		foreach ( $payload as $item ) {
			if ( is_string( $item ) ) {
				$name = sanitize_text_field( $item );
			} elseif ( is_array( $item ) ) {
				$name = $item['name'] ?? $item['id'] ?? $item['title'] ?? '';
				$name = sanitize_text_field( (string) $name );
			} else {
				$name = '';
			}

			if ( $name !== '' ) {
				$configs[] = [ 'name' => $name ];
			}
		}
	}

	$unique = [];
	foreach ( $configs as $config ) {
		$unique[ $config['name'] ] = $config;
	}

	return array_values( $unique );
}
