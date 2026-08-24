<?php
/**
 * Admin synchronized content panel.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped by the including admin renderer.
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-synced-content-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Resource mappings', 'progress-agentic-rag-connector' ); ?></div>
			<h2 id="progress-agentic-rag-synced-content-title"><?php esc_html_e( 'Synchronized content', 'progress-agentic-rag-connector' ); ?></h2>
		</div>
	</div>
	<p class="progress-agentic-rag__scheduler-note" data-progress-agentic-rag-single-delete-status>
		<?php esc_html_e( 'Remove an individual resource when you want it deleted from Progress Agentic RAG and unmapped locally without clearing every synced item.', 'progress-agentic-rag-connector' ); ?>
	</p>
	<?php if ( empty( $synced_content_rows ) ) : ?>
		<p class="progress-agentic-rag__muted"><?php esc_html_e( 'No synchronized content is currently mapped.', 'progress-agentic-rag-connector' ); ?></p>
	<?php else : ?>
		<table class="progress-agentic-rag__sync-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Content', 'progress-agentic-rag-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'progress-agentic-rag-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'progress-agentic-rag-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Resource ID', 'progress-agentic-rag-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sequence ID', 'progress-agentic-rag-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'progress-agentic-rag-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $synced_content_rows as $row ) : ?>
					<tr data-progress-agentic-rag-synced-row="<?php echo esc_attr( (string) $row['post_id'] ); ?>">
						<th scope="row">
							<span><?php echo esc_html( (string) $row['title'] ); ?></span>
							<code>#<?php echo esc_html( (string) $row['post_id'] ); ?></code>
							<?php if ( '' !== (string) $row['url'] ) : ?>
								<a href="<?php echo esc_url( (string) $row['url'] ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'View', 'progress-agentic-rag-connector' ); ?></a>
							<?php endif; ?>
						</th>
						<td><?php echo esc_html( (string) $row['type'] ); ?></td>
						<td><?php echo esc_html( (string) $row['status'] ); ?></td>
						<td><code><?php echo esc_html( (string) $row['rid'] ); ?></code></td>
						<td><code><?php echo esc_html( '' !== (string) $row['seqid'] ? (string) $row['seqid'] : '-' ); ?></code></td>
						<td>
							<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--danger" data-progress-agentic-rag-delete-single-synced="<?php echo esc_attr( (string) $row['post_id'] ); ?>">
								<?php esc_html_e( 'Remove from sync', 'progress-agentic-rag-connector' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
