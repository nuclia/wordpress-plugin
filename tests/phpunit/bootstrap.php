<?php
/**
 * PHPUnit bootstrap for isolated plugin unit tests.
 *
 * @package ProgressAgenticRag\Tests
 */

define( 'PROGRESS_AGENTIC_RAG_TESTS', true );

require_once dirname( __DIR__, 2 ) . '/src/Autoloader.php';

ProgressAgenticRag\Autoloader::register();

$GLOBALS['progress_agentic_rag_test_options'] = [];
$GLOBALS['progress_agentic_rag_test_http_requests'] = [];
$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
$GLOBALS['progress_agentic_rag_test_post_terms'] = [];
$GLOBALS['progress_agentic_rag_test_taxonomies'] = [];
$GLOBALS['progress_agentic_rag_test_terms'] = [];
$GLOBALS['progress_agentic_rag_test_posts'] = [];
$GLOBALS['progress_agentic_rag_test_post_type_objects'] = [];
$GLOBALS['progress_agentic_rag_test_attached_files'] = [];
$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
$GLOBALS['progress_agentic_rag_test_actions'] = [];

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private readonly string $code = '', private readonly string $message = '' ) {
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_title = '';
		public string $post_type = 'post';
		public string $post_status = 'publish';
		public string $post_password = '';
		public string $post_content = '';
		public string $post_date_gmt = '2026-06-17 00:00:00';

		/**
		 * @param array<string, mixed> $values
		 */
		public function __construct( array $values = [] ) {
			foreach ( $values as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'ProgressAgenticRagTestWpdb' ) ) {
	class ProgressAgenticRagTestWpdb {
		public string $prefix = 'wp_';
		public string $posts = 'wp_posts';
		public array $deleted = [];
		public array $inserted = [];
		public array $results = [];
		public mixed $var = 0;

		public function prepare( string $query, mixed ...$args ): string {
			return vsprintf( str_replace( [ '%s', '%d' ], [ "'%s'", '%d' ], $query ), $args );
		}

		public function delete( string $table, array $where, array $formats = [] ): bool {
			$this->deleted[] = compact( 'table', 'where', 'formats' );
			return true;
		}

		public function insert( string $table, array $data, array $formats = [] ): bool {
			$this->inserted[] = compact( 'table', 'data', 'formats' );
			return true;
		}

		public function get_results( string $query ): array {
			return $this->results;
		}

		public function get_var( string $query ): mixed {
			return $this->var;
		}
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option_name, mixed $default = false ): mixed {
		return $GLOBALS['progress_agentic_rag_test_options'][ $option_name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option_name, mixed $value ): bool {
		$GLOBALS['progress_agentic_rag_test_options'][ $option_name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( wp_strip_all_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $value ): string {
		return strip_tags( $value );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		return 'en-US';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( WP_Post|int $post ): string {
		$post_id = $post instanceof WP_Post ? $post->ID : $post;
		return 'https://example.test/?p=' . $post_id;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, mixed $value ): mixed {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['progress_agentic_rag_test_actions'][] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool {
		return ! empty( $GLOBALS['progress_agentic_rag_test_taxonomies'][ $taxonomy ] );
	}
}

if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( int|string $term, string $taxonomy = '' ): array|int|string|null {
		$term_id = (int) $term;
		if ( $term_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$terms = $GLOBALS['progress_agentic_rag_test_terms'][ $taxonomy ] ?? [];
		if ( empty( $terms ) || ! empty( $terms[ $term_id ] ) || in_array( $term_id, $terms, true ) ) {
			return [
				'term_id'          => $term_id,
				'term_taxonomy_id' => $term_id,
			];
		}

		return null;
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = [] ): array {
		return $GLOBALS['progress_agentic_rag_test_post_terms'][ $post_id ][ $taxonomy ] ?? [];
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( int $post_id ): string {
		return 'text/plain';
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( int $post_id ): string {
		return $GLOBALS['progress_agentic_rag_test_attached_files'][ $post_id ] ?? '';
	}
}

if ( ! function_exists( 'wp_basename' ) ) {
	function wp_basename( string $path, string $suffix = '' ): string {
		return basename( $path, $suffix );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $filename ): string {
		return preg_replace( '/[^A-Za-z0-9._-]/', '-', $filename ) ?? '';
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( string $url, array $args = [] ): mixed {
		$GLOBALS['progress_agentic_rag_test_http_requests'][] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( ! empty( $GLOBALS['progress_agentic_rag_test_http_responses'] ) ) {
			return array_shift( $GLOBALS['progress_agentic_rag_test_http_responses'] );
		}

		return [
			'response' => [
				'code' => 200,
			],
			'body'     => '',
		];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( mixed $response ): int {
		return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( mixed $response ): string {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return '00000000-0000-4000-8000-' . str_pad( (string) ( count( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] ) + 1 ), 12, '0', STR_PAD_LEFT );
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( int $timestamp, string $hook, array $args = [], string $group = '' ): int {
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][] = [
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
			'group'     => $group,
			'status'    => 'pending',
		];

		return count( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
	}
}

if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
	function as_get_scheduled_actions( array $args = [], string $return_format = 'objects' ): array {
		$matches = [];

		foreach ( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] as $index => $action ) {
			if ( isset( $args['hook'] ) && $args['hook'] !== $action['hook'] ) {
				continue;
			}

			if ( isset( $args['group'] ) && $args['group'] !== $action['group'] ) {
				continue;
			}

			if ( isset( $args['status'] ) && $args['status'] !== $action['status'] ) {
				continue;
			}

			$matches[] = 'ids' === $return_format ? $index + 1 : (object) $action;
		}

		return $matches;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	function as_unschedule_all_actions( string $hook, mixed $args = null, string $group = '' ): void {
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = array_values(
			array_filter(
				$GLOBALS['progress_agentic_rag_test_scheduled_actions'],
				static fn ( array $action ): bool => $hook !== $action['hook'] || $group !== $action['group']
			)
		);
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $post_id ): mixed {
		return $GLOBALS['progress_agentic_rag_test_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( WP_Post|int $post ): string {
		$post = $post instanceof WP_Post ? $post : get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( array $args = [], string $output = 'names' ): array {
		if ( 'objects' === $output ) {
			return $GLOBALS['progress_agentic_rag_test_post_type_objects'];
		}

		return array_keys( $GLOBALS['progress_agentic_rag_test_post_type_objects'] );
	}
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( string $post_type ): ?object {
		return $GLOBALS['progress_agentic_rag_test_post_type_objects'][ $post_type ] ?? null;
	}
}
