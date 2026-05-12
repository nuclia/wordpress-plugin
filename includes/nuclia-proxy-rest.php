<?php
/**
 * Nuclia REST proxy handler.
 *
 * @since 1.0.0
 */

// Nothing to see here if not loaded in WP context.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'rest_api_init', 'nuclia_register_proxy_rest_routes' );
add_action( 'init', 'nuclia_proxy_add_rewrite_rules' );
add_filter( 'query_vars', 'nuclia_proxy_query_vars' );
add_action( 'template_redirect', 'nuclia_proxy_path_handler' );
add_filter( 'rest_pre_serve_request', 'nuclia_proxy_force_rest_preflight_cors_headers', 1000, 4 );

// Disable canonical redirects for proxy URLs (fixes 301 trailing slash issue)
add_filter( 'redirect_canonical', function( $redirect_url, $request_url ) {
	if ( strpos( $request_url, '/nuclia-proxy/' ) !== false || strpos( $request_url, '/wp-json/progress-agentic-rag/v1/nuclia/' ) !== false ) {
		return false;
	}
	return $redirect_url;
}, 10, 2 );

// Disable zlib compression for proxy URLs (prevents double compression)
add_filter( 'zlib_output_compression', function( $compression ) {
	if ( isset( $_SERVER['REQUEST_URI'] ) && ( strpos( $_SERVER['REQUEST_URI'], '/nuclia-proxy/' ) !== false || strpos( $_SERVER['REQUEST_URI'], '/wp-json/progress-agentic-rag/v1/nuclia/' ) !== false ) ) {
		return false;
	}
	return $compression;
} );

// Remove WordPress output compression for proxy responses
// Run at priority -1 to ensure we run before ANY headers are sent
add_action( 'template_redirect', function() {
	if ( isset( $_SERVER['REQUEST_URI'] ) && ( strpos( $_SERVER['REQUEST_URI'], '/nuclia-proxy/' ) !== false || strpos( $_SERVER['REQUEST_URI'], '/wp-json/progress-agentic-rag/v1/nuclia/' ) !== false ) ) {
		// CRITICAL: Disable ALL error output for streaming
		// Error messages would corrupt binary response data
		error_reporting( 0 ); // Disable all error reporting
		@ini_set( 'display_errors', '0' );
		@ini_set( 'log_errors', '0' );

		// CRITICAL: Clean ALL output buffers before WordPress sends headers
		// This must happen at the earliest possible point
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}

		// Disable compression and output buffering
		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );
		@ini_set( 'implicit_flush', 'On' );
	}
}, -1 );

/**
 * Return a comma-separated list of headers allowed for CORS preflight.
 *
 * @return string
 */
function nuclia_proxy_cors_allow_headers_value(): string {
		return implode(
		', ',
		[
			'Content-Type',
			'Authorization',
			'X-Requested-With',
			'x-synchronous',
			'X-Synchronous',
			'Nuclia-Learning-Id',
			'x-ndb-client',
			'X-NDB-Client',
			'Range',
			'If-Range',
		]
	);
}

/**
 * Emit CORS headers for proxy routes.
 */
function nuclia_proxy_send_cors_headers(): void {
	if ( headers_sent() ) {
		return;
	}

	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( (string) $_SERVER['HTTP_ORIGIN'] ) : '';
	if ( $origin !== '' ) {
		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Vary: Origin', false );
	} else {
		header( 'Access-Control-Allow-Origin: *' );
	}

	header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
	header( 'Access-Control-Allow-Headers: ' . nuclia_proxy_cors_allow_headers_value() );
	header( 'Access-Control-Expose-Headers: X-Nuclia-Upstream-Status, X-Nuclia-Upstream-Content-Type, X-Nuclia-Upstream-URL, Nuclia-Learning-Id, X-NUCLIA-TRACE-ID' );
}

/**
 * Emit strict preflight CORS headers for the Nuclia REST proxy route.
 */
function nuclia_proxy_send_rest_preflight_cors_headers(): void {
	if ( headers_sent() ) {
		return;
	}

	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( (string) $_SERVER['HTTP_ORIGIN'] ) : '';
	if ( $origin !== '' ) {
		header( 'Access-Control-Allow-Origin: ' . $origin, true );
	} else {
		header( 'Access-Control-Allow-Origin: *', true );
	}

	header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS', true );
	header( 'Access-Control-Allow-Headers: ' . nuclia_proxy_cors_allow_headers_value(), true );
	header( 'Access-Control-Expose-Headers: X-Nuclia-Upstream-Status, X-Nuclia-Upstream-Content-Type, X-Nuclia-Upstream-URL, Nuclia-Learning-Id, X-NUCLIA-TRACE-ID', true );
	header( 'Vary: Origin', true );
	header( 'Access-Control-Max-Age: 86400', true );
}

/**
 * Override default WordPress REST CORS headers for Nuclia proxy preflight.
 *
 * @param bool             $served  Whether the request has already been served.
 * @param WP_HTTP_Response $result  Result to send to the client.
 * @param WP_REST_Request  $request Request used to generate the response.
 * @param WP_REST_Server   $server  Server instance.
 * @return bool
 */
function nuclia_proxy_force_rest_preflight_cors_headers( $served, $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
	$route = (string) $request->get_route();
	$method = strtoupper( (string) $request->get_method() );

	if ( $method === 'OPTIONS' && str_starts_with( $route, '/progress-agentic-rag/v1/nuclia' ) ) {
		nuclia_proxy_send_rest_preflight_cors_headers();
	}

	return $served;
}

/**
 * Return the path-only proxy URL for the Nuclia widget (no query string).
 * The widget appends path and ?eph-token=... to this URL; it must not contain "?".
 *
 * @param string $zone Nuclia zone slug (e.g. aws-us-east-2-1).
 * @return string
 */
function nuclia_proxy_url( string $zone ): string {
	$zone = sanitize_title( $zone );
	if ( $zone === '' ) {
		return '';
	}
	return home_url( 'nuclia-proxy/' . $zone );
}

/**
 * Register rewrite rule for path-only proxy: /nuclia-proxy/{zone}/{path}
 */
function nuclia_proxy_add_rewrite_rules(): void {
	add_rewrite_rule(
		'^nuclia-proxy/([a-z0-9-]+)/?(.*)?$',
		'index.php?nuclia_proxy=1&nuclia_proxy_zone=$matches[1]&nuclia_proxy_path=$matches[2]',
		'top'
	);
}

