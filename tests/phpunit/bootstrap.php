<?php
/**
 * PHPUnit bootstrap for isolated plugin unit tests.
 *
 * @package ProgressAgenticRag\Tests
 */

define( 'PROGRESS_AGENTIC_RAG_TESTS', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'PROGRESS_AGENTIC_RAG_VERSION' ) ) {
	define( 'PROGRESS_AGENTIC_RAG_VERSION', '0.1.0' );
	define( 'PROGRESS_AGENTIC_RAG_MIN_PHP_VERSION', '8.1' );
	define( 'PROGRESS_AGENTIC_RAG_MIN_WP_VERSION', '6.8' );
	define( 'PROGRESS_AGENTIC_RAG_FILE', dirname( __DIR__, 2 ) . '/progress-agentic-rag.php' );
	define( 'PROGRESS_AGENTIC_RAG_PATH', dirname( __DIR__, 2 ) . '/' );
	define( 'PROGRESS_AGENTIC_RAG_URL', 'https://example.test/wp-content/plugins/progress-agentic-rag/' );
	define( 'PROGRESS_AGENTIC_RAG_BASENAME', 'progress-agentic-rag/progress-agentic-rag.php' );
}

require_once dirname( __DIR__, 2 ) . '/src/Autoloader.php';

ProgressAgenticRag\Autoloader::register();

$GLOBALS['wp_version'] = '6.8';
$GLOBALS['progress_agentic_rag_test_options'] = [];
$GLOBALS['progress_agentic_rag_test_http_requests'] = [];
$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
$GLOBALS['progress_agentic_rag_test_http_post_responses'] = [];
$GLOBALS['progress_agentic_rag_test_post_terms'] = [];
$GLOBALS['progress_agentic_rag_test_taxonomies'] = [];
$GLOBALS['progress_agentic_rag_test_terms'] = [];
$GLOBALS['progress_agentic_rag_test_posts'] = [];
$GLOBALS['progress_agentic_rag_test_post_type_objects'] = [];
$GLOBALS['progress_agentic_rag_test_attached_files'] = [];
$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
$GLOBALS['progress_agentic_rag_test_actions'] = [];
$GLOBALS['progress_agentic_rag_test_filters'] = [];
$GLOBALS['progress_agentic_rag_test_rewrite_rules'] = [];
$GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] = [];
$GLOBALS['progress_agentic_rag_test_enqueued_styles'] = [];
$GLOBALS['progress_agentic_rag_test_enqueued_scripts'] = [];
$GLOBALS['progress_agentic_rag_test_registered_scripts'] = [];
$GLOBALS['progress_agentic_rag_test_registered_blocks'] = [];
$GLOBALS['progress_agentic_rag_test_shortcodes'] = [];
$GLOBALS['progress_agentic_rag_test_localized_scripts'] = [];
$GLOBALS['progress_agentic_rag_test_menu_pages'] = [];
$GLOBALS['progress_agentic_rag_test_safe_redirects'] = [];
$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;
$GLOBALS['progress_agentic_rag_test_is_admin'] = false;
$GLOBALS['progress_agentic_rag_test_is_front_page'] = true;
$GLOBALS['progress_agentic_rag_test_query_vars'] = [];
$GLOBALS['progress_agentic_rag_test_deleted_options'] = [];
$GLOBALS['progress_agentic_rag_test_db_delta'] = [];

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private readonly string $code = '', private readonly string $message = '' ) {
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'ProgressAgenticRagTestJsonResponse' ) ) {
	class ProgressAgenticRagTestJsonResponse extends RuntimeException {
		public function __construct(
			public readonly bool $success,
			public readonly mixed $data,
			public readonly int $status
		) {
			parent::__construct( 'JSON response' );
		}
	}
}

if ( ! class_exists( 'ProgressAgenticRagTestRedirect' ) ) {
	class ProgressAgenticRagTestRedirect extends RuntimeException {
		public function __construct( public readonly string $location ) {
			parent::__construct( 'Redirect' );
		}
	}
}

