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
		return 'yes' === get_option( self::OPTION_API_IS_REACHABLE, 'no' );
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
}
