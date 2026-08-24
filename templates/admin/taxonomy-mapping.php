<?php
/**
 * Taxonomy label mapping form.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped by the including admin renderer.
?>
<section class="progress-agentic-rag__mapping" aria-labelledby="progress-agentic-rag-taxonomy-mapping-title">
	<div class="progress-agentic-rag__mapping-heading">
		<h3 id="progress-agentic-rag-taxonomy-mapping-title"><?php esc_html_e( 'Taxonomy label mapping', 'progress-agentic-rag-connector' ); ?></h3>
		<p><?php esc_html_e( 'Map WordPress taxonomy terms to Progress Agentic RAG labels. Labelset suggestions come from your Knowledge Box.', 'progress-agentic-rag-connector' ); ?></p>
	</div>
	<div>
		<?php if ( empty( $taxonomies ) ) : ?>
			<p class="progress-agentic-rag__mapping-muted"><?php esc_html_e( 'No public taxonomies available for mapping.', 'progress-agentic-rag-connector' ); ?></p>
		<?php else : ?>
			<?php $mapped_taxonomies = array_keys( $taxonomy_label_map ); ?>
			<div class="progress-agentic-rag__mapping-control">
				<label for="progress-agentic-rag-taxonomy-select"><?php esc_html_e( 'Add taxonomy mapping:', 'progress-agentic-rag-connector' ); ?></label>
				<select id="progress-agentic-rag-taxonomy-select" data-progress-agentic-rag-taxonomy-select>
					<option value=""><?php esc_html_e( 'Select a taxonomy', 'progress-agentic-rag-connector' ); ?></option>
					<?php foreach ( $taxonomies as $taxonomy_name => $taxonomy ) : ?>
						<?php if ( in_array( $taxonomy_name, $mapped_taxonomies, true ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<option value="<?php echo esc_attr( $taxonomy_name ); ?>"><?php echo esc_html( $taxonomy->labels->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-add-mapping><?php esc_html_e( 'Add mapping', 'progress-agentic-rag-connector' ); ?></button>
			</div>

			<div class="progress-agentic-rag__mapping-blocks" data-progress-agentic-rag-mapping-container>
				<?php foreach ( $taxonomy_label_map as $taxonomy_key => $config ) : ?>
					<?php
					if ( ! isset( $taxonomies[ $taxonomy_key ] ) || ! is_array( $config ) ) {
						continue;
					}

					$taxonomy            = $taxonomies[ $taxonomy_key ];
					$taxonomy_labelset   = (string) ( $config['labelset'] ?? '' );
					$term_map            = is_array( $config['terms'] ?? null ) ? $config['terms'] : [];
					$fallback_config     = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
					$fallback_labelset   = (string) ( $fallback_config['labelset'] ?? '' );
					$fallback_labels     = is_array( $fallback_config['labels'] ?? null ) ? $fallback_config['labels'] : [];
					$available_labels    = '' !== $taxonomy_labelset && isset( $labelset_labels[ $taxonomy_labelset ] ) ? $labelset_labels[ $taxonomy_labelset ] : [];
					$fallback_available  = '' !== $fallback_labelset && isset( $labelset_labels[ $fallback_labelset ] ) ? $labelset_labels[ $fallback_labelset ] : [];
					$terms               = $taxonomy_terms[ $taxonomy_key ] ?? [];
					?>
					<div class="progress-agentic-rag__mapping-block" data-progress-agentic-rag-mapping-block data-taxonomy="<?php echo esc_attr( $taxonomy_key ); ?>">
						<div class="progress-agentic-rag__mapping-block-header">
							<div>
								<h4><?php echo esc_html( $taxonomy->labels->name ); ?></h4>
								<code><?php echo esc_html( $taxonomy_key ); ?></code>
							</div>
							<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--danger" data-progress-agentic-rag-remove-mapping><?php esc_html_e( 'Remove', 'progress-agentic-rag-connector' ); ?></button>
						</div>
						<label class="progress-agentic-rag__mapping-select" for="progress-agentic-rag-labelset-<?php echo esc_attr( $taxonomy_key ); ?>">
							<span><?php esc_html_e( 'Labelset', 'progress-agentic-rag-connector' ); ?></span>
							<select id="progress-agentic-rag-labelset-<?php echo esc_attr( $taxonomy_key ); ?>" name="<?php echo esc_attr( $taxonomy_label_map_option ); ?>[<?php echo esc_attr( $taxonomy_key ); ?>][labelset]" data-progress-agentic-rag-labelset-select data-taxonomy="<?php echo esc_attr( $taxonomy_key ); ?>">
								<option value=""><?php esc_html_e( 'Select a labelset', 'progress-agentic-rag-connector' ); ?></option>
								<?php foreach ( $labelsets as $labelset ) : ?>
									<option value="<?php echo esc_attr( $labelset ); ?>" <?php selected( $taxonomy_labelset, $labelset ); ?>><?php echo esc_html( $labelset ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<?php if ( empty( $labelsets ) ) : ?>
							<p class="progress-agentic-rag__mapping-muted"><?php esc_html_e( 'No labelsets available. Check your Progress Agentic RAG credentials.', 'progress-agentic-rag-connector' ); ?></p>
						<?php endif; ?>

						<?php if ( empty( $terms ) ) : ?>
							<p class="progress-agentic-rag__mapping-muted"><?php esc_html_e( 'No terms available for this taxonomy.', 'progress-agentic-rag-connector' ); ?></p>
						<?php else : ?>
							<table class="progress-agentic-rag__mapping-table">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Term', 'progress-agentic-rag-connector' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Progress Agentic RAG labels', 'progress-agentic-rag-connector' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $terms as $term ) : ?>
										<?php
										$term_labels = $term_map[ (int) $term->term_id ] ?? [];
										if ( ! is_array( $term_labels ) ) {
											$term_labels = '' !== $term_labels ? [ (string) $term_labels ] : [];
										}
										?>
										<tr>
											<th scope="row"><?php echo esc_html( $term->name ); ?></th>
											<td>
												<div class="progress-agentic-rag__label-checkboxes" data-progress-agentic-rag-label-checkboxes data-taxonomy="<?php echo esc_attr( $taxonomy_key ); ?>" data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>">
													<?php if ( empty( $available_labels ) ) : ?>
														<em><?php echo '' === $taxonomy_labelset ? esc_html__( 'Select a labelset to load labels.', 'progress-agentic-rag-connector' ) : esc_html__( 'No labels available.', 'progress-agentic-rag-connector' ); ?></em>
													<?php else : ?>
														<?php foreach ( $available_labels as $label ) : ?>
															<label class="progress-agentic-rag__label-checkbox">
																<input type="checkbox" value="<?php echo esc_attr( $label ); ?>" name="<?php echo esc_attr( $taxonomy_label_map_option ); ?>[<?php echo esc_attr( $taxonomy_key ); ?>][terms][<?php echo esc_attr( (string) $term->term_id ); ?>][]" <?php checked( in_array( $label, $term_labels, true ) ); ?> />
																<span><?php echo esc_html( $label ); ?></span>
															</label>
														<?php endforeach; ?>
													<?php endif; ?>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>

						<div class="progress-agentic-rag__fallback">
							<p><strong><?php esc_html_e( 'Fallback labels (when no terms assigned)', 'progress-agentic-rag-connector' ); ?></strong></p>
							<label class="progress-agentic-rag__mapping-select" for="progress-agentic-rag-fallback-labelset-<?php echo esc_attr( $taxonomy_key ); ?>">
								<span><?php esc_html_e( 'Labelset', 'progress-agentic-rag-connector' ); ?></span>
								<select id="progress-agentic-rag-fallback-labelset-<?php echo esc_attr( $taxonomy_key ); ?>" name="<?php echo esc_attr( $taxonomy_label_map_option ); ?>[<?php echo esc_attr( $taxonomy_key ); ?>][fallback][labelset]" data-progress-agentic-rag-fallback-labelset-select data-taxonomy="<?php echo esc_attr( $taxonomy_key ); ?>">
									<option value=""><?php esc_html_e( 'Select a labelset', 'progress-agentic-rag-connector' ); ?></option>
									<?php foreach ( $labelsets as $labelset ) : ?>
										<option value="<?php echo esc_attr( $labelset ); ?>" <?php selected( $fallback_labelset, $labelset ); ?>><?php echo esc_html( $labelset ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<div class="progress-agentic-rag__fallback-labels" data-progress-agentic-rag-fallback-labels data-taxonomy="<?php echo esc_attr( $taxonomy_key ); ?>">
								<?php if ( empty( $fallback_available ) ) : ?>
									<em><?php echo '' === $fallback_labelset ? esc_html__( 'Select a labelset to load labels.', 'progress-agentic-rag-connector' ) : esc_html__( 'No labels available.', 'progress-agentic-rag-connector' ); ?></em>
								<?php else : ?>
									<?php foreach ( $fallback_available as $label ) : ?>
										<label class="progress-agentic-rag__label-checkbox">
											<input type="checkbox" value="<?php echo esc_attr( $label ); ?>" name="<?php echo esc_attr( $taxonomy_label_map_option ); ?>[<?php echo esc_attr( $taxonomy_key ); ?>][fallback][labels][]" <?php checked( in_array( $label, $fallback_labels, true ) ); ?> />
											<span><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
