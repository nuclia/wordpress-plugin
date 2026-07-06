<?php
/**
 * Server-side proxy for Progress Agentic RAG widget requests.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Proxy;

use ProgressAgenticRag\Settings\SettingsRepository;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}

final class ProxyController {
	private const QUERY_VAR_ENABLED      = 'progress_agentic_rag_proxy';
	private const QUERY_VAR_ZONE         = 'progress_agentic_rag_proxy_zone';
	private const QUERY_VAR_PATH         = 'progress_agentic_rag_proxy_path';
	private const OPTION_REWRITE_VERSION = 'progress_agentic_rag_proxy_rewrite_version';

	public function __construct( private readonly SettingsRepository $settings ) {
	}

	public function register(): void {
		add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
		add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'parse_request', [ $this, 'handle_path_request' ], 0, 0 );
		add_action( 'template_redirect', [ $this, 'handle_path_request' ] );
		add_filter( 'redirect_canonical', [ $this, 'disable_canonical_redirect' ], 10, 2 );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^nuclia-proxy/([a-z0-9-]+)/?(.*)?$',
			'index.php?' . self::QUERY_VAR_ENABLED . '=1&' . self::QUERY_VAR_ZONE . '=$matches[1]&' . self::QUERY_VAR_PATH . '=$matches[2]',
			'top'
		);
	}

	public static function maybe_flush_rewrite_rules(): void {
		if ( get_option( self::OPTION_REWRITE_VERSION, '' ) === PROGRESS_AGENTIC_RAG_VERSION ) {
			return;
		}

		self::add_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( self::OPTION_REWRITE_VERSION, PROGRESS_AGENTIC_RAG_VERSION );
	}

	/**
	 * @param array<string> $vars Registered query vars.
	 * @return array<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR_ENABLED;
		$vars[] = self::QUERY_VAR_ZONE;
		$vars[] = self::QUERY_VAR_PATH;

		return $vars;
	}

	public static function proxy_url( string $zone ): string {
		$zone = sanitize_title( $zone );
		if ( '' === $zone ) {
			return '';
		}

		$path = '' === get_option( 'permalink_structure', '' ) ? 'index.php/nuclia-proxy/' . $zone : 'nuclia-proxy/' . $zone;

		return home_url( $path );
	}

	public function disable_canonical_redirect( mixed $redirect_url, string $request_url ): mixed {
		return str_contains( $request_url, '/nuclia-proxy/' ) ? false : $redirect_url;
	}

	public function handle_path_request(): void {
		$request = $this->path_request();
		if ( '' === $request['zone'] ) {
			return;
		}

		$this->send_cors_headers();

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$body   = ( 'GET' !== $method && 'HEAD' !== $method ) ? (string) file_get_contents( 'php://input' ) : '';

		$result = $this->execute(
			$request['zone'],
			$request['path'],
			$method,
			$this->client_query_string(),
			isset( $_SERVER['CONTENT_TYPE'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) : '',
			isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '',
			$body,
			$this->passthrough_headers()
		);

		status_header( $result['status'] );
		foreach ( $result['headers'] as $name => $value ) {
			header( (string) $name . ': ' . (string) $value, false );
		}

		echo $result['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param array<string,string> $passthrough_headers Allow-listed client request headers.
	 * @return array{status: int, headers: array<string,string>, body: string}
	 */
	private function execute( string $zone, string $path, string $method, string $query_string, string $content_type, string $accept, string $body, array $passthrough_headers ): array {
		$zone = strtolower( trim( $zone ) );
		if ( '' === $zone || ! preg_match( '/^[a-z0-9-]+$/', $zone ) ) {
			return $this->error( 400, 'progress_agentic_rag_proxy_invalid_zone', __( 'Invalid Progress Agentic RAG zone.', 'progress-agentic-rag' ) );
		}

		if ( ! in_array( $method, [ 'GET', 'POST', 'OPTIONS' ], true ) ) {
			return $this->error( 405, 'progress_agentic_rag_proxy_method_not_allowed', __( 'Request method is not allowed.', 'progress-agentic-rag' ) );
		}

		if ( 'OPTIONS' === $method ) {
			return [ 'status' => 200, 'headers' => [], 'body' => '' ];
		}

		$token = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		if ( '' === $token ) {
			return $this->error( 500, 'progress_agentic_rag_proxy_missing_token', __( 'Progress Agentic RAG Service token is not configured.', 'progress-agentic-rag' ) );
		}

		$normalized_path = $this->normalize_path( $path );
		if ( null === $normalized_path ) {
			return $this->error( 400, 'progress_agentic_rag_proxy_invalid_path', __( 'Invalid Progress Agentic RAG proxy path.', 'progress-agentic-rag' ) );
		}

		$remote_url = sprintf(
			'https://%s.rag.progress.cloud/%s',
			rawurlencode( $zone ),
			$normalized_path
		);

		$query_string = $this->clean_query_string( $query_string );
		if ( '' !== $query_string ) {
			$remote_url .= '?' . $query_string;
		}

		$headers = array_filter(
			[
				'X-NUCLIA-SERVICEACCOUNT' => $this->is_ephemeral_request( $query_string ) ? null : 'Bearer ' . $token,
				'Content-Type'            => '' !== $content_type ? $content_type : null,
				'Accept'                  => '' !== $accept ? $accept : null,
				'Accept-Encoding'         => 'identity',
			]
		);

		foreach ( $passthrough_headers as $name => $value ) {
			$headers[ $name ] = $value;
		}

		$args = [
			'method'      => $method,
			'timeout'     => 30,
			'redirection' => 5,
			'headers'     => $headers,
		];

		if ( '' !== $body && 'GET' !== $method ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $remote_url, $args );
		if ( is_wp_error( $response ) ) {
			return $this->error( 502, 'progress_agentic_rag_proxy_upstream_failed', $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		return [
			'status'  => $status,
			'headers' => $this->response_headers( $response, $remote_url, $status ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
		];
	}

	/**
	 * @return array{zone: string, path: string}
	 */
	private function path_request(): array {
		if ( '1' === get_query_var( self::QUERY_VAR_ENABLED, '' ) ) {
			$zone = (string) get_query_var( self::QUERY_VAR_ZONE, '' );
			if ( '' !== $zone ) {
				return [
					'zone' => $zone,
					'path' => ltrim( (string) get_query_var( self::QUERY_VAR_PATH, '' ), '/' ),
				];
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home_path && str_starts_with( $request_path, '/' . $home_path . '/' ) ) {
			$request_path = substr( $request_path, strlen( $home_path ) + 1 );
		}
		if ( str_starts_with( $request_path, '/index.php/' ) ) {
			$request_path = substr( $request_path, strlen( '/index.php' ) );
		}

		if ( ! str_starts_with( $request_path, '/nuclia-proxy/' ) ) {
			return [ 'zone' => '', 'path' => '' ];
		}

		$suffix = substr( $request_path, strlen( '/nuclia-proxy/' ) );
		$parts  = explode( '/', $suffix, 2 );

		return [
			'zone' => (string) ( $parts[0] ?? '' ),
			'path' => isset( $parts[1] ) ? ltrim( $parts[1], '/' ) : '',
		];
	}

	private function normalize_path( string $path ): ?string {
		$path = ltrim( $path, '/' );
		$decoded_path = rawurldecode( $path );

		if (
			'' === $path
			|| str_contains( $path, '..' )
			|| str_contains( $decoded_path, '..' )
			|| str_contains( $path, '\\' )
			|| str_contains( $decoded_path, '\\' )
			|| str_contains( $path, "\r" )
			|| str_contains( $decoded_path, "\r" )
			|| str_contains( $path, "\n" )
			|| str_contains( $decoded_path, "\n" )
		) {
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

	private function client_query_string(): string {
		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';

		if ( '' === $query_string && isset( $_SERVER['REQUEST_URI'] ) ) {
			$parsed = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
			$query_string = is_string( $parsed ) ? $parsed : '';
		}

		return $query_string;
	}

	private function clean_query_string( string $query_string ): string {
		if ( '' === $query_string ) {
			return '';
		}

		$strip_keys = [
			self::QUERY_VAR_ENABLED,
			self::QUERY_VAR_ZONE,
			self::QUERY_VAR_PATH,
			'rest_route',
		];
		$parts = [];

		foreach ( explode( '&', $query_string ) as $part ) {
			$key = rawurldecode( explode( '=', $part, 2 )[0] ?? '' );
			if ( '' !== $part && ! in_array( $key, $strip_keys, true ) ) {
				$parts[] = $part;
			}
		}

		return implode( '&', $parts );
	}

	/**
	 * @return array<string,string>
	 */
	private function passthrough_headers(): array {
		$allowed = [
			'x-synchronous',
			'x-show-consumption',
			'x-ndb-client',
			'range',
			'if-range',
		];
		$headers = [];

		foreach ( $allowed as $name ) {
			$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) );
			if ( isset( $_SERVER[ $server_key ] ) ) {
				$value = sanitize_text_field( (string) wp_unslash( $_SERVER[ $server_key ] ) );
				if ( '' !== $value ) {
					$headers[ $name ] = $value;
				}
			}
		}

		return $headers;
	}

	private function send_cors_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		header( 'Access-Control-Allow-Origin: ' . ( '' !== $origin ? $origin : '*' ) );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, x-synchronous, X-Synchronous, x-ndb-client, X-NDB-Client, Range, If-Range' );
		header( 'Access-Control-Expose-Headers: X-Nuclia-Upstream-Status, X-Nuclia-Upstream-Content-Type, X-Nuclia-Upstream-URL, Nuclia-Learning-Id, X-NUCLIA-TRACE-ID' );
		header( 'Vary: Origin', false );
	}

	private function is_ephemeral_request( string $query_string ): bool {
		return (bool) preg_match( '/(^|&)eph-token=/i', $query_string );
	}

	/**
	 * @param array<string,mixed> $response Upstream response.
	 * @return array<string,string>
	 */
	private function response_headers( array $response, string $remote_url, int $status ): array {
		$headers = [];
		$skip    = [ 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'content-encoding' ];

		foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$name = (string) $name;
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
			}
			$value = str_replace( [ "\r", "\n" ], '', (string) $value );

			if ( '' === $value || ! preg_match( '/^[A-Za-z0-9-]+$/', $name ) || in_array( strtolower( $name ), $skip, true ) ) {
				continue;
			}

			$headers[ $name ] = $value;
		}

		$headers['X-Nuclia-Upstream-Status'] = (string) $status;
		$content_type = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
		if ( '' !== $content_type ) {
			$headers['X-Nuclia-Upstream-Content-Type'] = $content_type;
		}
		$headers['X-Nuclia-Upstream-URL'] = substr( str_replace( [ "\r", "\n" ], '', (string) preg_replace( '/(?<=[?&])eph-token=[^&]*/i', 'eph-token=REDACTED', $remote_url ) ), 0, 2048 );

		return $headers;
	}

	/**
	 * @return array{status: int, headers: array<string,string>, body: string}
	 */
	private function error( int $status, string $code, string $message ): array {
		return [
			'status'  => $status,
			'headers' => [ 'Content-Type' => 'application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) ],
			'body'    => (string) wp_json_encode(
				[
					'code'    => $code,
					'message' => $message,
				]
			),
		];
	}
}
