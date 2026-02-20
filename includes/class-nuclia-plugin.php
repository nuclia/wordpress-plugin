<?php
/**
 * Nuclia_Plugin class file.
 *
 * @since   1.0.0
 *
 */

/**
 * Class Nuclia_Plugin
 *
 * @since 1.0.0
 */
class Nuclia_Plugin {

	/**
	 * Instance of Nuclia_API.
	 *
	 * @since  1.0.0
	 *
	 * @var Nuclia_API
	 */
	public Nuclia_API $api;

	/**
	 * Instance of Nuclia_Settings.
	 *
	 * @since  1.0.0
	 *
	 * @var Nuclia_Settings
	 */
	private Nuclia_Settings $settings;

	/**
	 * Instance of Nuclia_Background_Processor.
	 *
	 * @since  1.1.0
	 *
	 * @var Nuclia_Background_Processor
	 */
	private Nuclia_Background_Processor $background_processor;

	/**
	 * Instance of Nuclia_Label_Reprocessor.
	 *
	 * @since  1.4.0
	 *
	 * @var Nuclia_Label_Reprocessor
	 */
	private Nuclia_Label_Reprocessor $label_reprocessor;

	/**
	 * Nuclia_Plugin constructor.
	 *
	 * @since  1.0.0
	 */
	public function __construct() {
		$this->settings             = new Nuclia_Settings();
		$this->api                  = new Nuclia_API( $this->settings );
		$this->background_processor = new Nuclia_Background_Processor( $this );
		$this->label_reprocessor    = new Nuclia_Label_Reprocessor( $this );

		// Register background processor hooks early (before 'init')
		$this->background_processor->register_hooks();
		$this->label_reprocessor->register_hooks();

		add_action( 'init', [ $this, 'load' ], 20 );
	}

	/**
	 * Load.
	 *
	 * @since  1.0.0
	 */
	public function load(): void {
		// Load admin or public part of the plugin.
		if ( is_admin() ) {
			
			new Nuclia_Admin_Page_Settings( $this );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			
			if ( $this->settings->get_api_is_reachable() ) {
				// post, page and custom post type
				add_action( 'save_post', [ $this, 'create_or_modify_nucliadb_resource' ], 10, 2 );	// $post_id, $post
				// attachment
				add_action( 'add_attachment', [ $this, 'add_or_modify_attachment' ], 10, 1 ); // $post_id
				add_action( 'attachment_updated', [ $this, 'add_or_modify_attachment' ], 10, 1 ); // $post_id
				// delete
				add_action( 'delete_post', [ $this, 'delete_nuclia_resource' ], 10, 2 ); // $post_id, $post
				
				// ajax call to index all content
				add_action( 'wp_ajax_nuclia_re_index', [ $this, 're_index' ] );
			}
		}
	
	}

	/**
	 * Get the Nuclia_API.
	 *
	 * @since  1.0.0
	 *
	 * @return Nuclia_API
	 */
	public function get_api(): Nuclia_API {
		return $this->api;
	}

	/**
	 * Get the Nuclia_Settings.
	 *
	 * @since  1.0.0
	 *
	 * @return Nuclia_Settings
	 */
	public function get_settings(): Nuclia_Settings {
		return $this->settings;
	}

	/**
	 * Get the Nuclia_Background_Processor.
	 *
	 * @since  1.1.0
	 *
	 * @return Nuclia_Background_Processor
	 */
	public function get_background_processor(): Nuclia_Background_Processor {
		return $this->background_processor;
	}

	/**
	 * Get the Nuclia_Label_Reprocessor.
	 *
	 * @since  1.4.0
	 *
	 * @return Nuclia_Label_Reprocessor
	 */
	public function get_label_reprocessor(): Nuclia_Label_Reprocessor {
		return $this->label_reprocessor;
	}

	/**
	 * Get indexable post types.
	 *
	 * @since  1.0.0
	 *
	 * @return array post type names indexable.
	 */
	public function get_indexable_post_types(): array {
		return $this->settings->get_indexable_post_types();
	}
	
	/** After attachment is added or modified.
	 *
	 * @since   1.0.0
	 *
	 * @param int $post_id Attachment ID.
	 */
	 
	public function add_or_modify_attachment( int $post_id ): void {

		$post = get_post( $post_id );
		
		// hack attachments are inherit
		$post->post_status = 'publish'; 
		
		$this->create_or_modify_nucliadb_resource( $post_id, $post );
	}
	
	
	/** Create or modify resource.
	 *
	 * @since   1.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    WP_Post object.
	 */