/**
 * @param array<string> $vars
 * @return array<string>
 */
function nuclia_proxy_query_vars( array $vars ): array {
	$vars[] = 'nuclia_proxy';
	$vars[] = 'nuclia_proxy_zone';
	$vars[] = 'nuclia_proxy_path';
	return $vars;
}

/**
 * Handle requests to /nuclia-proxy/{zone}/{path} and run the proxy.
 */
function nuclia_proxy_path_handler(): void {
	$zone = '';
	$path = '';

	if ( get_query_var( 'nuclia_proxy', '' ) === '1' ) {
		$zone = get_query_var( 'nuclia_proxy_zone', '' );
		$path = get_query_var( 'nuclia_proxy_path', '' );
	} else {
		// Plain permalinks or no rewrite: parse REQUEST_URI (e.g. /nuclia-proxy/zone/v1/kb/.../field).
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path_part = parse_url( $request_uri, PHP_URL_PATH );
		if ( is_string( $path_part ) && str_starts_with( $path_part, '/nuclia-proxy/' ) ) {
			$suffix = substr( $path_part, strlen( '/nuclia-proxy/' ) );
			$parts = explode( '/', $suffix, 2 );
			$zone = $parts[0] ?? '';
			$path = isset( $parts[1] ) ? $parts[1] : '';
		}
	}

	if ( $zone === '' ) {
		return;
	}

	$zone = sanitize_title( $zone );
	$path = is_string( $path ) ? ltrim( $path, '/' ) : '';
	nuclia_proxy_send_cors_headers();

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	$query_params = isset( $_GET ) && is_array( $_GET ) ? $_GET : [];
	$raw_query_string = nuclia_proxy_get_client_query_string();
	$body = ( $method !== 'GET' && $method !== 'HEAD' ) ? file_get_contents( 'php://input' ) : '';
	$content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? sanitize_text_field( (string) $_SERVER['CONTENT_TYPE'] ) : '';
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( (string) $_SERVER['HTTP_ACCEPT'] ) : '';
	$passthrough_headers = nuclia_proxy_extract_passthrough_headers_from_server();

	$result = nuclia_proxy_execute( $zone, $path, $method, $query_params, $content_type, $accept, $body, $raw_query_string, $passthrough_headers );

	if ( isset( $result['error'] ) ) {
		status_header( $result['status'] );
		echo wp_json_encode( [ 'code' => $result['error']['code'], 'message' => $result['error']['message'] ] );
		exit;
	}

	nuclia_proxy_binary_response_prepare_output();

	status_header( $result['status'] );
	$out = nuclia_proxy_prepare_proxy_response_for_output( $result['body'], $result['headers'] );
	foreach ( $out['headers'] as $name => $value ) {
		header( (string) $name . ': ' . (string) $value );
	}
	if ( $result['stream_file'] !== '' && file_exists( $result['stream_file'] ) ) {
		readfile( $result['stream_file'] );
		@unlink( $result['stream_file'] );
	} else {
		echo $out['body'];
	}
	exit;
}

/**
 * Extract a strict allow-list of headers we proxy to Nuclia.
 *
 * @return array<string,string>
 */
function nuclia_proxy_extract_passthrough_headers_from_server(): array {
	$allowed = [
		'x-synchronous',
		'x-show-consumption',
		'x-ndb-client',
		'range',
		'if-range',
	];

	$headers = [];
	foreach ( $allowed as $header ) {
		$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );
		if ( isset( $_SERVER[ $server_key ] ) ) {
			$value = sanitize_text_field( (string) $_SERVER[ $server_key ] );
			if ( $value !== '' ) {
				$headers[ $header ] = $value;
			}
		}
	}

	return $headers;
}

/**
 * Extract a strict allow-list of headers from REST requests.
 *
 * @param WP_REST_Request $request Request object.
 * @return array<string,string>
 */
function nuclia_proxy_extract_passthrough_headers_from_request( WP_REST_Request $request ): array {
	$allowed = [
		'x-synchronous',
		'x-show-consumption',
		'x-ndb-client',
		'range',
		'if-range',
	];

	$headers = [];
	foreach ( $allowed as $header ) {
		$value = $request->get_header( $header );
		if ( is_string( $value ) && $value !== '' ) {
			$headers[ $header ] = sanitize_text_field( $value );
		}
	}

	return $headers;
}

/**
 * Determine whether a string header value should be treated as true.
 *
 * @param string $value Header value.
 * @return bool
 */
function nuclia_proxy_is_truthy_header( string $value ): bool {
	$value = strtolower( trim( $value ) );
	return in_array( $value, [ '1', 'true', 'yes', 'on' ], true );
}

/**
 * Return true when an /ask request should be streamed (incremental ndjson).
 *
 * @param string              $method              HTTP method.
 * @param string              $normalized_path     Normalized API path.
 * @param array<string,string> $passthrough_headers Forwarded request headers.
 * @return bool
 */
function nuclia_proxy_should_stream_ask( string $method, string $normalized_path, array $passthrough_headers ): bool {
	if ( $method !== 'POST' ) {
		return false;
	}
	if ( ! preg_match( '#/ask/?$#', $normalized_path ) ) {
		return false;
	}

	$synchronous = (string) ( $passthrough_headers['x-synchronous'] ?? '' );
	return ! nuclia_proxy_is_truthy_header( $synchronous );
}

function nuclia_register_proxy_rest_routes(): void {
	register_rest_route(
		'progress-agentic-rag/v1',
		'/nuclia/(?P<zone>[a-z0-9-]+)(?:/(?P<path>.*))?',
		[
			[
				'methods' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' ],
				'callback' => 'nuclia_proxy_rest_handler',
				'permission_callback' => '__return_true',
				'args' => [
					'zone' => [
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value ) && $value !== '';
						},
					],
					'path' => [
						'default' => '',
					],
				],
			],
		]
	);
}

/**
 * Stream a response directly using cURL.
 * Passes chunks directly to browser for true incremental delivery.
 *
 * This function directly outputs headers and content, then exits.
 * It does NOT return - control never passes back to the caller.
 *
 * @param string                $remote_url      Full remote URL with query params.
 * @param string                $method          HTTP method.
 * @param string                $token           Nuclia service account token (empty if using eph-token).
 * @param array<string,string>  $request_headers Extra headers to send upstream.
 * @param string                $request_body    Request body for non-GET/HEAD calls.
 * @return never
 */
