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
		
		// for attachments
		if ( $post->post_type == 'attachment' ) :
			// $file     = get_attached_file( $post->ID );
			// $filename = esc_html( wp_basename( $file ) );
			$mime_type = get_post_mime_type( $post->ID );
			$body = [
				...$body,
				'icon' => $mime_type,
				// 'files' => [ 
				// 	$post->post_name => [
				// 		'file' => [
				// 			'filename' => $filename,
				// 			'content_type' => $mime_type,
				// 			'payload' => base64_encode( file_get_contents( $file ) )
				// 		]
				// 	]
				// ]
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
