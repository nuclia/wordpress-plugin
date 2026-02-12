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
add_action( 'template_redirect', function() {
	if ( isset( $_SERVER['REQUEST_URI'] ) && ( strpos( $_SERVER['REQUEST_URI'], '/nuclia-proxy/' ) !== false || strpos( $_SERVER['REQUEST_URI'], '/wp-json/progress-agentic-rag/v1/nuclia/' ) !== false ) ) {
		// Disable compression
		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );
	}
}, 0 );

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

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	$query_params = isset( $_GET ) && is_array( $_GET ) ? $_GET : [];
	$body = ( $method !== 'GET' && $method !== 'HEAD' ) ? file_get_contents( 'php://input' ) : '';
	$content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? sanitize_text_field( (string) $_SERVER['CONTENT_TYPE'] ) : '';
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( (string) $_SERVER['HTTP_ACCEPT'] ) : '';

	$result = nuclia_proxy_execute( $zone, $path, $method, $query_params, $content_type, $accept, $body );

	if ( isset( $result['error'] ) ) {
		status_header( $result['status'] );
		echo wp_json_encode( [ 'code' => $result['error']['code'], 'message' => $result['error']['message'] ] );
		exit;
	}

	status_header( $result['status'] );
	foreach ( $result['headers'] as $name => $value ) {
		header( (string) $name . ': ' . (string) $value );
	}
	if ( $result['stream_file'] !== '' && file_exists( $result['stream_file'] ) ) {
		readfile( $result['stream_file'] );
		@unlink( $result['stream_file'] );
	} else {
		echo $result['body'];
	}
	exit;
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
 * Stream a download directly using cURL (no temp file).
 * Passes chunks directly to browser for optimal performance.
 *
 * This function directly outputs headers and content, then exits.
 * It does NOT return - control never passes back to the caller.
 *
 * @param string $zone         Nuclia zone.
 * @param string $normalized_path API path.
 * @param string $token        Nuclia service account token (empty if using eph-token).
 * @param string $remote_url   Full remote URL with query params.
 * @return never
 */
function nuclia_proxy_stream_with_curl( string $zone, string $normalized_path, string $token, string $remote_url ): void {
	$ch = curl_init( $remote_url );

	// Capture response headers to forward to client
	$response_headers = [];
	$header_callback = function( $ch, $header_line ) use ( &$response_headers ) {
		$len = strlen( $header_line );
		if ( $len > 2 ) { // Skip empty lines and \r\n
			$parts = explode( ':', $header_line, 2 );
			if ( count( $parts ) === 2 ) {
				$name = trim( $parts[0] );
				$value = trim( $parts[1] );
				if ( $name !== '' && $value !== '' ) {
					$response_headers[ $name ] = $value;
				}
			}
		}
		return $len;
	};

	// Build cURL headers - only add auth if token is provided (not using eph-token)
	$curl_headers = [
		'Accept-Encoding: identity', // No compression for streaming
	];
	if ( $token !== '' ) {
		$curl_headers[] = 'X-NUCLIA-SERVICEACCOUNT: Bearer ' . $token;
	}

	// Track if we've started outputting content
	$content_started = false;

	curl_setopt_array( $ch, [
		CURLOPT_RETURNTRANSFER => false,
		CURLOPT_HEADER => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_WRITEFUNCTION => function( $ch, $data ) use ( &$response_headers, &$content_started ) {
			// Set headers before first chunk of data
			if ( ! $content_started ) {
				$content_started = true;

				// Get HTTP status from cURL
				$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
				status_header( $http_code );

				// Skip certain headers
				$skip_headers = [ 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'content-encoding' ];

				foreach ( $response_headers as $name => $value ) {
					$lower = strtolower( $name );
					if ( in_array( $lower, $skip_headers, true ) ) {
						continue;
					}
					header( $name . ': ' . $value );
				}
			}

			// Stream chunk directly to browser
			echo $data;
			if ( function_exists( 'flush' ) ) {
				flush();
			}
			return strlen( $data );
		},
		CURLOPT_HEADERFUNCTION => $header_callback,
		CURLOPT_HTTPHEADER => $curl_headers,
	] );

	// Execute request
	$result = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$error = curl_error( $ch );
	curl_close( $ch );

	// Handle cURL errors
	if ( $error !== '' ) {
		status_header( 502 );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( [ 'error' => 'cURL error: ' . $error ] );
	}

	// Exit to prevent WordPress from sending additional output
	exit;
}

/**
 * Execute the Nuclia proxy request. Shared by REST and path-only handlers.
 *
 * @param string               $zone         Nuclia zone.
 * @param string               $path         API path (e.g. v1/kb/.../field).
 * @param string               $method       HTTP method.
 * @param array<string,mixed>   $query_params Query parameters (e.g. eph-token).
 * @param string               $content_type Request Content-Type header.
 * @param string               $accept       Request Accept header.
 * @param string               $body         Request body.
 * @return array{status: int, headers: array<string,string>, body: string, stream_file: string, error?: array{code: string, message: string}}
 */