function nuclia_proxy_stream_with_curl( string $remote_url, string $method, string $token, array $request_headers = [], string $request_body = '' ): void {
	nuclia_proxy_binary_response_prepare_output();
	$ch = curl_init( $remote_url );
	$method = strtoupper( $method );
	nuclia_proxy_send_cors_headers();

	// Track if we have processed headers yet
	$headers_processed = false;

	// Capture HTTP status code from status line
	$http_status = 200;

	// Capture response headers to forward to client
	$response_headers = [];

	/**
	 * CURLOPT_HEADERFUNCTION callback - receives response headers before body.
	 */
	$header_callback = function( $ch, $header_line ) use ( &$response_headers, &$http_status ) {
		$len = strlen( $header_line );

		// Upstream may respond over HTTP/2, so status parsing must support both
		// HTTP/1.x and HTTP/2 status lines.
		if ( preg_match( '#^HTTP/(?:\d(?:\.\d+)?)\s+(\d+)#', $header_line, $matches ) ) {
			// Upstream can emit multiple header blocks (redirects/intermediate responses).
			// Reset captured headers on each new status line so final status/headers win.
			$response_headers = [];
			$http_status = (int) $matches[1];
			return $len;
		}

		// Skip empty lines and continuation markers
		if ( $len <= 2 ) {
			return $len;
		}

		// Parse header name:value
		$parts = explode( ':', $header_line, 2 );
		if ( count( $parts ) === 2 ) {
			$name = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $name !== '' && $value !== '' ) {
				$response_headers[ $name ] = $value;
			}
		}

		return $len;
	};

	// Build cURL headers
	$curl_headers = [
		'Accept-Encoding: identity', // No compression for streaming
	];
	if ( $token !== '' ) {
		$curl_headers[] = 'X-NUCLIA-SERVICEACCOUNT: Bearer ' . $token;
	}
	foreach ( $request_headers as $name => $value ) {
		if ( ! is_string( $name ) || ! is_string( $value ) || $value === '' ) {
			continue;
		}
		if ( strtolower( $name ) === 'accept-encoding' ) {
			continue;
		}
		if ( nuclia_proxy_is_upstream_auth_header_blocked( $name ) ) {
			continue;
		}
		$curl_headers[] = $name . ': ' . $value;
	}

	// Headers to skip when forwarding to client
	$skip_headers = [
		'connection',
		'keep-alive',
		'proxy-authenticate',
		'proxy-authorization',
		'te',
		'trailer',
		'transfer-encoding',
		'upgrade',
		'content-encoding',
	];

	/**
	 * CURLOPT_WRITEFUNCTION callback - receives body data chunks.
	 */
	$write_callback = function( $ch, $data ) use ( &$response_headers, &$http_status, &$headers_processed, $skip_headers, $remote_url ) {
		$chunk_len = strlen( $data );

		// On first chunk, send headers BEFORE any body output
		if ( ! $headers_processed ) {
			$headers_processed = true;

			// Status must be fixed before any body bytes are echoed/flushed, or clients
			// may keep the default 200 even when upstream returned an error status.
			http_response_code( $http_status );
			status_header( $http_status );
			header( 'X-Accel-Buffering: no' );
			// Debug visibility headers: expose upstream metadata without changing payload
			// shape consumed by the widget/client JSON contract.
			header( 'X-Nuclia-Upstream-Status: ' . (string) $http_status );
			$upstream_content_type = (string) ( $response_headers['Content-Type'] ?? $response_headers['content-type'] ?? '' );
			if ( $upstream_content_type !== '' ) {
				header( 'X-Nuclia-Upstream-Content-Type: ' . $upstream_content_type );
			}
			header( 'X-Nuclia-Upstream-URL: ' . nuclia_proxy_upstream_url_debug_header( $remote_url ) );
			foreach ( $response_headers as $name => $value ) {
				$lower = strtolower( $name );
				if ( in_array( $lower, $skip_headers, true ) ) {
					continue;
				}
				header( $name . ': ' . $value, false );
			}
		}

		// Stream chunk directly to browser
		echo $data;

		if ( function_exists( 'ob_flush' ) && ob_get_level() > 0 ) {
			@ob_flush();
		}
		if ( function_exists( 'flush' ) ) {
			flush();
		}

		return $chunk_len;
	};

	// Configure cURL
	curl_setopt_array( $ch, [
		CURLOPT_RETURNTRANSFER => false,    // Don't return response, use callbacks
		CURLOPT_HEADER => false,            // Don't include headers in output
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_FOLLOWLOCATION => true,     // Follow redirects
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_WRITEFUNCTION => $write_callback,
		CURLOPT_HEADERFUNCTION => $header_callback,
		CURLOPT_HTTPHEADER => $curl_headers,
	] );
	if ( $method === 'HEAD' ) {
		curl_setopt( $ch, CURLOPT_NOBODY, true );
	}
	if ( $request_body !== '' && $method !== 'GET' && $method !== 'HEAD' ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $request_body );
	}

	// Execute request
	curl_exec( $ch );
	$error = curl_error( $ch );
	curl_close( $ch );

	// Handle cURL errors before sending any success headers.
	if ( $error !== '' ) {
		nuclia_proxy_send_cors_headers();
		status_header( 502 );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( [ 'error' => 'cURL error: ' . $error ] );
		exit;
	}

	// Some responses can be valid but contain no body (e.g. HEAD or 204).
	if ( ! $headers_processed ) {
		http_response_code( $http_status );
		status_header( $http_status );
		header( 'X-Accel-Buffering: no' );
		// Debug visibility headers: expose upstream metadata without changing payload
		// shape consumed by the widget/client JSON contract.
		header( 'X-Nuclia-Upstream-Status: ' . (string) $http_status );
		$upstream_content_type = (string) ( $response_headers['Content-Type'] ?? $response_headers['content-type'] ?? '' );
		if ( $upstream_content_type !== '' ) {
			header( 'X-Nuclia-Upstream-Content-Type: ' . $upstream_content_type );
		}
		header( 'X-Nuclia-Upstream-URL: ' . nuclia_proxy_upstream_url_debug_header( $remote_url ) );
		foreach ( $response_headers as $name => $value ) {
			$lower = strtolower( $name );
			if ( in_array( $lower, $skip_headers, true ) ) {
				continue;
			}
			header( $name . ': ' . $value, false );
		}
	}

	// Exit to prevent WordPress from sending additional output
	exit;
}

