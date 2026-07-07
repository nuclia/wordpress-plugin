<?php
/**
 * Admin connection settings panel.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-connection-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Service access', 'progress-agentic-rag' ); ?></div>
			<h2 id="progress-agentic-rag-connection-title"><?php esc_html_e( 'Connection settings', 'progress-agentic-rag' ); ?></h2>
		</div>
		<div class="progress-agentic-rag__connection-state">
			<div class="progress-agentic-rag__token-state">
				<?php echo esc_html( $connection_state_label ); ?>
			</div>
			<div class="progress-agentic-rag__token-state">
				<?php echo esc_html( $token_state_label ); ?>
			</div>
		</div>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="progress_agentic_rag_save_connection" />
		<?php wp_nonce_field( 'progress_agentic_rag_save_connection' ); ?>
		<div class="progress-agentic-rag__field-grid">
			<label class="progress-agentic-rag__field" for="progress-agentic-rag-zone">
				<span><?php esc_html_e( 'Zone', 'progress-agentic-rag' ); ?></span>
				<input type="text" id="progress-agentic-rag-zone" name="<?php echo esc_attr( $zone_option_name ); ?>" value="<?php echo esc_attr( $zone ); ?>" autocomplete="off" />
			</label>
			<label class="progress-agentic-rag__field" for="progress-agentic-rag-kbid">
				<span><?php esc_html_e( 'Knowledge Box ID', 'progress-agentic-rag' ); ?></span>
				<input type="text" id="progress-agentic-rag-kbid" name="<?php echo esc_attr( $kbid_option_name ); ?>" value="<?php echo esc_attr( $kbid ); ?>" autocomplete="off" />
			</label>
			<label class="progress-agentic-rag__field" for="progress-agentic-rag-account-id">
				<span><?php esc_html_e( 'Account ID', 'progress-agentic-rag' ); ?></span>
				<input type="text" id="progress-agentic-rag-account-id" name="<?php echo esc_attr( $account_id_option_name ); ?>" value="<?php echo esc_attr( $account_id ); ?>" autocomplete="off" />
			</label>
			<label class="progress-agentic-rag__field" for="progress-agentic-rag-token">
				<span><?php esc_html_e( 'Service token', 'progress-agentic-rag' ); ?></span>
				<input type="password" id="progress-agentic-rag-token" name="<?php echo esc_attr( $token_option_name ); ?>" value="" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Stored token is never displayed', 'progress-agentic-rag' ); ?>" />
			</label>
		</div>
		<div class="progress-agentic-rag__actions">
			<button type="submit" class="progress-agentic-rag__button progress-agentic-rag__button--primary"><?php esc_html_e( 'Save connection settings', 'progress-agentic-rag' ); ?></button>
		</div>
	</form>
</section>