	public function create_or_modify_nucliadb_resource( int $post_id, WP_Post $post ): void {

		// auto-save
		if( wp_is_post_autosave($post) ) return;
		
		nuclia_log( 'ID : '.$post_id );
		nuclia_log( 'type : '.$post->post_type );
		nuclia_log( 'password protected: '.( $post->post_password ? 'yes' : 'no' ) );
		nuclia_log( 'status : '.$post->post_status );
		
		// indexable post type
		if ( !array_key_exists( $post->post_type, $this->get_indexable_post_types() ) ) return;
		
		// do not index or delete, if not public or password protected
		$dont_index = ( $post->post_password || $post->post_status !== 'publish' ) ? true : false;
		if ( $dont_index ) {
			return;
		}

		// resource id if already indexed
		$rid = $this->api->get_rid( $post_id );
			
		if ( $rid ) { // post already indexed
			nuclia_log( 'Modifying resource' );
			// $body = $this->prepare_nuclia_resource_body( $post );
			$this->api->modify_resource( $post_id, $rid, $post );
		} else { // post not indexed
			nuclia_log( 'Creating resource' );
			// $body = $this->prepare_nuclia_resource_body( $post );
			$this->api->create_resource( $post_id, $post );
		};
	}

	/**
	 * Delete nuclia resource.
	 *
	 * @since  1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @param WP_Post $post WP_Post object.
	 */
	public function delete_nuclia_resource( int $post_id, WP_Post $post ): void {
		$rid = $this->api->get_rid( $post_id );
		if ( $rid ) {
			$this->api->delete_resource( $post_id, $rid );
		}
	}

	
	/**
	 * Prepare NucliaDB resource body
	 *
	 * @param WP_Post $post Post to index into NucliaDb.
	 *
	 * @return string Prepared resource body as JSON string
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
		// https://docs.nuclia.dev/docs/quick-start/push/#push-a-cloud-based-file
		if ( $post->post_type == 'attachment' ) :
			$file     = get_attached_file( $post->ID );
			$filename = esc_html( wp_basename( $file ) );
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
	 * Enqueue scripts.
	 *
	 * @since   1.0.0
	 */
	public function enqueue_scripts( string $hook = '' ): void {
		if ( $hook !== 'toplevel_page_progress-agentic-rag' ) {
			return;
		}

		$plugin_url = plugin_dir_url(__FILE__) . 'admin/js/reindex-button.js';
		wp_enqueue_script(
			'nuclia-admin-reindex-button',
			$plugin_url,
			[],
			PROGRESS_NUCLIA_VERSION,
			true
		);

		// pass nonce to js.
		wp_localize_script(
			'nuclia-admin-reindex-button',
			'nucliaReindex',
			[
				'nonce' => wp_create_nonce( 'nuclia_reindex_nonce' ),
				'labelsNonce' => wp_create_nonce( 'nuclia_labels_nonce' ),
				'i18n' => [
					'confirmReprocess' => __( 'This will update labels for %d synced resource(s) with the current taxonomy mapping. Continue?', 'progress-agentic-rag' ),
					'copied' => __( 'Copied!', 'progress-agentic-rag' ),
					'copyFailed' => __( 'Copy failed', 'progress-agentic-rag' ),
				],
			]
		);

		$taxonomies = get_taxonomies(
			[
				'public' => true,
			],
			'objects'
		);

		$taxonomy_data = [];
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				[
					'taxonomy' => $taxonomy->name,
					'hide_empty' => false,
				]
			);

			if ( is_wp_error( $terms ) ) {
				$terms = [];
			}

			$taxonomy_data[ $taxonomy->name ] = [
				'label' => $taxonomy->labels->name ?? $taxonomy->name,
				'terms' => array_map(
					static function ( $term ) {
						return [
							'id' => $term->term_id,
							'name' => $term->name,
						];
					},
					$terms
				),
			];
		}

		$labelsets = $this->api->get_labelsets();

		wp_localize_script(
			'nuclia-admin-reindex-button',
			'nucliaMappingData',
			[
				'taxonomies' => $taxonomy_data,
				'labelsets' => $labelsets,
			]
		);
	}

	/**
	 * Re index.
	 *
	 * @since   1.0.0
	 *
	 * @throws RuntimeException If index ID or page are not provided, or index name does not exist.
	 * @throws Exception If index ID or page are not provided, or index name does not exist.
	 */
	public function re_index(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // Verify nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclia_reindex_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

        $post_type = sanitize_text_field( $_POST['post_type'] ?? '' );
				
		try {
			if ( empty( $post_type ) || !post_type_exists( $post_type ) ) {
				nuclia_log( 'Post type should be provided' );
				throw new RuntimeException( 'Post type should be provided.' );
			}
			
			$indexable = get_option( "nuclia_indexable_{$post_type}", [] );
			
			$total_pages = count( $indexable );
			nuclia_log( "Total pages {$total_pages}" );
			if ( $total_pages ) {
				$post_id = array_shift($indexable);
				update_option( "nuclia_indexable_{$post_type}", $indexable );
				$total_pages --;
				if ( $post_type == 'attachment' ) {
					$this->add_or_modify_attachment( $post_id );
				} else {
					$post = get_post( $post_id );
					// $body = $this->prepare_nuclia_resource_body( $post );
					$this->api->create_resource( $post_id, $post );
				};
			};
			
			$response = [
				'nbPosts' => $total_pages
			];

			wp_send_json( $response, 200 );
		} catch ( Exception $exception ) {
			wp_send_json( ['error' => $exception->getMessage()], 500 );
			throw $exception;
		}
	}

}