/**
 * Safe value for debug response headers (no CRLF, bounded length).
 *
 * @param string $value Raw value.
 * @return string
 */
function nuclia_proxy_debug_header_value( string $value ): string {
	$value = str_replace( [ "\r", "\n" ], '', $value );
	return substr( $value, 0, 2048 );
}

/**
 * Redact sensitive query values for debug headers (never expose eph-token to the browser).
 *
 * @param string $url Full upstream URL.
 * @return string
 */
function nuclia_proxy_redact_sensitive_query_params_for_debug( string $url ): string {
	if ( $url === '' ) {
		return '';
	}
	return (string) preg_replace( '/(?<=[?&])eph-token=[^&]*/i', 'eph-token=REDACTED', $url );
}

/**
 * Safe value for X-Nuclia-Upstream-URL (redacted + length bound).
 *
 * @param string $remote_url Upstream URL actually requested.
 * @return string
 */
function nuclia_proxy_upstream_url_debug_header( string $remote_url ): string {
	return nuclia_proxy_debug_header_value( nuclia_proxy_redact_sensitive_query_params_for_debug( $remote_url ) );
}

/**
 * Silence PHP warnings/notices for binary passthrough and drop output buffers so no stray bytes precede the file.
 */
function nuclia_proxy_binary_response_prepare_output(): void {
	@error_reporting( 0 );
	@ini_set( 'display_errors', '0' );
	@ini_set( 'display_startup_errors', '0' );
	@ini_set( 'log_errors', '0' );
	while ( ob_get_level() > 0 ) {
		@ob_end_clean();
	}
}

/**
 * If $s is whitespace-stripped base64 for a PDF, return decoded bytes; otherwise null.
 */
function nuclia_proxy_try_decode_pdf_base64_string( string $s ): ?string {
	$packed = preg_replace( '/\s+/', '', $s );
	if ( $packed === null || strlen( $packed ) < 100 ) {
		return null;
	}
	if ( ! preg_match( '/^[A-Za-z0-9+\/]+=*$/', $packed ) ) {
		return null;
	}
	$raw = base64_decode( $packed, true );
	if ( $raw === false || $raw === '' ) {
		return null;
	}
	if ( strncmp( $raw, '%PDF', 4 ) !== 0 ) {
		return null;
	}
	return $raw;
}

/**
 * Turn JSON-wrapped or base64-wrapped PDF payloads into raw PDF bytes when detectable.
 *
 * @return array{body: string, content_type: ?string} content_type set when body was transformed.
 */
function nuclia_proxy_maybe_decode_binary_response_body( string $body, string $content_type_header ): array {
	$trim = ltrim( $body );
	if ( $trim === '' ) {
		return [ 'body' => $body, 'content_type' => null ];
	}
	// JSON envelope with a base64 PDF field.
	if ( $trim[0] === '{' ) {
		$data = json_decode( $body, true );
		if ( is_array( $data ) ) {
			foreach ( [ 'data', 'content', 'body', 'file', 'blob', 'payload', 'base64' ] as $key ) {
				if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) || $data[ $key ] === '' ) {
					continue;
				}
				$candidate = nuclia_proxy_try_decode_pdf_base64_string( $data[ $key ] );
				if ( $candidate !== null ) {
					return [ 'body' => $candidate, 'content_type' => 'application/pdf' ];
				}
			}
		}
	}
	// Whole body is base64 for a PDF (often mislabeled as json/text/octet-stream).
	if ( ! str_starts_with( $trim, '%PDF' ) ) {
		$candidate = nuclia_proxy_try_decode_pdf_base64_string( $body );
		if ( $candidate !== null ) {
			$ct_lower = strtolower( $content_type_header );
			if ( str_contains( $ct_lower, 'json' )
				|| str_contains( $ct_lower, 'text/' )
				|| str_contains( $ct_lower, 'octet-stream' )
				|| $content_type_header === '' ) {
				return [ 'body' => $candidate, 'content_type' => 'application/pdf' ];
			}
		}
	}
	return [ 'body' => $body, 'content_type' => null ];
}

/**
 * @param array<string,string> $headers
 * @return array{headers: array<string,string>, body: string}
 */
function nuclia_proxy_prepare_proxy_response_for_output( string $body, array $headers ): array {
	$out_headers = $headers;
	$ct = (string) ( $out_headers['Content-Type'] ?? $out_headers['content-type'] ?? '' );
	$processed = nuclia_proxy_maybe_decode_binary_response_body( $body, $ct );
	if ( $processed['content_type'] !== null && $processed['content_type'] !== '' ) {
		$out_headers['Content-Type'] = $processed['content_type'];
		unset( $out_headers['content-type'], $out_headers['Content-Length'], $out_headers['content-length'] );
		return [ 'headers' => $out_headers, 'body' => $processed['body'] ];
	}
	return [ 'headers' => $out_headers, 'body' => $processed['body'] ];
}

/**
 * Buffered GET for binary/download: single raw body pass, optional base64→PDF decode, no incremental PHP warnings.
 *
 * @param string               $remote_url Full upstream URL.
 * @param string               $token      Service token (empty when ephemeral-only).
 * @param array<string,string> $binary_headers Extra request headers (Accept, Range, …).
 * @return never
 */