if ( ! class_exists( 'ProgressAgenticRagTestWpDie' ) ) {
	class ProgressAgenticRagTestWpDie extends RuntimeException {
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
		public array $queries = [];
		public array $results = [];
		public mixed $var = '';

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

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4';
		}

		public function query( string $query ): bool {
			$this->queries[] = $query;
			return true;
		}
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $option_name, mixed $value ): bool {
		if ( array_key_exists( $option_name, $GLOBALS['progress_agentic_rag_test_options'] ) ) {
			return false;
		}

		$GLOBALS['progress_agentic_rag_test_options'][ $option_name ] = $value;
		return true;
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

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option_name ): bool {
		$GLOBALS['progress_agentic_rag_test_deleted_options'][] = $option_name;
		unset( $GLOBALS['progress_agentic_rag_test_options'][ $option_name ] );
		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ): mixed {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text, string $domain = 'default' ): void {
		echo esc_attr( $text );
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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( mixed $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( mixed $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( mixed $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
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

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( string $color ): ?string {
		return 1 === preg_match( '/^#(?:[a-fA-F0-9]{3}){1,2}$/', $color ) ? $color : null;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		return trim( strtolower( preg_replace( '/[^a-zA-Z0-9-]+/', '-', $title ) ?? '' ), '-' );
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( array|string $data ): array|string {
		return $data;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $value ): string {
		return strip_tags( $value );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return (bool) $GLOBALS['progress_agentic_rag_test_current_user_can'];
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( string $message = '' ): never {
		throw new ProgressAgenticRagTestWpDie( $message );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action = '' ): bool {
		return true;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( string $action = '' ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( mixed $data = null, int $status_code = 200 ): never {
		throw new ProgressAgenticRagTestJsonResponse( true, $data, $status_code );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( mixed $data = null, int $status_code = 400 ): never {
		throw new ProgressAgenticRagTestJsonResponse( false, $data, $status_code );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, mixed $callback = '', string $icon_url = '' ): string {
		$hook = 'toplevel_page_' . $menu_slug;
		$GLOBALS['progress_agentic_rag_test_menu_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'hook' );
		return $hook;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false ): void {
		$GLOBALS['progress_agentic_rag_test_enqueued_styles'][] = compact( 'handle', 'src', 'deps', 'ver' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool|array $args = false ): void {
		$GLOBALS['progress_agentic_rag_test_enqueued_scripts'][] = compact( 'handle', 'src', 'deps', 'ver', 'args' );
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool|array $args = false ): bool {
		$GLOBALS['progress_agentic_rag_test_registered_scripts'][] = compact( 'handle', 'src', 'deps', 'ver', 'args' );
		return true;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		$GLOBALS['progress_agentic_rag_test_localized_scripts'][] = compact( 'handle', 'object_name', 'l10n' );
		return true;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( string $tag, mixed $callback ): void {
		$GLOBALS['progress_agentic_rag_test_shortcodes'][ $tag ] = $callback;
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	function register_block_type( string $block_type, array $args = [] ): string {
		$GLOBALS['progress_agentic_rag_test_registered_blocks'][ $block_type ] = $args;
		return $block_type;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.test/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action = '' ): string {
		return 'nonce-' . $action;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '' ): void {
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separator . http_build_query( $args );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location ): never {
		$GLOBALS['progress_agentic_rag_test_safe_redirects'][] = $location;
		throw new ProgressAgenticRagTestRedirect( $location );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( mixed $selected, mixed $current = true, bool $display = true ): string {
		$result = $selected === $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
		$result = $checked === $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'disabled' ) ) {
	function disabled( mixed $disabled, mixed $current = true, bool $display = true ): string {
		$result = $disabled === $current ? ' disabled="disabled"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return (bool) $GLOBALS['progress_agentic_rag_test_is_admin'];
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool {
		return (bool) $GLOBALS['progress_agentic_rag_test_is_front_page'];
	}
}

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( string $regex, string $query, string $after = 'bottom' ): void {
		$GLOBALS['progress_agentic_rag_test_rewrite_rules'][] = compact( 'regex', 'query', 'after' );
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( bool $hard = true ): void {
		$GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'][] = $hard;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( string $query_var, mixed $default_value = '' ): mixed {
		return $GLOBALS['progress_agentic_rag_test_query_vars'][ $query_var ] ?? $default_value;
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( array $args = [], string $output = 'names' ): array {
		if ( 'objects' === $output ) {
			$objects = [];
			foreach ( $GLOBALS['progress_agentic_rag_test_taxonomies'] as $name => $taxonomy ) {
				$objects[ $name ] = is_object( $taxonomy ) ? $taxonomy : (object) [
					'name'   => $name,
					'labels' => (object) [ 'name' => ucfirst( str_replace( '_', ' ', $name ) ) ],
				];
			}
			return $objects;
		}

		return array_keys( $GLOBALS['progress_agentic_rag_test_taxonomies'] );
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = [] ): array|WP_Error {
		$taxonomy = (string) ( $args['taxonomy'] ?? '' );
		$terms    = $GLOBALS['progress_agentic_rag_test_terms'][ $taxonomy ] ?? [];
		$result   = [];

		foreach ( $terms as $term_id => $term ) {
			if ( is_object( $term ) ) {
				$result[] = $term;
				continue;
			}

			$id       = is_int( $term_id ) ? $term_id : (int) $term;
			$result[] = (object) [
				'term_id' => $id,
				'name'    => 'Term ' . $id,
			];
		}

		return $result;
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

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['progress_agentic_rag_test_filters'][] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
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

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = [] ): mixed {
		$GLOBALS['progress_agentic_rag_test_http_requests'][] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( ! empty( $GLOBALS['progress_agentic_rag_test_http_post_responses'] ) ) {
			return array_shift( $GLOBALS['progress_agentic_rag_test_http_post_responses'] );
		}

		return [
			'response' => [
				'code' => 422,
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

if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
	function wp_remote_retrieve_headers( mixed $response ): array {
		return is_array( $response ) && is_array( $response['headers'] ?? null ) ? $response['headers'] : [];
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return '00000000-0000-4000-8000-' . str_pad( (string) ( count( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] ) + 1 ), 12, '0', STR_PAD_LEFT );
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( string $sql ): array {
		$GLOBALS['progress_agentic_rag_test_db_delta'][] = $sql;
		return [ $sql ];
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
				static fn ( array $action ): bool => $hook !== $action['hook'] || $group !== $action['group'] || ( null !== $args && $args !== $action['args'] )
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