function nuclia_proxy_execute( string $zone, string $path, string $method, array $query_params, string $content_type, string $accept, string $body ): array {
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

	$normalized_path = nuclia_proxy_normalize_path( $path );
	if ( $normalized_path === null ) {
		return [ 'status' => 400, 'headers' => [], 'body' => '', 'stream_file' => '', 'error' => [ 'code' => 'nuclia_proxy_invalid_path', 'message' => 'Invalid path' ] ];
	}

	$remote_url = sprintf(
		'https://%1$s.rag.progress.cloud/%2$s',
		rawurlencode( $zone ),
		$normalized_path
	);

	if ( ! empty( $query_params ) ) {
		unset( $query_params['rest_route'], $query_params['nuclia_proxy'], $query_params['nuclia_proxy_zone'], $query_params['nuclia_proxy_path'] );
		$remote_url = add_query_arg( $query_params, $remote_url );
	}

	// Detect download endpoints for optimized cURL streaming (no temp file)
	$should_stream = str_contains( $normalized_path, '/download/' );

	// For download endpoints, use raw cURL for true streaming (bypasses temp file)
	// Note: This function calls exit() and never returns
	if ( $should_stream && $method === 'GET' ) {
		// If eph-token is present, use empty token so cURL doesn't send auth header
		$token_for_curl = isset( $query_params['eph-token'] ) ? '' : $token;
		nuclia_proxy_stream_with_curl( $zone, $normalized_path, $token_for_curl, $remote_url );
		// Never reaches here due to exit() in stream function
	}

	// For non-download endpoints or non-GET methods, use WordPress HTTP API
	$temp_file = ''; // Not used anymore for streaming
	$should_stream = false; // Disable old temp file streaming

	$headers = array_filter(
		[
			'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
			'Content-Type' => $content_type !== '' ? $content_type : null,
			'Accept' => $accept !== '' ? $accept : null,
			// Don't request compression for streaming downloads - WordPress doesn't decompress when streaming to file
			// and stripping Content-Encoding header while forwarding gzipped content corrupts PDFs
			'Accept-Encoding' => $should_stream ? 'identity' : 'gzip, deflate, br, zstd'
		]
	);

	$request_args = [
		'method' => $method,
		'timeout' => $should_stream ? 60 : 20,
		'headers' => $headers,
		'redirection' => $should_stream ? 5 : 0,
	];
	if ( $body !== '' && $method !== 'GET' ) {
		$request_args['body'] = $body;
	}
	if ( $should_stream && $temp_file ) {
		$request_args['stream'] = true;
		$request_args['filename'] = $temp_file;
	}
	
	// If eph-token is present in query params, remove the Bearer auth token
	if ( isset( $query_params['eph-token'] ) ) {
		unset( $request_args['headers']['X-NUCLIA-SERVICEACCOUNT'] );
	}

	$response = wp_remote_request( $remote_url, $request_args );

	if ( is_wp_error( $response ) ) {
		return [ 'status' => 502, 'headers' => [], 'body' => '', 'stream_file' => '', 'error' => [ 'code' => $response->get_error_code(), 'message' => $response->get_error_message() ] ];
	}

	$status = wp_remote_retrieve_response_code( $response );
	$payload = $should_stream ? '' : wp_remote_retrieve_body( $response );
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

	return [
		'status' => $status,
		'headers' => $out_headers,
		'body' => $payload,
		'stream_file' => $should_stream && $temp_file ? $temp_file : '',
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
	$zone = sanitize_title( (string) $request['zone'] );
	$path = (string) $request['path'];

	$query_params = $request->get_query_params();
	unset( $query_params['rest_route'] );

	$result = nuclia_proxy_execute(
		$zone,
		$path,
		$request->get_method(),
		$query_params,
		$request->get_header( 'content-type' ) ?? '',
		$request->get_header( 'accept' ) ?? '',
		$request->get_body()
	);

	if ( isset( $result['error'] ) ) {
		return new WP_Error( $result['error']['code'], $result['error']['message'], [ 'status' => $result['status'] ] );
	}

	$rest_response = new WP_REST_Response( null, $result['status'] );
	foreach ( $result['headers'] as $name => $value ) {
		$rest_response->header( $name, $value );
	}

	$content_type = $result['headers']['Content-Type'] ?? $result['headers']['content-type'] ?? '';
	$is_json = is_string( $content_type ) && stripos( $content_type, 'application/json' ) !== false;
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
			if ( ! headers_sent() ) {
				status_header( $proxy_result['status'] );
				foreach ( $proxy_result['headers'] as $name => $value ) {
					header( (string) $name . ': ' . (string) $value );
				}
				if ( $proxy_result['stream_file'] !== '' && file_exists( $proxy_result['stream_file'] ) ) {
					if ( ! isset( $proxy_result['headers']['Content-Length'] ) && ! isset( $proxy_result['headers']['content-length'] ) ) {
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
				echo $proxy_result['body'];
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
