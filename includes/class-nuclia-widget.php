<?php

/**
 * Nuclia_Searchbox_Widget class file.
 *
 * @since   1.0.0
 *
 */


/**
 * Class Nuclia_Searchbox_Widget
 *
 * @since 1.0.0
 */

namespace Progress\WPSWN;

class Nuclia_Searchbox_Widget extends \WP_Widget {

	/**
	 * @var array<string,string>
	 */
	public array $args = [
		'before_title'  => '<h4 class="widgettitle">',
		'after_title'   => '</h4>',
		'before_widget' => '<div class="widget-wrap">',
		'after_widget'  => '</div>',
	];

	public function __construct() {
		$widget_args = [
			'classname'             => 'nuclia-searchbox-widget',
			'description' 			=> __( "Fully functional and customizable widget to embed Nuclia's search in seconds.", 'progress-agentic-rag' ),
			'show_instance_in_rest' => true
		];

		parent::__construct(
			'nuclia-search',  // Base ID
			__('Progress Agentic RAG search','progress-agentic-rag'),  // Name
			$widget_args // arguments
		);
	}

	/**
	 * @param array<string,mixed> $args
	 * @param array<string,mixed> $instance
	 *
	 * @return void
	 */
    public function widget( $args, $instance ): void {
        wp_enqueue_script(
                'nuclia-widget',
                'https://cdn.rag.progress.cloud/nuclia-widget.umd.js',
                [],
                false,
                true
        );

        echo wp_kses_post( $args['before_widget'] );

        if ( ! empty( $instance['title'] ) ) {
            $title = apply_filters( 'widget_title', $instance['title'] );
            echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
        }

        if ( ! empty( $instance['kbid'] ) && ! empty( $instance['zone'] ) ) :
            $kbid     = esc_attr( $instance['kbid'] );
            $zone     = esc_attr( $instance['zone'] );
            $features = ( ! empty( $instance['features'] ) && is_array( $instance['features'] ) )
                    ? esc_attr( implode( ',', $instance['features'] ) )
                    : '';

            echo '<nuclia-search-bar';
            echo ' knowledgebox="' . $kbid . '"';
            echo ' zone="' . $zone . '"';
            echo $features !== '' ? ' features="' . $features . '"' : '';
            echo '></nuclia-search-bar>';
            echo '<nuclia-search-results></nuclia-search-results>';
        else :
            if ( current_user_can( 'edit_posts' ) ) {
                echo sprintf(
                        '<div style="color:red; border: 2px dotted red; padding: .5em;">%s</div>',
                        esc_html__(
                                'Nuclia shortcode misconfigured. Please provide your zone and your kbid.',
                                'progress-agentic-rag'
                        )
                );
            } else {
                echo '';
            }
        endif;

        echo wp_kses_post( $args['after_widget'] );
    }

	/**
	 * @param array<string,mixed> $instance
	 *
	 * @return void
	 */
	public function form( $instance ): void {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( '', 'progress-agentic-rag' );
		$zone  = ! empty( $instance['zone'] ) ? $instance['zone'] : 'europe-1';
		$kbid  = ! empty( $instance['kbid'] ) ? $instance['kbid'] : esc_html__( '', 'progress-agentic-rag' );
		$features  = ! empty( $instance['features'] ) ? $instance['features'] : [ 'navigateToLink' ];

		// available features
		$widget_search_features = [
			"navigateToLink" => __("Navigate to links : clicking on a result will open the original page rather than rendering it in the viewer." , 'progress-agentic-rag' ),
			"permalink" => __("Permalinks : add extra parameters in URL allowing direct opening of a resource or search results." , 'progress-agentic-rag' ),
			"suggestions" => __("Suggestions : suggest results while typing search query." , 'progress-agentic-rag' ),
			//"suggestLabels" => __("Suggest labels" , 'progress-agentic-rag' ),
			//"filter" => __("Filter" , 'progress-agentic-rag' ),
			//"relations" => __("Relations" , 'progress-agentic-rag' )
		];
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo esc_html__( 'Title:', 'progress-agentic-rag' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'zone' ) ); ?>"><?php echo esc_html__( 'Zone:', 'progress-agentic-rag' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'zone' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'zone' ) ); ?>" >
				<option value="europe-1" <?php selected( esc_attr( $zone ),'europe-1'); ?> >europe-1</option>
            </select>
		</p>
        <p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'kbid' ) ); ?>"><?php echo esc_html__( 'Knowledgebox ID:', 'progress-agentic-rag' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'kbid' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'kbid' ) ); ?>" type="text" value="<?php echo esc_attr( $kbid ); ?>">
		</p>
        <p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'features' ) ); ?>"><?php echo esc_html__( 'Features:', 'progress-agentic-rag' ); ?></label><br>
        	<?php foreach( $widget_search_features as $key => $label ) : ?>
            <input class="checkbox" type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'features' ).$key ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'features' ) ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $features ), 1 ); ?>> <?php echo $label; ?><br />
            <?php endforeach; ?>
		</p>
		<?php
	}

	/**
	 * @param array<string,mixed> $new_instance
	 * @param array<string,mixed> $old_instance
	 *
	 * @return array<string,mixed>
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance          = [];
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['zone']  = ( ! empty( $new_instance['zone'] ) ) ? sanitize_title( $new_instance['zone'] ) : 'europe-1';
		$instance['kbid']  = ( ! empty( $new_instance['kbid'] ) ) ? sanitize_title( $new_instance['kbid'] ) : '';
		$instance['features']  = ( ! empty( $new_instance['features'] ) ) ? array_filter( $new_instance['features'], 'sanitize_title' ) : [];
		return $instance;
	}

}

/**
 * @return void
 */
function register_nuclia_widget(): void {
	\register_widget( __NAMESPACE__.'\Nuclia_Searchbox_Widget' );
}
\add_action( 'widgets_init', __NAMESPACE__.'\register_nuclia_widget' );