function nuclia_proxy_serve_binary_get_with_curl( string $remote_url, string $token, array $binary_headers ): void {
	nuclia_proxy_binary_response_prepare_output();
	nuclia_proxy_send_cors_headers();

	$max_bytes = 52428800;
	if ( defined( 'NUCLIA_PROXY_MAX_BUFFERED_DOWNLOAD_BYTES' ) ) {
		$v = constant( 'NUCLIA_PROXY_MAX_BUFFERED_DOWNLOAD_BYTES' );
		if ( is_int( $v ) && $v > 0 ) {
			$max_bytes = $v;
		}
	}

	$ch = curl_init( $remote_url );
	$curl_headers = [ 'Accept-Encoding: identity' ];
	if ( $token !== '' ) {
		$curl_headers[] = 'X-NUCLIA-SERVICEACCOUNT: Bearer ' . $token;
	}
	foreach ( $binary_headers as $name => $value ) {
		if ( ! is_string( $name ) || ! is_string( $value ) || $value === '' ) {
			continue;
		}
		if ( strtolower( $name ) === 'accept-encoding' ) {
			continue;
		}
		if ( nuclia_proxy_is_upstream_auth_header_blocked( $name ) ) {
			continue;
		}
		$curl_headers[] = $name . ': ' . $value;
	}

	curl_setopt_array( $ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 120,
		CURLOPT_HTTPHEADER => $curl_headers,
	] );

	$raw = curl_exec( $ch );
	$errno = curl_errno( $ch );
	$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );

	if ( $errno !== 0 || ! is_string( $raw ) ) {
		nuclia_proxy_send_cors_headers();
		status_header( 502 );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( [ 'error' => 'Upstream request failed' ] );
		exit;
	}

	$header_block = substr( $raw, 0, $header_size );
	$body = substr( $raw, $header_size );
	$http_status = 200;
	$response_headers = [];
	foreach ( explode( "\r\n", $header_block ) as $line ) {
		if ( preg_match( '#^HTTP/(?:\d(?:\.\d+)?)\s+(\d+)#', $line, $m ) ) {
			$http_status = (int) $m[1];
			$response_headers = [];
			continue;
		}
		if ( strpos( $line, ':' ) === false ) {
			continue;
		}
		list( $hn, $hv ) = explode( ':', $line, 2 );
		$hn = trim( $hn );
		$hv = trim( $hv );
		if ( $hn !== '' && $hv !== '' ) {
			$response_headers[ $hn ] = $hv;
		}
	}

	if ( strlen( $body ) > $max_bytes ) {
		nuclia_proxy_send_cors_headers();
		status_header( 502 );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( [ 'error' => 'Response too large for proxy buffer' ] );
		exit;
	}

	$upstream_ct = (string) ( $response_headers['Content-Type'] ?? $response_headers['content-type'] ?? '' );
	$decoded = nuclia_proxy_maybe_decode_binary_response_body( $body, $upstream_ct );
	$out_body = $decoded['body'];
	if ( $decoded['content_type'] !== null && $decoded['content_type'] !== '' ) {
		$response_headers['Content-Type'] = $decoded['content_type'];
		unset( $response_headers['content-type'], $response_headers['Content-Length'], $response_headers['content-length'] );
	}

	nuclia_proxy_send_cors_headers();
	http_response_code( $http_status );
	status_header( $http_status );
	header( 'X-Accel-Buffering: no' );
	header( 'X-Nuclia-Upstream-Status: ' . (string) $http_status );
	if ( $upstream_ct !== '' ) {
		header( 'X-Nuclia-Upstream-Content-Type: ' . $upstream_ct );
	}
	header( 'X-Nuclia-Upstream-URL: ' . nuclia_proxy_upstream_url_debug_header( $remote_url ) );

	$skip_headers = [
		'connection',
		'keep-alive',
		'proxy-authenticate',
		'proxy-authorization',
		'te',
		'trailer',
		'transfer-encoding',
		'upgrade',
		'content-encoding',
	];
	foreach ( $response_headers as $name => $value ) {
		$lower = strtolower( $name );
		if ( in_array( $lower, $skip_headers, true ) ) {
			continue;
		}
		header( $name . ': ' . $value, false );
	}
	if ( ! isset( $response_headers['Content-Length'] ) && ! isset( $response_headers['content-length'] ) && $out_body !== '' ) {
		header( 'Content-Length: ' . (string) strlen( $out_body ) );
	}
	echo $out_body;
	exit;
}

/**
 * Raw client query string: prefer QUERY_STRING, then REDIRECT_QUERY_STRING, then REQUEST_URI.
 * Some stacks omit or truncate QUERY_STRING; REQUEST_URI is more reliable for repeated params.
 *
 * @return string Query string without leading '?'.
 */
function nuclia_proxy_get_client_query_string(): string {
	if ( isset( $_SERVER['QUERY_STRING'] ) && is_string( $_SERVER['QUERY_STRING'] ) && $_SERVER['QUERY_STRING'] !== '' ) {
		return (string) $_SERVER['QUERY_STRING'];
	}
	if ( isset( $_SERVER['REDIRECT_QUERY_STRING'] ) && is_string( $_SERVER['REDIRECT_QUERY_STRING'] ) && $_SERVER['REDIRECT_QUERY_STRING'] !== '' ) {
		return (string) $_SERVER['REDIRECT_QUERY_STRING'];
	}
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$from_uri = is_string( $request_uri ) ? (string) parse_url( $request_uri, PHP_URL_QUERY ) : '';
	return $from_uri !== null && $from_uri !== '' ? $from_uri : '';
}

/**
 * Fix mistaken use of "?" between query pairs (e.g. show=value?show=extracted → show=value&show=extracted).
 * Unencoded "?" before key= inside the query segment is almost always a broken "&".
 *
 * @param string $qs Query string (no leading "?").
 * @return string
 */
function nuclia_proxy_normalize_query_delimiters( string $qs ): string {
	if ( $qs === '' || ! str_contains( $qs, '?' ) ) {
		return $qs;
	}
	// Repeat until stable so chained mistakes are repaired.
	$prev = '';
	while ( $prev !== $qs ) {
		$prev = $qs;
		$qs = preg_replace( '/\?(?=([a-zA-Z0-9_.-]+)=)/', '&', $qs ) ?? $qs;
	}
	return $qs;
}

/**
 * Strip proxy-internal params from a raw query string, preserving repeated params.
 *
 * PHP's $_GET collapses repeated keys (e.g. ?show=value&show=extracted → show=extracted).
 * This function works on the raw string so repeated params survive intact.
 *
 * @param string $raw_qs Raw query string from the client request.
 * @return string Cleaned query string (no leading '?').
 */
function nuclia_proxy_clean_query_string( string $raw_qs ): string {
	if ( $raw_qs === '' ) {
		return '';
	}
	$raw_qs = nuclia_proxy_normalize_query_delimiters( $raw_qs );
	$strip_keys = [ 'rest_route', 'nuclia_proxy', 'nuclia_proxy_zone', 'nuclia_proxy_path' ];
	$parts = explode( '&', $raw_qs );
	$kept = [];
	foreach ( $parts as $part ) {
		if ( $part === '' ) {
			continue;
		}
		$eq = strpos( $part, '=' );
		$key = $eq !== false ? substr( $part, 0, $eq ) : $part;
		$key = urldecode( $key );
		if ( ! in_array( $key, $strip_keys, true ) ) {
			$kept[] = $part;
		}
	}
	return implode( '&', $kept );
}

