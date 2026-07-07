<?php
/**
 * Progress Agentic RAG API client.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Api;

use ProgressAgenticRag\Settings\SettingsRepository;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ApiClient {
	public function __construct( private readonly SettingsRepository $settings ) {
	}

	public function index_post( WP_Post $post ): bool|WP_Error {
		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );

		if ( '' === $endpoint || '' === $token ) {
			return new WP_Error( 'progress_agentic_rag_missing_connection', __( 'Progress Agentic RAG connection settings are incomplete.', 'progress-agentic-rag' ) );
		}

		$response = wp_remote_request(
			$endpoint . 'resources',
			[
				'method'  => 'POST',
				'headers' => [
					'Content-type'            => 'application/json',
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'body'    => wp_json_encode( $this->resource_body( $post ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 409 === $response_code ) {
			$existing = $this->find_resource_by_slug( (string) $post->ID );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}

			if ( '' === $existing['rid'] ) {
				return new WP_Error( 'progress_agentic_rag_existing_resource_missing', __( 'Progress Agentic RAG reports the resource already exists, but it could not be found by slug.', 'progress-agentic-rag' ) );
			}

			$seqid = $existing['seqid'];
			if ( 'attachment' === $post->post_type ) {
				$file_result = $this->upload_attachment_file( $endpoint, $token, $existing['rid'], $post );
				if ( is_wp_error( $file_result ) ) {
					return $file_result;
				}

				if ( '' !== $file_result ) {
					$seqid = $file_result;
				}
			}

			$this->upsert_index( (int) $post->ID, $existing['rid'], $seqid );

			return true;
		}

		if ( 201 !== $response_code ) {
			return new WP_Error( 'progress_agentic_rag_resource_failed', $this->response_error_message( $response, __( 'Progress Agentic RAG rejected the resource.', 'progress-agentic-rag' ) ) );
		}

		$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
		$rid          = is_array( $api_response ) && isset( $api_response['uuid'] ) ? sanitize_text_field( (string) $api_response['uuid'] ) : '';
		$seqid        = is_array( $api_response ) && isset( $api_response['seqid'] ) ? sanitize_text_field( (string) $api_response['seqid'] ) : '';

		if ( '' === $rid ) {
			return new WP_Error( 'progress_agentic_rag_missing_resource_id', __( 'Progress Agentic RAG did not return a resource ID.', 'progress-agentic-rag' ) );
		}

		if ( 'attachment' === $post->post_type ) {
			$file_result = $this->upload_attachment_file( $endpoint, $token, $rid, $post );
			if ( is_wp_error( $file_result ) ) {
				return $file_result;
			}

			if ( '' !== $file_result ) {
				$seqid = $file_result;
			}
		}

		$this->upsert_index( (int) $post->ID, $rid, $seqid );

		return true;
	}

	public function sync_post( WP_Post $post ): bool|WP_Error {
		$rid = $this->get_indexed_resource_id( (int) $post->ID );

		if ( '' === $rid ) {
			return $this->index_post( $post );
		}

		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );

		if ( '' === $endpoint || '' === $token ) {
			return new WP_Error( 'progress_agentic_rag_missing_connection', __( 'Progress Agentic RAG connection settings are incomplete.', 'progress-agentic-rag' ) );
		}

		$response = wp_remote_request(
			$endpoint . 'resource/' . rawurlencode( $rid ),
			[
				'method'  => 'PATCH',
				'headers' => [
					'Content-type'            => 'application/json',
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'body'    => wp_json_encode( $this->resource_body( $post ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $response_code ) {
			$this->delete_index( (int) $post->ID );
			return $this->index_post( $post );
		}

		if ( ! in_array( $response_code, [ 200, 201 ], true ) ) {
			return new WP_Error( 'progress_agentic_rag_resource_update_failed', $this->response_error_message( $response, __( 'Progress Agentic RAG rejected the resource update.', 'progress-agentic-rag' ) ) );
		}

		$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
		$seqid        = is_array( $api_response ) && isset( $api_response['seqid'] ) ? sanitize_text_field( (string) $api_response['seqid'] ) : '';

		if ( 'attachment' === $post->post_type ) {
			$file_result = $this->upload_attachment_file( $endpoint, $token, $rid, $post );
			if ( is_wp_error( $file_result ) ) {
				return $file_result;
			}

			if ( '' !== $file_result ) {
				$seqid = $file_result;
			}
		}

		$this->upsert_index( (int) $post->ID, $rid, $seqid );

		return true;
	}

	public function update_resource_labels( WP_Post $post, string $rid ): bool|WP_Error {
		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		$rid      = sanitize_text_field( $rid );

		if ( '' === $endpoint || '' === $token || '' === $rid ) {
			return new WP_Error( 'progress_agentic_rag_missing_connection', __( 'Progress Agentic RAG connection settings are incomplete.', 'progress-agentic-rag' ) );
		}

		$response = wp_remote_request(
			$endpoint . 'resource/' . rawurlencode( $rid ),
			[
				'method'  => 'PATCH',
				'headers' => [
					'Content-type'            => 'application/json',
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'body'    => wp_json_encode(
					[
						'usermetadata' => [
							'classifications' => $this->build_taxonomy_classifications( $post ),
						],
					],
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'progress_agentic_rag_label_update_failed', $this->response_error_message( $response, __( 'Progress Agentic RAG rejected the label update.', 'progress-agentic-rag' ) ) );
		}

		return true;
	}

	/**
	 * @return list<string>
	 */
	public function get_labelsets(): array {
		$cache            = $this->settings->get_labelsets_cache();
		$cached_labelsets = is_array( $cache['labelsets'] ?? null ) ? $cache['labelsets'] : [];
		$fetched_at       = (int) ( $cache['fetched_at'] ?? 0 );
		$ttl              = defined( 'HOUR_IN_SECONDS' ) ? 6 * HOUR_IN_SECONDS : 21600;

		if ( ! empty( $cached_labelsets ) && $fetched_at > 0 && ( time() - $fetched_at ) < $ttl ) {
			return $cached_labelsets;
		}

		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		if ( '' === $endpoint || '' === $token ) {
			return $cached_labelsets;
		}

		$response = wp_remote_request(
			$endpoint . 'labelsets',
			[
				'method'  => 'GET',
				'headers' => [
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $cached_labelsets;
		}

		$normalized = $this->normalize_labelsets_response( json_decode( wp_remote_retrieve_body( $response ), true ) );
		$labelsets  = $normalized['labelsets'];
		if ( empty( $labelsets ) ) {
			return $cached_labelsets;
		}

		if ( ! empty( $normalized['labels'] ) ) {
			$this->settings->set_labelsets_cache_with_labels( $labelsets, $normalized['labels'] );
		} else {
			$this->settings->set_labelsets_cache( $labelsets );
		}

		return $labelsets;
	}

	/**
	 * @return list<string>
	 */
	public function get_labelset_labels( string $labelset ): array {
		$labelset = trim( $labelset );
		if ( '' === $labelset ) {
			return [];
		}

		$cache         = $this->settings->get_labelsets_cache();
		$fetched_at    = (int) ( $cache['fetched_at'] ?? 0 );
		$ttl           = defined( 'HOUR_IN_SECONDS' ) ? 6 * HOUR_IN_SECONDS : 21600;
		$cached_labels = $this->settings->get_labelset_labels_cache( $labelset );

		if ( ! empty( $cached_labels ) && $fetched_at > 0 && ( time() - $fetched_at ) < $ttl ) {
			return $cached_labels;
		}

		$labels = $this->fetch_labelset_labels( $labelset );
		if ( ! empty( $labels ) ) {
			$this->settings->set_labelset_labels_cache( $labelset, $labels );
			return $labels;
		}

		return $cached_labels;
	}

	public function delete_resource( int $post_id, string $rid ): bool|WP_Error {
		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		$rid      = sanitize_text_field( $rid );

		if ( '' === $endpoint || '' === $token || '' === $rid ) {
			return new WP_Error( 'progress_agentic_rag_missing_connection', __( 'Progress Agentic RAG connection settings are incomplete.', 'progress-agentic-rag' ) );
		}

		$response = wp_remote_request(
			$endpoint . 'resource/' . rawurlencode( $rid ),
			[
				'method'  => 'DELETE',
				'headers' => [
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $response_code, [ 204, 404 ], true ) ) {
			return new WP_Error( 'progress_agentic_rag_delete_failed', $this->response_error_message( $response, __( 'Progress Agentic RAG rejected the resource deletion.', 'progress-agentic-rag' ) ) );
		}

		$this->delete_index( $post_id );

		return true;
	}

	/**
	 * @return list<object{post_id:string,nuclia_rid:string}>
	 */
	public function get_synced_resources(): array {
		global $wpdb;

		$table_name = $this->sync_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reads plugin-owned sync table; values are prepared and the table name is escaped.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, nuclia_rid FROM {$table_name} WHERE nuclia_rid IS NOT NULL AND nuclia_rid != %s",
				''
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return is_array( $results ) ? $results : [];
	}

	/**
	 * @param list<int|string> $post_ids
	 */
	public function recover_existing_resources( array $post_ids ): int|WP_Error {
		$slugs = [];

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 ) {
				$slugs[ (string) $post_id ] = $post_id;
			}
		}

		if ( empty( $slugs ) ) {
			return 0;
		}

		$resources = $this->find_resources_by_slugs( array_keys( $slugs ) );
		if ( is_wp_error( $resources ) ) {
			return $resources;
		}

		$recovered = 0;
		foreach ( $resources as $slug => $resource ) {
			if ( '' === $resource['rid'] ) {
				continue;
			}

			$this->upsert_index( $slugs[ $slug ], $resource['rid'], $resource['seqid'] );
			$recovered++;
		}

		return $recovered;
	}

	private function endpoint(): string {
		$zone = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid = $this->settings->get_string( SettingsRepository::OPTION_KBID );

		if ( '' === $zone || '' === $kbid || ! preg_match( '/^[a-z0-9-]+$/', $zone ) ) {
			return '';
		}

		return sprintf(
			'https://%s.rag.progress.cloud/api/v1/kb/%s/',
			rawurlencode( $zone ),
			rawurlencode( $kbid )
		);
	}

	/**
	 * @return array{rid:string,seqid:string}|WP_Error
	 */
	private function find_resource_by_slug( string $slug ): array|WP_Error {
		if ( '' === $slug ) {
			return new WP_Error( 'progress_agentic_rag_missing_connection', __( 'Progress Agentic RAG connection settings are incomplete.', 'progress-agentic-rag' ) );
		}

		$resources = $this->find_resources_by_slugs( [ $slug ] );
		if ( is_wp_error( $resources ) ) {
			return $resources;
		}

		return $resources[ $slug ] ?? [
			'rid'   => '',
			'seqid' => '',
		];
	}

	/**
	 * @param list<string> $slugs
	 *
	 * @return array<string, array{rid:string,seqid:string}>|WP_Error
	 */
	private function find_resources_by_slugs( array $slugs ): array|WP_Error {
		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		$lookup   = [];

		foreach ( $slugs as $slug ) {
			$slug = trim( (string) $slug );
			if ( '' !== $slug ) {
				$lookup[ $slug ] = true;
			}
		}

		if ( empty( $lookup ) ) {
			return [];
		}

		if ( '' === $endpoint || '' === $token ) {
			return new WP_Error( 'progress_agentic_rag_missing_connection', __( 'Progress Agentic RAG connection settings are incomplete.', 'progress-agentic-rag' ) );
		}

		$page_size = 250;
		$offset    = 0;
		$matches   = [];

		do {
			$response = wp_remote_request(
				$endpoint . 'resources?from=' . $offset . '&size=' . $page_size,
				[
					'method'  => 'GET',
					'headers' => [
						'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
					],
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return new WP_Error( 'progress_agentic_rag_existing_resource_lookup_failed', $this->response_error_message( $response, __( 'Progress Agentic RAG could not look up the existing resource.', 'progress-agentic-rag' ) ) );
			}

			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
			$resources    = is_array( $api_response['resources'] ?? null ) ? $api_response['resources'] : [];

			foreach ( $resources as $resource ) {
				$slug = is_array( $resource ) ? (string) ( $resource['slug'] ?? '' ) : '';
				if ( ! isset( $lookup[ $slug ] ) ) {
					continue;
				}

				$matches[ $slug ] = [
					'rid'   => sanitize_text_field( (string) ( $resource['id'] ?? $resource['uuid'] ?? '' ) ),
					'seqid' => sanitize_text_field( (string) ( $resource['last_seqid'] ?? $resource['seqid'] ?? '' ) ),
				];

				if ( count( $matches ) === count( $lookup ) ) {
					break 2;
				}
			}

			$offset += $page_size;
		} while ( count( $resources ) === $page_size );

		return $matches;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function resource_body( WP_Post $post ): array {
		$body = [
			'title'    => html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, 'UTF-8' ),
			'slug'     => (string) $post->ID,
			'metadata' => [
				'language' => get_bloginfo( 'language' ),
			],
			'origin'   => [
				'url' => get_permalink( $post ),
			],
			'created'  => gmdate( 'Y-m-d', strtotime( $post->post_date_gmt ) ) . 'T' . gmdate( 'H:i:s', strtotime( $post->post_date_gmt ) ) . 'Z',
		];

		$classifications = $this->build_taxonomy_classifications( $post );
		if ( ! empty( $classifications ) ) {
			$body['usermetadata'] = [
				'classifications' => $classifications,
			];
		}

		if ( 'attachment' === $post->post_type ) {
			$body['icon'] = get_post_mime_type( $post->ID );

			return $body;
		}

		$body['icon']  = 'text/html';
		$body['texts'] = [
			'text-1' => [
				'body'   => apply_filters(
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filters are applied before indexing post content.
					'the_content',
					$post->post_content
				),
				'format' => 'HTML',
			],
		];

		return $body;
	}

	/**
	 * @return list<array{labelset:string,label:string}>
	 */
	private function build_taxonomy_classifications( WP_Post $post ): array {
		$taxonomy_label_map = $this->settings->get_taxonomy_label_map();
		$classifications    = [];

		foreach ( $taxonomy_label_map as $taxonomy => $config ) {
			if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || ! is_array( $config ) ) {
				continue;
			}

			$labelset          = isset( $config['labelset'] ) ? trim( (string) $config['labelset'] ) : '';
			$term_map          = is_array( $config['terms'] ?? null ) ? $config['terms'] : [];
			$fallback          = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
			$fallback_labelset = isset( $fallback['labelset'] ) ? trim( (string) $fallback['labelset'] ) : '';
			$fallback_labels   = is_array( $fallback['labels'] ?? null ) ? $fallback['labels'] : [];
			$term_ids          = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );

			if ( is_wp_error( $term_ids ) ) {
				continue;
			}

			if ( empty( $term_ids ) ) {
				foreach ( $fallback_labels as $label ) {
					$label = trim( (string) $label );
					if ( '' !== $fallback_labelset && '' !== $label ) {
						$classifications[] = [
							'labelset' => $fallback_labelset,
							'label'    => $label,
						];
					}
				}
				continue;
			}

			if ( '' === $labelset || empty( $term_map ) ) {
				continue;
			}

			foreach ( $term_ids as $term_id ) {
				$labels = $term_map[ (int) $term_id ] ?? [];
				if ( ! is_array( $labels ) ) {
					$labels = '' !== $labels ? [ (string) $labels ] : [];
				}

				foreach ( $labels as $label ) {
					$label = trim( (string) $label );
					if ( '' === $label ) {
						continue;
					}

					$classifications[] = [
						'labelset' => $labelset,
						'label'    => $label,
					];
				}
			}
		}

		$unique = [];
		foreach ( $classifications as $classification ) {
			$unique[ $classification['labelset'] . '|' . $classification['label'] ] = $classification;
		}

		return array_values( $unique );
	}

	/**
	 * @return array{labelsets:list<string>,labels:array<string, list<string>>}
	 */
	private function normalize_labelsets_response( mixed $data ): array {
		if ( ! is_array( $data ) ) {
			return [
				'labelsets' => [],
				'labels'    => [],
			];
		}

		$labelsets  = [];
		$labels_map = [];
		$payload    = $data['labelsets'] ?? $data;

		if ( is_array( $payload ) ) {
			$is_assoc = array_keys( $payload ) !== range( 0, count( $payload ) - 1 );
			if ( $is_assoc ) {
				$labelsets = array_keys( $payload );
				foreach ( $payload as $labelset => $entry ) {
					$labels = $this->normalize_labelset_labels( $entry );
					if ( ! empty( $labels ) ) {
						$labels_map[ (string) $labelset ] = $labels;
					}
				}
			} else {
				foreach ( $payload as $entry ) {
					if ( is_string( $entry ) ) {
						$labelsets[] = $entry;
					} elseif ( is_array( $entry ) ) {
						if ( isset( $entry['labelset'] ) ) {
							$labelset_name = (string) $entry['labelset'];
							$labelsets[]   = $labelset_name;
							$labels        = $this->normalize_labelset_labels( $entry );
							if ( ! empty( $labels ) ) {
								$labels_map[ $labelset_name ] = $labels;
							}
						} elseif ( isset( $entry['name'] ) ) {
							$labelsets[] = (string) $entry['name'];
						} elseif ( isset( $entry['id'] ) ) {
							$labelsets[] = (string) $entry['id'];
						}
					}
				}
			}
		}

		$labelsets = array_filter( array_map( 'sanitize_text_field', $labelsets ) );

		return [
			'labelsets' => array_values( array_unique( $labelsets ) ),
			'labels'    => $labels_map,
		];
	}

	/**
	 * @return list<string>
	 */
	private function fetch_labelset_labels( string $labelset ): array {
		$endpoint = $this->endpoint();
		$token    = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		if ( '' === $endpoint || '' === $token ) {
			return [];
		}

		$encoded    = rawurlencode( $labelset );
		$candidates = [
			$endpoint . 'labelsets/' . $encoded,
			$endpoint . 'labelset/' . $encoded,
			$endpoint . 'labelsets/' . $encoded . '/labels',
			$endpoint . 'labelset/' . $encoded . '/labels',
		];

		foreach ( $candidates as $uri ) {
			$response = wp_remote_request(
				$uri,
				[
					'method'  => 'GET',
					'headers' => [
						'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
					],
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$labels = $this->normalize_labelset_labels( json_decode( wp_remote_retrieve_body( $response ), true ) );
			if ( ! empty( $labels ) ) {
				return $labels;
			}
		}

		return [];
	}

	/**
	 * @return list<string>
	 */
	private function normalize_labelset_labels( mixed $data ): array {
		if ( ! is_array( $data ) ) {
			return [];
		}

		$labels  = [];
		$payload = $data['labels'] ?? $data['labelset'] ?? $data;

		if ( is_array( $payload ) ) {
			$is_assoc = array_keys( $payload ) !== range( 0, count( $payload ) - 1 );
			if ( $is_assoc ) {
				$labels = array_keys( $payload );
			} else {
				foreach ( $payload as $entry ) {
					if ( is_string( $entry ) ) {
						$labels[] = $entry;
					} elseif ( is_array( $entry ) ) {
						if ( isset( $entry['title'] ) ) {
							$labels[] = (string) $entry['title'];
						} elseif ( isset( $entry['text'] ) ) {
							$labels[] = (string) $entry['text'];
						} elseif ( isset( $entry['uri'] ) ) {
							$labels[] = (string) $entry['uri'];
						} elseif ( isset( $entry['related'] ) && is_string( $entry['related'] ) ) {
							$labels[] = $entry['related'];
						} elseif ( isset( $entry['label'] ) ) {
							$labels[] = (string) $entry['label'];
						} elseif ( isset( $entry['name'] ) ) {
							$labels[] = (string) $entry['name'];
						} elseif ( isset( $entry['id'] ) ) {
							$labels[] = (string) $entry['id'];
						}
					}
				}
			}
		}

		$labels = array_filter( array_map( 'sanitize_text_field', $labels ) );

		return array_values( array_unique( $labels ) );
	}

	private function upload_attachment_file( string $endpoint, string $token, string $rid, WP_Post $post ): string|WP_Error {
		$file = get_attached_file( $post->ID );

		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}

		$response = wp_remote_request(
			$endpoint . 'resource/' . rawurlencode( $rid ) . '/file/file/upload',
			[
				'method'  => 'POST',
				'headers' => [
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
					'Content-Type'            => get_post_mime_type( $post->ID ) ?: 'application/octet-stream',
					'x-filename'              => sanitize_file_name( wp_basename( $file ) ),
					'x-md5'                   => md5_file( $file ),
				],
				'body'    => file_get_contents( $file ),
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! in_array( (int) wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ) ) {
			return new WP_Error( 'progress_agentic_rag_file_upload_failed', $this->response_error_message( $response, __( 'Progress Agentic RAG rejected the attachment file.', 'progress-agentic-rag' ) ) );
		}

		$api_response = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $api_response ) && isset( $api_response['seqid'] ) ? sanitize_text_field( (string) $api_response['seqid'] ) : '';
	}

	private function get_indexed_resource_id( int $post_id ): string {
		global $wpdb;

		$table_name = $this->sync_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reads plugin-owned sync table; values are prepared and the table name is escaped.
		$resource_id = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT nuclia_rid FROM {$table_name} WHERE post_id = %d",
				$post_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $resource_id;
	}

	private function upsert_index( int $post_id, string $rid, string $seqid ): void {
		global $wpdb;

		$table_name = $this->sync_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes plugin-owned sync table after upstream indexing succeeds.
		$wpdb->delete( $table_name, [ 'post_id' => $post_id ], [ '%d' ] );
		$wpdb->insert(
			$table_name,
			[
				'post_id'      => $post_id,
				'nuclia_rid'   => $rid,
				'nuclia_seqid' => '' !== $seqid ? $seqid : null,
			],
				[
					'%d',
					'%s',
					'%s',
				]
			);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	private function response_error_message( mixed $response, string $fallback ): string {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return $fallback;
		}

		foreach ( [ 'detail', 'message', 'error' ] as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== (string) $data[ $key ] ) {
				return $fallback . ' ' . sanitize_text_field( (string) $data[ $key ] );
			}
		}

		return $fallback;
	}

	private function delete_index( int $post_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes one row from the plugin-owned sync table.
		$wpdb->delete( $this->sync_table_name(), [ 'post_id' => $post_id ], [ '%d' ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	private function sync_table_name(): string {
		global $wpdb;

		return esc_sql( $wpdb->prefix . SettingsRepository::SYNC_TABLE_NAME );
	}
}
