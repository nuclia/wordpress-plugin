<?php
/**
 * Elementor widget adapter for the search widget.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Frontend;

defined( 'ABSPATH' ) || exit;

final class ElementorSearchWidget extends \Elementor\Widget_Base {
	public function __construct( private readonly SearchWidget $search_widget, array $data = [], mixed $args = null ) {
		parent::__construct( $data, $args );
	}

	public function get_name(): string {
		return 'progress_agentic_rag_search';
	}

	public function get_title(): string {
		return __( 'Progress Agentic RAG Search', 'progress-agentic-rag-connector' );
	}

	public function get_icon(): string {
		return 'eicon-search';
	}

	/**
	 * @return list<string>
	 */
	public function get_categories(): array {
		return [ 'general' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'progress_agentic_rag_content',
			[
				'label' => __( 'Widget options', 'progress-agentic-rag-connector' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'features',
			[
				'label'    => __( 'Features', 'progress-agentic-rag-connector' ),
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options'  => SearchWidget::feature_options(),
				'default'  => SearchWidget::default_features(),
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		echo $this->search_widget->render_markup( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes all dynamic output.
	}
}