/**
 * Remove duplicate identical "key=value" segments while preserving intentional repeats
 * (e.g. show=value&show=extracted) that differ by value.
 *
 * @param string $qs Query string without leading '?'.
 * @return string
 */
function nuclia_proxy_dedupe_identical_query_pairs( string $qs ): string {
	if ( $qs === '' || ! str_contains( $qs, '&' ) ) {
		return $qs;
	}
	$parts = explode( '&', $qs );
	$seen = [];
	$out = [];
	foreach ( $parts as $part ) {
		if ( $part === '' ) {
			continue;
		}
		if ( isset( $seen[ $part ] ) ) {
			continue;
		}
		$seen[ $part ] = true;
		$out[] = $part;
	}
	return implode( '&', $out );
}

/**
 * True when the merged upstream query string includes eph-token (PHP $_GET may omit it).
 */
function nuclia_proxy_query_string_contains_eph_token( string $cleaned_query_string ): bool {
	if ( $cleaned_query_string === '' ) {
		return false;
	}
	return (bool) preg_match( '/(^|&)eph-token=/i', $cleaned_query_string );
}

/**
 * Signed / ephemeral requests: upstream auth is only eph-token in the query (no service headers).
 *
 * @param string               $normalized_path     Normalized Nuclia API path (e.g. api/v1/kb/.../download/field).
 * @param string               $cleaned_query_string Query forwarded upstream (after WP keys stripped).
 * @param array<string,mixed>   $query_params       Parsed query (may omit eph-token if only in raw string).
 */
