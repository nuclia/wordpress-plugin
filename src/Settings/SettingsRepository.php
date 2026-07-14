<?php
/**
 * Storage keys for settings that must remain compatible with the reference plugin.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsRepository {
	public const OPTION_ZONE                 = 'nuclia_zone';
	public const OPTION_TOKEN                = 'nuclia_token';
	public const OPTION_KBID                 = 'nuclia_kbid';
	public const OPTION_ACCOUNT_ID           = 'nuclia_account_id';
	public const OPTION_API_IS_REACHABLE     = 'nuclia_api_is_reachable';
	public const OPTION_TAXONOMY_LABEL_MAP   = 'nuclia_taxonomy_label_map';
	public const OPTION_LABELSETS_CACHE      = 'nuclia_labelsets_cache';
	public const OPTION_INDEXABLE_POST_TYPES = 'nuclia_indexable_post_types';
	public const OPTION_MANUAL_SYNC_STATE     = 'progress_agentic_rag_manual_sync_state';
	public const OPTION_DELETE_SYNC_STATE     = 'progress_agentic_rag_delete_sync_state';
	public const OPTION_BACKGROUND_SYNC_STATE = 'progress_agentic_rag_background_sync_state';
	public const OPTION_SYNC_HISTORY          = 'progress_agentic_rag_sync_history';
	public const OPTION_FAILED_SYNC_ITEMS     = 'progress_agentic_rag_failed_sync_items';
	public const OPTION_WIDGET_APPEARANCE     = 'progress_agentic_rag_widget_appearance';
	public const SYNC_TABLE_NAME             = 'agentic_rag_for_wp';

	/**
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			self::OPTION_ZONE                 => '',
			self::OPTION_TOKEN                => '',
			self::OPTION_KBID                 => '',
			self::OPTION_ACCOUNT_ID           => '',
			self::OPTION_API_IS_REACHABLE     => 'no',
			self::OPTION_TAXONOMY_LABEL_MAP   => [],
			self::OPTION_LABELSETS_CACHE      => [
				'fetched_at' => 0,
				'labelsets'  => [],
				'labels'     => [],
			],
			self::OPTION_INDEXABLE_POST_TYPES => [
				'post' => 1,
				'page' => 1,
			],
			self::OPTION_MANUAL_SYNC_STATE     => [],
			self::OPTION_DELETE_SYNC_STATE     => [],
			self::OPTION_BACKGROUND_SYNC_STATE => [],
			self::OPTION_SYNC_HISTORY          => [],
			self::OPTION_FAILED_SYNC_ITEMS     => [],
			self::OPTION_WIDGET_APPEARANCE     => self::widget_appearance_defaults(),
		];
	}

	/**
	 * @return array<string, string|int|float>
	 */
	public static function widget_appearance_defaults(): array {
		return [
			'accent_color'  => '#054bff',
			'text_color'    => '#000000',
			'muted_color'   => '#707070',
			'surface_color' => '#ffffff',
			'border_color'  => '#e6e6e6',
			'font_family'   => 'roboto',
			'font_size'     => 16,
			'line_height'   => 1.5,
			'border_radius' => 8,
			'card_padding'  => 24,
			'shadow'        => 'soft',
		];
	}

	public function add_defaults(): void {
		foreach ( $this->defaults() as $option_name => $default_value ) {
			add_option( $option_name, $default_value );
		}
	}

	public function get_string( string $option_name ): string {
		$defaults = $this->defaults();
		$value    = (string) get_option( $option_name, $defaults[ $option_name ] ?? '' );

		if ( self::OPTION_ZONE === $option_name ) {
			return self::normalize_zone( $value );
		}

		if ( self::OPTION_KBID === $option_name ) {
			return self::normalize_kbid( $value );
		}

		if ( self::OPTION_TOKEN === $option_name ) {
			return self::normalize_token( $value );
		}

		return $value;
	}

	public function has_token(): bool {
		return '' !== $this->get_string( self::OPTION_TOKEN );
	}

	public function get_api_is_reachable(): bool {
		return 'yes' === get_option( self::OPTION_API_IS_REACHABLE, 'no' ) && '' !== $this->get_string( self::OPTION_ZONE ) && '' !== $this->get_string( self::OPTION_KBID ) && $this->has_token();
	}

	public function set_api_is_reachable( bool $flag ): void {
		update_option( self::OPTION_API_IS_REACHABLE, $flag ? 'yes' : 'no' );
	}

	/**
	 * @return array<string, int>
	 */
	public function get_indexable_post_types(): array {
		$value = get_option( self::OPTION_INDEXABLE_POST_TYPES, $this->defaults()[ self::OPTION_INDEXABLE_POST_TYPES ] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_taxonomy_label_map(): array {
		$value = get_option( self::OPTION_TAXONOMY_LABEL_MAP, $this->defaults()[ self::OPTION_TAXONOMY_LABEL_MAP ] );

		return is_array( $value ) ? $this->sanitize_taxonomy_label_map( $value ) : [];
	}

	/**
	 * @return array<string, string|int|float>
	 */
	public function get_widget_appearance(): array {
		$value = get_option( self::OPTION_WIDGET_APPEARANCE, self::widget_appearance_defaults() );

		return $this->sanitize_widget_appearance( is_array( $value ) ? $value : [] );
	}

	/**
	 * @param array<string, mixed> $value Raw widget appearance form data.
	 */
	public function update_widget_appearance( array $value ): void {
		update_option( self::OPTION_WIDGET_APPEARANCE, $this->sanitize_widget_appearance( $value ) );
	}

	/**
	 * @param array<string, mixed> $value Raw widget appearance form data.
	 *
	 * @return array<string, string|int|float>
	 */
	public function sanitize_widget_appearance( array $value ): array {
		$defaults = self::widget_appearance_defaults();
		$fonts    = [ 'roboto', 'inter', 'system' ];
		$shadows  = [ 'none', 'subtle', 'soft' ];

		foreach ( [ 'accent_color', 'text_color', 'muted_color', 'surface_color', 'border_color' ] as $key ) {
			$color = sanitize_hex_color( (string) ( $value[ $key ] ?? '' ) );
			$defaults[ $key ] = is_string( $color ) ? strtolower( $color ) : $defaults[ $key ];
		}

		$font_family = sanitize_key( (string) ( $value['font_family'] ?? '' ) );
		$shadow      = sanitize_key( (string) ( $value['shadow'] ?? '' ) );

		$defaults['font_family']   = in_array( $font_family, $fonts, true ) ? $font_family : $defaults['font_family'];
		$defaults['font_size']     = $this->bounded_int( $value['font_size'] ?? null, (int) $defaults['font_size'], 12, 24 );
		$defaults['line_height']   = $this->bounded_float( $value['line_height'] ?? null, (float) $defaults['line_height'], 1.2, 2 );
		$defaults['border_radius'] = $this->bounded_int( $value['border_radius'] ?? null, (int) $defaults['border_radius'], 0, 24 );
		$defaults['card_padding']  = $this->bounded_int( $value['card_padding'] ?? null, (int) $defaults['card_padding'], 8, 48 );
		$defaults['shadow']        = in_array( $shadow, $shadows, true ) ? $shadow : $defaults['shadow'];

		return $defaults;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_labelsets_cache(): array {
		$value = get_option( self::OPTION_LABELSETS_CACHE, $this->defaults()[ self::OPTION_LABELSETS_CACHE ] );

		return is_array( $value ) ? $value : $this->defaults()[ self::OPTION_LABELSETS_CACHE ];
	}

	/**
	 * @param list<string> $labelsets Labelset names.
	 */
	public function set_labelsets_cache( array $labelsets ): void {
		$cache  = $this->get_labelsets_cache();
		$labels = is_array( $cache['labels'] ?? null ) ? $cache['labels'] : [];

		update_option(
			self::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => time(),
				'labelsets'  => array_values( $labelsets ),
				'labels'     => $labels,
			]
		);
	}

	/**
	 * @param list<string>                 $labelsets Labelset names.
	 * @param array<string, list<string>>  $labels_map Labels keyed by labelset.
	 */
	public function set_labelsets_cache_with_labels( array $labelsets, array $labels_map ): void {
		$labels = [];
		foreach ( $labels_map as $labelset => $label_list ) {
			if ( is_string( $labelset ) && is_array( $label_list ) ) {
				$labels[ $labelset ] = array_values( $label_list );
			}
		}

		update_option(
			self::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => time(),
				'labelsets'  => array_values( $labelsets ),
				'labels'     => $labels,
			]
		);
	}

	/**
	 * @return list<string>
	 */
	public function get_labelset_labels_cache( string $labelset ): array {
		$cache  = $this->get_labelsets_cache();
		$labels = is_array( $cache['labels'] ?? null ) ? $cache['labels'] : [];

		return is_array( $labels[ $labelset ] ?? null ) ? $labels[ $labelset ] : [];
	}

	/**
	 * @param list<string> $labels Labels for the labelset.
	 */
	public function set_labelset_labels_cache( string $labelset, array $labels ): void {
		$cache         = $this->get_labelsets_cache();
		$labelsets     = is_array( $cache['labelsets'] ?? null ) ? $cache['labelsets'] : [];
		$stored_labels = is_array( $cache['labels'] ?? null ) ? $cache['labels'] : [];

		$stored_labels[ $labelset ] = array_values( $labels );

		update_option(
			self::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => time(),
				'labelsets'  => $labelsets,
				'labels'     => $stored_labels,
			]
		);
	}

	/**
	 * @param array<string, mixed> $value Raw taxonomy mapping form data.
	 */
	public function update_taxonomy_label_map( array $value ): void {
		update_option( self::OPTION_TAXONOMY_LABEL_MAP, $this->sanitize_taxonomy_label_map( $value ) );
	}

	/**
	 * @param array<string, mixed> $value Raw taxonomy mapping form data.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_taxonomy_label_map( array $value ): array {
		$sanitized = [];

		foreach ( $value as $taxonomy => $config ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $config ) ) {
				continue;
			}

			$labelset              = isset( $config['labelset'] ) ? sanitize_text_field( (string) $config['labelset'] ) : '';
			$terms                 = is_array( $config['terms'] ?? null ) ? $config['terms'] : [];
			$fallback              = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
			$fallback_labelset     = isset( $fallback['labelset'] ) ? sanitize_text_field( (string) $fallback['labelset'] ) : '';
			$fallback_labels       = $fallback['labels'] ?? [];
			$clean_terms           = [];
			$clean_fallback_labels = [];

			if ( ! is_array( $fallback_labels ) ) {
				$fallback_labels = '' !== $fallback_labels ? [ (string) $fallback_labels ] : [];
			}

			foreach ( $terms as $term_id => $labels ) {
				$term_id = (int) $term_id;
				if ( $term_id <= 0 || ! term_exists( $term_id, $taxonomy ) ) {
					continue;
				}

				if ( ! is_array( $labels ) ) {
					$labels = '' !== $labels ? [ (string) $labels ] : [];
				}

				$clean_labels = [];
				foreach ( $labels as $label ) {
					$label = sanitize_text_field( (string) $label );
					if ( '' !== $label ) {
						$clean_labels[] = $label;
					}
				}

				$clean_labels = array_values( array_unique( $clean_labels ) );
				if ( ! empty( $clean_labels ) ) {
					$clean_terms[ $term_id ] = $clean_labels;
				}
			}

			foreach ( $fallback_labels as $label ) {
				$label = sanitize_text_field( (string) $label );
				if ( '' !== $label ) {
					$clean_fallback_labels[] = $label;
				}
			}

			$clean_fallback_labels = array_values( array_unique( $clean_fallback_labels ) );
			$has_term_mapping      = '' !== $labelset && ! empty( $clean_terms );
			$has_fallback          = '' !== $fallback_labelset && ! empty( $clean_fallback_labels );

			if ( $has_term_mapping || $has_fallback ) {
				$sanitized[ $taxonomy ] = [];
				if ( $has_term_mapping ) {
					$sanitized[ $taxonomy ]['labelset'] = $labelset;
					$sanitized[ $taxonomy ]['terms']    = $clean_terms;
				}
				if ( $has_fallback ) {
					$sanitized[ $taxonomy ]['fallback'] = [
						'labelset' => $fallback_labelset,
						'labels'   => $clean_fallback_labels,
					];
				}
			}
		}

		return $sanitized;
	}

	public function clear_labels(): void {
		update_option( self::OPTION_TAXONOMY_LABEL_MAP, $this->defaults()[ self::OPTION_TAXONOMY_LABEL_MAP ] );
		update_option( self::OPTION_LABELSETS_CACHE, $this->defaults()[ self::OPTION_LABELSETS_CACHE ] );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get_sync_history(): array {
		$value = get_option( self::OPTION_SYNC_HISTORY, [] );
		if ( ! is_array( $value ) ) {
			return [];
		}

		$history = [];
		foreach ( $value as $entry ) {
			if ( is_array( $entry ) ) {
				$history[] = $this->sanitize_sync_history_entry( $entry );
			}
		}

		return array_slice( $history, 0, 10 );
	}

	/**
	 * @param array<string, mixed> $entry Sync history entry.
	 */
	public function add_sync_history_entry( array $entry ): void {
		$history   = $this->get_sync_history();
		$history[] = $this->sanitize_sync_history_entry( $entry );

		usort(
			$history,
			static fn ( array $a, array $b ): int => (int) ( $b['finished_at'] ?? 0 ) <=> (int) ( $a['finished_at'] ?? 0 )
		);

		update_option( self::OPTION_SYNC_HISTORY, array_slice( $history, 0, 10 ) );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_failed_sync_items(): array {
		$value = get_option( self::OPTION_FAILED_SYNC_ITEMS, [] );
		if ( ! is_array( $value ) ) {
			return [];
		}

		$items = [];
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean = $this->sanitize_failed_sync_item( $item );
			if ( empty( $clean ) ) {
				continue;
			}

			$items[ $clean['post_id'] . '|' . $clean['post_type'] ] = $clean;
		}

		return $items;
	}

	public function add_failed_sync_item( int $post_id, string $post_type, string $label, string $message, string $source ): void {
		$item = $this->sanitize_failed_sync_item(
			[
				'post_id'   => $post_id,
				'post_type' => $post_type,
				'label'     => $label,
				'message'   => $message,
				'source'    => $source,
				'failed_at' => time(),
			]
		);

		if ( empty( $item ) ) {
			return;
		}

		$items = $this->get_failed_sync_items();
		$items[ $item['post_id'] . '|' . $item['post_type'] ] = $item;

		update_option( self::OPTION_FAILED_SYNC_ITEMS, array_values( $items ) );
	}

	public function remove_failed_sync_item( int $post_id, string $post_type ): void {
		$items = $this->get_failed_sync_items();
		unset( $items[ $post_id . '|' . sanitize_key( $post_type ) ] );

		update_option( self::OPTION_FAILED_SYNC_ITEMS, array_values( $items ) );
	}

	public function clear_failed_sync_items(): void {
		update_option( self::OPTION_FAILED_SYNC_ITEMS, [] );
	}

	public function update_connection_settings( string $zone, string $kbid, string $account_id, string $token ): void {
		update_option( self::OPTION_ZONE, self::normalize_zone( $zone ) );
		update_option( self::OPTION_KBID, self::normalize_kbid( $kbid ) );
		update_option( self::OPTION_ACCOUNT_ID, $account_id );

		if ( '' !== $token ) {
			update_option( self::OPTION_TOKEN, self::normalize_token( $token ) );
		}
	}

	private static function normalize_zone( string $zone ): string {
		$zone = strtolower( trim( $zone ) );
		$host = (string) wp_parse_url( $zone, PHP_URL_HOST );

		if ( '' === $host ) {
			$host = (string) strtok( $zone, '/?#' );
		}

		$host = (string) preg_replace( '/:\d+$/', '', $host );
		if ( str_ends_with( $host, '.rag.progress.cloud' ) ) {
			$host = substr( $host, 0, -strlen( '.rag.progress.cloud' ) );
		}

		return $host;
	}

	private static function normalize_kbid( string $kbid ): string {
		$kbid = trim( $kbid );

		if ( 1 === preg_match( '~/kb/([^/?#]+)~', $kbid, $matches ) ) {
			return $matches[1];
		}

		return trim( $kbid, " \t\n\r\0\x0B/" );
	}

	private static function normalize_token( string $token ): string {
		$token = trim( $token );

		if ( 1 === preg_match( '/^Bearer\s+/i', $token ) ) {
			$token = trim( (string) preg_replace( '/^Bearer\s+/i', '', $token, 1 ) );
		}

		return $token;
	}

	/**
	 * @param list<string> $post_types Post type names selected for indexing.
	 */
	public function update_indexable_post_types( array $post_types ): void {
		update_option( self::OPTION_INDEXABLE_POST_TYPES, array_fill_keys( $post_types, 1 ) );
	}

	/**
	 * @return list<string>
	 */
	public function option_names(): array {
		return array_keys( $this->defaults() );
	}

	private function bounded_int( mixed $value, int $default, int $minimum, int $maximum ): int {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		return min( $maximum, max( $minimum, (int) $value ) );
	}

	private function bounded_float( mixed $value, float $default, float $minimum, float $maximum ): float {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		return min( $maximum, max( $minimum, (float) $value ) );
	}

	/**
	 * @param array<string, mixed> $entry Sync history entry.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_sync_history_entry( array $entry ): array {
		return [
			'id'          => sanitize_text_field( (string) ( $entry['id'] ?? '' ) ),
			'type'        => sanitize_key( (string) ( $entry['type'] ?? 'sync' ) ),
			'status'      => sanitize_key( (string) ( $entry['status'] ?? 'complete' ) ),
			'total'       => max( 0, (int) ( $entry['total'] ?? 0 ) ),
			'completed'   => max( 0, (int) ( $entry['completed'] ?? 0 ) ),
			'failed'      => max( 0, (int) ( $entry['failed'] ?? 0 ) ),
			'message'     => sanitize_text_field( (string) ( $entry['message'] ?? '' ) ),
			'current'     => sanitize_text_field( (string) ( $entry['current'] ?? '' ) ),
			'started_at'  => max( 0, (int) ( $entry['started_at'] ?? 0 ) ),
			'finished_at' => max( 0, (int) ( $entry['finished_at'] ?? time() ) ),
		];
	}

	/**
	 * @param array<string, mixed> $item Failed sync item.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_failed_sync_item( array $item ): array {
		$post_id   = max( 0, (int) ( $item['post_id'] ?? 0 ) );
		$post_type = sanitize_key( (string) ( $item['post_type'] ?? '' ) );

		if ( $post_id <= 0 || '' === $post_type ) {
			return [];
		}

		return [
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'label'     => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
			'message'   => sanitize_text_field( (string) ( $item['message'] ?? '' ) ),
			'source'    => sanitize_key( (string) ( $item['source'] ?? 'sync' ) ),
			'failed_at' => max( 0, (int) ( $item['failed_at'] ?? time() ) ),
		];
	}
}
