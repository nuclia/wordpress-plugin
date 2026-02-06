<?php
/**
 * Nuclia_API class file.
 *
 * @since   1.0.0
 *
 */

/**
 * Class Nuclia_API
 *
 * @since 1.0.0
 */
class Nuclia_API {

	/**
	 * The Nuclia_Settings instance.
	 *
	 * @since  1.0.0
	 *
	 * @var Nuclia_Settings
	 */
	private Nuclia_Settings $settings;

	/**
	 * The NucliaDB API end point
	 *
	 * @since  1.0.0
	 *
	 * @var string
	 */
	private string $endpoint;

	/**
	 * Nuclia_API constructor.
	 *
	 * @since  1.0.0
	 *
	 * @param Nuclia_Settings $settings The Nuclia_Settings instance.
	 */
	public function __construct( Nuclia_Settings $settings ) {
		$this->settings = $settings;
		$this->endpoint = sprintf( 'https://%1s.rag.progress.cloud/api/v1/kb/%2s/',
							$this->settings->get_zone(),
							$this->settings->get_kbid()
						  );
	}
	
	public function upsert_index( int $post_id, string $rid, string|null $seqid ): void {
		global $wpdb;
		$wpdb->replace( "{$wpdb->prefix}agentic_rag_for_wp", [
			'post_id' => $post_id,
			'nuclia_rid' => $rid,
			'nuclia_seqid' => $seqid ?? null
		] );
	}
	
	public function get_rid( int $post_id ): string | null {
		global $wpdb;
		$rid = $wpdb->get_var( $wpdb->prepare( "SELECT nuclia_rid FROM {$wpdb->prefix}agentic_rag_for_wp WHERE post_id = %d", $post_id ) );
		return $rid;
	}