function nuclia_proxy_is_ephemeral_download_request( string $normalized_path, string $cleaned_query_string, array $query_params ): bool {
	if ( nuclia_proxy_query_string_contains_eph_token( $cleaned_query_string ) ) {
		return true;
	}
	$eph = $query_params['eph-token'] ?? null;
	if ( is_string( $eph ) && $eph !== '' ) {
		return true;
	}
	if ( is_array( $eph ) ) {
		foreach ( $eph as $v ) {
			if ( is_string( $v ) && $v !== '' ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Client/browser headers that must never be forwarded to Nuclia (prevents mixed auth).
 */
function nuclia_proxy_is_upstream_auth_header_blocked( string $header_name ): bool {
	return in_array( strtolower( $header_name ), [ 'authorization', 'x-nuclia-serviceaccount', 'proxy-authorization' ], true );
}

/**
 * @param array<string,string> $headers
 * @return array<string,string>
 */
function nuclia_proxy_filter_blocked_upstream_request_headers( array $headers ): array {
	$out = [];
	foreach ( $headers as $name => $value ) {
		if ( ! is_string( $name ) || ! is_string( $value ) || $value === '' ) {
			continue;
		}
		if ( nuclia_proxy_is_upstream_auth_header_blocked( $name ) ) {
			continue;
		}
		$out[ $name ] = $value;
	}
	return $out;
}

/**
 * Execute the Nuclia proxy request. Shared by REST and path-only handlers.
 *
 * @param string               $zone            Nuclia zone.
 * @param string               $path            API path (e.g. v1/kb/.../field).
 * @param string               $method          HTTP method.
 * @param array<string,mixed>   $query_params   Parsed query parameters (for eph-token check).
 * @param string               $content_type    Request Content-Type header.
 * @param string               $accept          Request Accept header.
 * @param string               $body            Request body.
 * @param string               $raw_query_string Raw query string preserving repeated params.
 * @param array<string,string>  $passthrough_headers Allow-listed headers forwarded upstream.
 * @return array{status: int, headers: array<string,string>, body: string, stream_file: string, error?: array{code: string, message: string}}
 */
function nuclia_proxy_execute( string $zone, string $path, string $method, array $query_params, string $content_type, string $accept, string $body, string $raw_query_string = '', array $passthrough_headers = [] ): array {
	$zone = sanitize_title( $zone );
	if ( $zone === '' ) {
		return [ 'status' => 400, 'headers' => [], 'body' => '', 'stream_file' => '', 'error' => [ 'code' => 'nuclia_proxy_invalid_zone', 'message' => 'Invalid zone' ] ];
	}

	$token = sanitize_text_field( (string) get_option( 'nuclia_token', '' ) );
	if ( $token === '' ) {
		return [ 'status' => 500, 'headers' => [], 'body' => '', 'stream_file' => '', 'error' => [ 'code' => 'nuclia_proxy_missing_token', 'message' => 'Nuclia token is not configured.' ] ];
	}

	$method = strtoupper( $method );
	if ( $method === 'OPTIONS' ) {
		return [ 'status' => 200, 'headers' => [], 'body' => '', 'stream_file' => '' ];
	}

	$path_sidecar_qs = '';
	$path_for_norm = $path;
	if ( str_contains( $path, '?' ) ) {
		$path_parts = explode( '?', $path, 2 );
		$path_for_norm = $path_parts[0];
		$path_sidecar_qs = isset( $path_parts[1] ) ? (string) $path_parts[1] : '';
	}

	$normalized_path = nuclia_proxy_normalize_path( $path_for_norm );
	if ( $normalized_path === null ) {
		return [ 'status' => 400, 'headers' => [], 'body' => '', 'stream_file' => '', 'error' => [ 'code' => 'nuclia_proxy_invalid_path', 'message' => 'Invalid path' ] ];
	}

	$remote_url = sprintf(
		'https://%1$s.rag.progress.cloud/%2$s',
		rawurlencode( $zone ),
		$normalized_path
	);

	// Use raw query string to preserve repeated params (e.g. ?show=value&show=extracted).
	// PHP's $_GET/$_REQUEST collapse repeated keys, losing data the Nuclia API needs.
	$cleaned_qs = nuclia_proxy_clean_query_string( $raw_query_string );
	if ( $path_sidecar_qs !== '' ) {
		$path_q_clean = nuclia_proxy_clean_query_string( $path_sidecar_qs );
		if ( $path_q_clean !== '' ) {
			$cleaned_qs = $cleaned_qs === '' ? $path_q_clean : ( $path_q_clean . '&' . $cleaned_qs );
		}
	}
	// Path + outer query can both carry eph-token (e.g. rest_route embed + QUERY_STRING); drop identical pairs only.
	$cleaned_qs = nuclia_proxy_dedupe_identical_query_pairs( $cleaned_qs );
	if ( $cleaned_qs !== '' ) {
		$remote_url .= '?' . $cleaned_qs;
	}

	$filtered_passthrough = nuclia_proxy_filter_blocked_upstream_request_headers( $passthrough_headers );
	$ephemeral_upstream = nuclia_proxy_is_ephemeral_download_request( $normalized_path, $cleaned_qs, $query_params );
	$token_for_upstream = $ephemeral_upstream ? '' : $token;

	$binary_upstream_headers = [];
	if ( $accept !== '' ) {
		$binary_upstream_headers['Accept'] = $accept;
	}
	if ( isset( $filtered_passthrough['range'] ) && is_string( $filtered_passthrough['range'] ) && $filtered_passthrough['range'] !== '' ) {
		$binary_upstream_headers['Range'] = $filtered_passthrough['range'];
	}
	if ( isset( $filtered_passthrough['if-range'] ) && is_string( $filtered_passthrough['if-range'] ) && $filtered_passthrough['if-range'] !== '' ) {
		$binary_upstream_headers['If-Range'] = $filtered_passthrough['if-range'];
	}

	// Binary / large GET: cURL streaming avoids wp_remote_request compression + buffering issues.
	$should_stream_binary_get = ( $method === 'GET' || $method === 'HEAD' ) && (
		str_contains( $normalized_path, '/download/' )
		|| (bool) preg_match( '#/resource/[^/]+/file/#', $normalized_path )
	);
	$is_ask_endpoint = (bool) preg_match( '#/ask/?$#', $normalized_path );

	// For downloads and resource file payloads, stream via cURL (binary-safe, correct headers).
	// Note: stream_with_curl calls exit() and never returns for GET.
	if ( $should_stream_binary_get ) {
		if ( $method === 'HEAD' ) {
			nuclia_proxy_binary_response_prepare_output();
			$curl_headers = [ 'Accept-Encoding: identity' ];
			if ( $accept !== '' ) {
				$curl_headers[] = 'Accept: ' . $accept;
			}
			if ( isset( $filtered_passthrough['range'] ) && is_string( $filtered_passthrough['range'] ) && $filtered_passthrough['range'] !== '' ) {
				$curl_headers[] = 'Range: ' . $filtered_passthrough['range'];
			}
			if ( isset( $filtered_passthrough['if-range'] ) && is_string( $filtered_passthrough['if-range'] ) && $filtered_passthrough['if-range'] !== '' ) {
				$curl_headers[] = 'If-Range: ' . $filtered_passthrough['if-range'];
			}
			if ( $token_for_upstream !== '' ) {
				$curl_headers[] = 'X-NUCLIA-SERVICEACCOUNT: Bearer ' . $token_for_upstream;
			}
			$ch = curl_init( $remote_url );
			curl_setopt_array( $ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => true,
				CURLOPT_NOBODY => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 20,
				CURLOPT_HTTPHEADER => $curl_headers,
			] );
			$response = curl_exec( $ch );
			$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
			curl_close( $ch );

			$headers = [];
			if ( is_string( $response ) && $response !== '' ) {
				$header_text = substr( $response, 0, $header_size );
				$header_lines = explode( "\r\n", $header_text );
				foreach ( $header_lines as $line ) {
					if ( strpos( $line, ':' ) !== false ) {
						list( $name, $value ) = explode( ':', $line, 2 );
						$headers[ trim( $name ) ] = trim( $value );
					}
				}
			}

			status_header( $http_code );
			header( 'X-Nuclia-Upstream-Status: ' . (string) $http_code );
			$upstream_ct = (string) ( $headers['Content-Type'] ?? $headers['content-type'] ?? '' );
			if ( $upstream_ct !== '' ) {
				header( 'X-Nuclia-Upstream-Content-Type: ' . $upstream_ct );
			}
			header( 'X-Nuclia-Upstream-URL: ' . nuclia_proxy_upstream_url_debug_header( $remote_url ) );
			$skip_headers = [ 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'content-encoding' ];
			foreach ( $headers as $name => $value ) {
				$lower = strtolower( $name );
				if ( in_array( $lower, $skip_headers, true ) ) {
					continue;
				}
				header( $name . ': ' . $value );
			}
			exit;
		}

		nuclia_proxy_serve_binary_get_with_curl( $remote_url, $token_for_upstream, $binary_upstream_headers );
		// Never reaches here due to exit() in stream function
	}

	// /ask may return regular JSON or streamed/chunked output. Buffering through
	// wp_remote_request() can corrupt or truncate proxy responses, so always use
	// the cURL passthrough streaming path for /ask endpoints.
	if ( $is_ask_endpoint ) {
		$stream_headers = [
			'Content-Type' => $content_type !== '' ? $content_type : 'application/json',
			'Accept' => ( $accept !== '' && $accept !== '*/*' ) ? $accept : 'application/x-ndjson',
		];
		foreach ( $filtered_passthrough as $name => $value ) {
			if ( is_string( $name ) && is_string( $value ) && $value !== '' ) {
				$stream_headers[ $name ] = $value;
			}
		}
		nuclia_proxy_stream_with_curl( $remote_url, $method, $token_for_upstream, $stream_headers, $body );
		// Never reaches here due to exit() in stream function
	}

	// For non-stream endpoints, use WordPress HTTP API.
	$headers = array_filter(
		[
			'X-NUCLIA-SERVICEACCOUNT' => $token_for_upstream !== '' ? 'Bearer ' . $token_for_upstream : null,
			'Content-Type' => $content_type !== '' ? $content_type : null,
			'Accept' => $accept !== '' ? $accept : null,
			// Identity avoids br/gzip + header stripping mismatches that corrupt binary bodies.
			'Accept-Encoding' => 'identity',
		]
	);
	foreach ( $filtered_passthrough as $name => $value ) {
		if ( is_string( $name ) && is_string( $value ) && $value !== '' ) {
			$headers[ $name ] = $value;
		}
	}

	$request_args = [
		'method' => $method,
		'timeout' => 20,
		'headers' => $headers,
		'redirection' => 5,
	];
	if ( $body !== '' && $method !== 'GET' ) {
		$request_args['body'] = $body;
	}

	$response = wp_remote_request( $remote_url, $request_args );

	if ( is_wp_error( $response ) ) {
		return [ 'status' => 502, 'headers' => [], 'body' => '', 'stream_file' => '', 'error' => [ 'code' => $response->get_error_code(), 'message' => $response->get_error_message() ] ];
	}

	$status = wp_remote_retrieve_response_code( $response );
	$payload = wp_remote_retrieve_body( $response );
	$response_headers = wp_remote_retrieve_headers( $response );

	$out_headers = [];
	$skip_headers = [ 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'content-encoding' ];
	foreach ( $response_headers as $name => $value ) {
		$lower = strtolower( (string) $name );
		if ( in_array( $lower, $skip_headers, true ) || $value === '' || $value === null ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
		}
		$out_headers[ (string) $name ] = (string) $value;
	}

	$out_headers['X-Nuclia-Upstream-Status'] = (string) (int) $status;
	$upstream_ct_dbg = $out_headers['Content-Type'] ?? $out_headers['content-type'] ?? '';
	if ( is_string( $upstream_ct_dbg ) && $upstream_ct_dbg !== '' ) {
		$out_headers['X-Nuclia-Upstream-Content-Type'] = $upstream_ct_dbg;
	}
	$out_headers['X-Nuclia-Upstream-URL'] = nuclia_proxy_upstream_url_debug_header( $remote_url );

	return [
		'status' => $status,
		'headers' => $out_headers,
		'body' => $payload,
		'stream_file' => '',
	];
}

/**
 * Proxy Nuclia API requests through WordPress, injecting the service account key.
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response|WP_Error
 */
function nuclia_proxy_rest_handler( WP_REST_Request $request ) {
	nuclia_proxy_send_cors_headers();
	if ( strtoupper( (string) $request->get_method() ) === 'OPTIONS' ) {
		nuclia_proxy_send_rest_preflight_cors_headers();
		$preflight = new WP_REST_Response( null, 200 );
		$preflight->header( 'Access-Control-Allow-Origin', isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( (string) $_SERVER['HTTP_ORIGIN'] ) : '*' );
		$preflight->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
		$preflight->header( 'Access-Control-Allow-Headers', nuclia_proxy_cors_allow_headers_value() );
		$preflight->header( 'Access-Control-Expose-Headers', 'X-Nuclia-Upstream-Status, X-Nuclia-Upstream-Content-Type, X-Nuclia-Upstream-URL, Nuclia-Learning-Id, X-NUCLIA-TRACE-ID' );
		$preflight->header( 'Vary', 'Origin' );
		$preflight->header( 'Access-Control-Max-Age', '86400' );
		return $preflight;
	}

	$zone = sanitize_title( (string) $request['zone'] );
	$path = (string) $request['path'];

	$query_params = $request->get_query_params();
	unset( $query_params['rest_route'] );
	$raw_query_string = nuclia_proxy_get_client_query_string();

	$result = nuclia_proxy_execute(
		$zone,
		$path,
		$request->get_method(),
		$query_params,
		$request->get_header( 'content-type' ) ?? '',
		$request->get_header( 'accept' ) ?? '',
		$request->get_body(),
		$raw_query_string,
		nuclia_proxy_extract_passthrough_headers_from_request( $request )
	);

	if ( isset( $result['error'] ) ) {
		return new WP_Error( $result['error']['code'], $result['error']['message'], [ 'status' => $result['status'] ] );
	}

	$content_type = (string) ( $result['headers']['Content-Type'] ?? $result['headers']['content-type'] ?? '' );
	$bin = nuclia_proxy_maybe_decode_binary_response_body( $result['body'], $content_type );
	if ( $bin['content_type'] !== null && $bin['content_type'] !== '' ) {
		$result['body'] = $bin['body'];
		$result['headers']['Content-Type'] = $bin['content_type'];
		unset( $result['headers']['content-type'], $result['headers']['Content-Length'], $result['headers']['content-length'] );
	}

	$rest_response = new WP_REST_Response( null, $result['status'] );
	foreach ( $result['headers'] as $name => $value ) {
		$rest_response->header( $name, $value );
	}

	$content_type = (string) ( $result['headers']['Content-Type'] ?? $result['headers']['content-type'] ?? '' );
	$is_json = $bin['content_type'] === null && stripos( $content_type, 'application/json' ) !== false;
	if ( $is_json && $result['body'] !== '' ) {
		$decoded = json_decode( $result['body'], true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$rest_response->set_data( $decoded );
			return $rest_response;
		}
	}

	// Non-JSON or streaming: serve via filter so body is output when REST server serves the response.
	$proxy_result = $result;
	add_filter(
		'rest_pre_serve_request',
		static function ( $served, $response, $req, $server ) use ( $rest_response, $proxy_result ) {
			if ( $response !== $rest_response ) {
				return $served;
			}
			$out = nuclia_proxy_prepare_proxy_response_for_output( $proxy_result['body'], $proxy_result['headers'] );
			nuclia_proxy_binary_response_prepare_output();
			nuclia_proxy_send_cors_headers();
			if ( ! headers_sent() ) {
				status_header( $proxy_result['status'] );
				foreach ( $out['headers'] as $name => $value ) {
					header( (string) $name . ': ' . (string) $value );
				}
				if ( $proxy_result['stream_file'] !== '' && file_exists( $proxy_result['stream_file'] ) ) {
					if ( ! isset( $out['headers']['Content-Length'] ) && ! isset( $out['headers']['content-length'] ) ) {
						$size = filesize( $proxy_result['stream_file'] );
						if ( $size !== false ) {
							header( 'Content-Length: ' . (string) $size );
						}
					}
				}
			}
			if ( $proxy_result['stream_file'] !== '' && file_exists( $proxy_result['stream_file'] ) ) {
				readfile( $proxy_result['stream_file'] );
				@unlink( $proxy_result['stream_file'] );
			} else {
				echo $out['body'];
			}
			return true;
		},
		10,
		4
	);

	return $rest_response;
}

/**
 * Normalize and validate the requested API path.
 *
 * @param string $path
 *
 * @return string|null
 */
function nuclia_proxy_normalize_path( string $path ): ?string {
	$path = ltrim( $path, '/' );

	if ( $path === '' ) {
		return 'api';
	}

	if ( str_contains( $path, '..' ) ) {
		return null;
	}

	if ( str_starts_with( $path, 'api/' ) ) {
		return $path;
	}

	if ( str_starts_with( $path, 'v1/' ) ) {
		return 'api/' . $path;
	}

	return 'api/' . $path;
}