	public function delete_index( int $post_id ): void {
		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}agentic_rag_for_wp", [ 'post_id' => $post_id ] );
	}

	/**
	 * Prepare NucliaDB resource body
	 * @param WP_Post $post
	 * @return bool|string
	 */
	public function prepare_nuclia_resource_body( WP_Post $post ): string {
		$body = [
			'title' => html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, "UTF-8" ),
			'slug' => (string)$post->ID,
			'metadata' => [
				'language' => get_bloginfo("language")
			],
			'origin' => [
				'url' => get_permalink( $post ),
			],
			'created' => gmdate('Y-m-d', strtotime( $post->post_date_gmt )).'T'.gmdate('H:i:s', strtotime( $post->post_date_gmt )).'Z'
		];

		$taxonomy_label_map = $this->settings->get_taxonomy_label_map();
		if ( ! empty( $taxonomy_label_map ) ) {
			$body['usermetadata'] = [
				'classifications' => $this->build_taxonomy_classifications( $post ),
			];
		}
		
		// for attachments
		if ( $post->post_type == 'attachment' ) :
			// $file     = get_attached_file( $post->ID );
			// $filename = esc_html( wp_basename( $file ) );
			$mime_type = get_post_mime_type( $post->ID );
			$body = [
				...$body,
				'icon' => $mime_type,
			];
			
		// other post types
		else :
			$body = [
				...$body,
				'icon' => 'text/html',
				'texts' => [ 
					'text-1' => [
						'body' => apply_filters('the_content', $post->post_content ),
						'format' => 'HTML',
					]
				]
			];
			
		endif;
		
		return json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Get available Nuclia labelsets (cached).
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */
	public function get_labelsets(): array {
		$cache = $this->settings->get_labelsets_cache();
		$cached_labelsets = $cache['labelsets'] ?? [];
		$fetched_at = (int) ( $cache['fetched_at'] ?? 0 );
		$ttl = defined( 'HOUR_IN_SECONDS' ) ? 6 * HOUR_IN_SECONDS : 21600;

		if ( ! empty( $cached_labelsets ) && $fetched_at > 0 && ( time() - $fetched_at ) < $ttl ) {
			return $cached_labelsets;
		}

		if ( empty( $this->settings->get_zone() ) || empty( $this->settings->get_kbid() ) || empty( $this->settings->get_token() ) ) {
			return $cached_labelsets;
		}

		$uri = "{$this->endpoint}labelsets";
		$args = [
			'method' => 'GET',
			'headers' => [
				'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $this->settings->get_token(),
			],
		];

		$response = wp_remote_get( $uri, $args );
		if ( is_wp_error( $response ) ) {
			nuclia_error_log( 'Failed to fetch labelsets: ' . $response->get_error_message() );
			return $cached_labelsets;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			nuclia_error_log( 'Failed to fetch labelsets, response code: ' . $response_code );
			return $cached_labelsets;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$normalized = $this->normalize_labelsets_response( $data );
		$labelsets = $normalized['labelsets'];
		$labels_map = $normalized['labels'];
		if ( ! empty( $labelsets ) ) {
			if ( ! empty( $labels_map ) ) {
				$this->settings->set_labelsets_cache_with_labels( $labelsets, $labels_map );
			} else {
				$this->settings->set_labelsets_cache( $labelsets );
			}
			return $labelsets;
		}

		return $cached_labelsets;
	}

	/**
	 * Get labels for a labelset (cached).
	 *
	 * @since 1.2.0
	 *
	 * @param string $labelset
	 * @return array
	 */
	public function get_labelset_labels( string $labelset ): array {
		$labelset = trim( $labelset );
		if ( $labelset === '' ) {
			return [];
		}

		$cache = $this->settings->get_labelsets_cache();
		$fetched_at = (int) ( $cache['fetched_at'] ?? 0 );
		$ttl = defined( 'HOUR_IN_SECONDS' ) ? 6 * HOUR_IN_SECONDS : 21600;
		$cached_labels = $this->settings->get_labelset_labels_cache( $labelset );

		if ( ! empty( $cached_labels ) && $fetched_at > 0 && ( time() - $fetched_at ) < $ttl ) {
			return $cached_labels;
		}

		if ( empty( $this->settings->get_zone() ) || empty( $this->settings->get_kbid() ) || empty( $this->settings->get_token() ) ) {
			return $cached_labels;
		}

		$labels = $this->fetch_labelset_labels( $labelset );
		if ( ! empty( $labels ) ) {
			$this->settings->set_labelset_labels_cache( $labelset, $labels );
			return $labels;
		}

		return $cached_labels;
	}

	/**
	 * Normalize labelset list from API response.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $data
	 * @return array
	 */
	private function normalize_labelsets_response( mixed $data ): array {
		if ( ! is_array( $data ) ) {
			return [
				'labelsets' => [],
				'labels' => [],
			];
		}

		$labelsets = [];
		$labels_map = [];
		$payload = $data['labelsets'] ?? $data;

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
							$labelsets[] = $labelset_name;
							$labels = $this->normalize_labelset_labels( $entry );
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
			'labels' => $labels_map,
		];
	}

	/**
	 * Fetch labels for a labelset from the API.
	 *
	 * @since 1.2.0
	 *
	 * @param string $labelset
	 * @return array
	 */
	private function fetch_labelset_labels( string $labelset ): array {
		$labelset = rawurlencode( $labelset );
		$candidates = [
			"{$this->endpoint}labelsets/{$labelset}",
			"{$this->endpoint}labelset/{$labelset}",
			"{$this->endpoint}labelsets/{$labelset}/labels",
			"{$this->endpoint}labelset/{$labelset}/labels",
		];

		foreach ( $candidates as $uri ) {
			$args = [
				'method' => 'GET',
				'headers' => [
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $this->settings->get_token(),
				],
			];

			$response = wp_remote_get( $uri, $args );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				continue;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$labels = $this->normalize_labelset_labels( $data );
			if ( ! empty( $labels ) ) {
				return $labels;
			}
		}

		return [];
	}

	/**
	 * Normalize label list for a labelset response.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $data
	 * @return array
	 */
	private function normalize_labelset_labels( mixed $data ): array {
		if ( ! is_array( $data ) ) {
			return [];
		}

		$labels = [];
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

	/**
	 * Build classifications based on taxonomy mapping.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	private function build_taxonomy_classifications( WP_Post $post ): array {
		$taxonomy_label_map = $this->settings->get_taxonomy_label_map();
		if ( empty( $taxonomy_label_map ) ) {
			return [];
		}

		$classifications = [];

		foreach ( $taxonomy_label_map as $taxonomy => $config ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $config ) ) {
				continue;
			}

			$labelset = isset( $config['labelset'] ) ? trim( (string) $config['labelset'] ) : '';
			$term_map = is_array( $config['terms'] ?? null ) ? $config['terms'] : [];

			if ( $labelset === '' || empty( $term_map ) ) {
				continue;
			}

			$term_ids = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $term_ids ) ) {
				continue;
			}

			foreach ( $term_ids as $term_id ) {
				$term_id = (int) $term_id;
				if ( empty( $term_map[ $term_id ] ) ) {
					continue;
				}

				$label = trim( (string) $term_map[ $term_id ] );
				if ( $label === '' ) {
					continue;
				}

				$classifications[] = [
					'labelset' => $labelset,
					'label' => $label
				];
			}
		}

		$unique = [];
		foreach ( $classifications as $classification ) {
			$key = $classification['labelset'] . '|' . $classification['label'];
			$unique[ $key ] = $classification;
		}

		return array_values( $unique );
	}

	/**
	 * Create a resource
	 *
	 * @since  1.0.0
	 *
	 * @param int $post_id ID of the post.
	 *
	 * @param string $body Content to send for indexation.
	 */
	public function create_resource( int $post_id, WP_Post $post ): void {

		$uri = "{$this->endpoint}resources";
		
		$args = [
			'method' => 'POST',
			'headers' => [
				'Content-type' => 'application/json',
				'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' .$this->settings->get_token()
			],
			'body' => $this->prepare_nuclia_resource_body( $post )
		];
		
		nuclia_log("endpoint : {$uri}");
		
		$response = wp_remote_request( $uri, $args );
		$response_code = wp_remote_retrieve_response_code( $response ); // int or empty string
		
		nuclia_log("code : {$response_code}");
		
		if ( !is_wp_error( $response ) ) {
			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
			// successfull response
			if ( $response_code === 201 ) {
				$rid = $api_response['uuid'];
				$seqid = $api_response['seqid'];
				
				// Only upload file for attachments
				if ( $post->post_type === 'attachment' ) {
					$file = get_attached_file( $post->ID );
					
					// Verify file exists before attempting upload
					if ( $file && file_exists( $file ) ) {
						$uri = "{$this->endpoint}resource/{$rid}/file/file/upload";
						nuclia_log("uri : {$uri}");
						$filename = esc_html( wp_basename( $file ) );
						$args = [
							'method' => 'POST',
							'headers' => [
								'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' .$this->settings->get_token(),
								'Content-Type' => get_post_mime_type( $post->ID ) ?? 'application/octet-stream',
								'x-filename' => $filename,
								'x-md5' => md5_file( $file ),
							],
							'body' => file_get_contents( $file )
						];
						$response = wp_remote_request( $uri, $args );
						$response_code = wp_remote_retrieve_response_code( $response ); // int or empty string
						nuclia_log("code : {$response_code}");
						if ( !is_wp_error( $response ) ) {
							$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
							if ( $response_code === 200 ) {
								$seqid = $api_response['seqid'];
							}
						}
					} else {
						nuclia_error_log("File not found for attachment {$post_id}: {$file}");
					}
				}
				
				// Save the index entry for both attachments and regular posts
				$this->upsert_index( $post_id, $rid, $seqid );
				nuclia_log("nuclia success : ".print_r($api_response, true) );
			}
			// Validation error
			else {
				nuclia_error_log("nuclia error : ".print_r($api_response, true) );
			};
		} else {
			nuclia_error_log("connection error: ".print_r($response,true) );
		};
		
	}
	
	/**
	 * Modify a resource
	 *
	 * @since  1.0.0
	 *
	 * @param int $post_id ID of the post.
	 *
	 * @param string  $rid  Resource ID in nucliaDB
	 * @param string $body The content to index.
	 */
	public function modify_resource( int $post_id, string $rid, WP_Post $post ): void {

		$uri = "{$this->endpoint}resource/{$rid}";
		$args = [
			'method' => 'PATCH',
			'headers' => [
				'Content-type' => 'application/json',
				'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' .$this->settings->get_token()
			],
			'body' => $this->prepare_nuclia_resource_body( $post )
		];
		
		nuclia_log( $uri );
		nuclia_log( print_r( $args, true ) );
		
		$response = wp_remote_request( $uri, $args );
		$response_code = wp_remote_retrieve_response_code( $response ); // int or empty string
		nuclia_log("code : {$response_code}");
		if ( !is_wp_error( $response ) ) {
			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
			// successfull response
			if ( $response_code === 200 ) {
				$seqid = $api_response['seqid'];
				$this->upsert_index( $post_id, $rid, $seqid );
				nuclia_log("nuclia success : ".print_r($api_response, true));
			}
			// Validation error
			else {
				nuclia_error_log("nuclia error : ".print_r($api_response, true));
			};
		} else {
			nuclia_error_log("connection error: ".print_r($response,true));
		};
	}
	
	/**
	 * Delete a resource
	 *
	 * @since  1.0.0
	 *
	 * @param int $post_id ID of the post.
	 *
	 * @param string  $rid  Resource ID in nucliaDB
	 */	 
	public function delete_resource( int $post_id, string $rid ): void {

		$uri = "{$this->endpoint}resource/{$rid}";
		
		$args = [
			'method' => 'DELETE',
			'headers' => [
				'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' .$this->settings->get_token()
			],
			'body' => ''
		];
		
		nuclia_log( $uri );
		nuclia_log( print_r( $args, true ) );
		
		$response = wp_remote_request( $uri, $args );
		$response_code = wp_remote_retrieve_response_code( $response ); // int or empty string
		nuclia_log("code : {$response_code}");
		
		if ( !is_wp_error( $response ) ) {
			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );
			// successfull response
			if ( $response_code === 204 ) {
				$this->delete_index( $post_id );
				nuclia_log("nuclia success : ".print_r($api_response, true));
			}
			// Validation error
			else {
				nuclia_error_log("nuclia error : ".print_r($api_response, true));

			};
		} else {
			nuclia_error_log("connection error: ".print_r($response,true) );
		};
	}
}
