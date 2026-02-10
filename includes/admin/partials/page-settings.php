<?php
/**
 * Form options admin template partial.
 *
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<style>
	.pl-nuclia-settings-page{max-width:1120px}
	.pl-nuclia-settings-page .pl-nuclia-settings-form{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px 22px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
	.pl-nuclia-settings-page h1{display:flex;align-items:center;gap:8px;margin-bottom:18px}
	.pl-nuclia-settings-page h2,.pl-nuclia-settings-page h3{color:#0f172a}
	.pl-nuclia-settings-page p.description{color:#475467}
	.pl-nuclia-settings-page .form-table{border-collapse:separate;border-spacing:0 12px;margin-top:10px}
	.pl-nuclia-settings-page .form-table th{padding:14px 14px 0 0;color:#344054;font-weight:600;width:220px}
	.pl-nuclia-settings-page .form-table td{padding:10px 12px;background:#f8fafc;border:1px solid #e4e7ec;border-radius:10px}
	.pl-nuclia-settings-page .regular-text{max-width:460px}
	.pl-nuclia-settings-page input[type="text"],
	.pl-nuclia-settings-page input[type="password"],
	.pl-nuclia-settings-page select{border-color:#d0d5dd;border-radius:8px}
	.pl-nuclia-settings-page .submit{margin-top:18px;padding-top:12px;border-top:1px solid #eaecf0}
	.pl-nuclia-settings-page .button-primary{box-shadow:none}
	.pl-nuclia-settings-page .notice{border-radius:8px}
	.pl-nuclia-inline-spinner{float:none !important;margin:0 6px 0 4px}
	.pl-nuclia-muted{margin:8px 0 0;color:#667085}
	.pl-nuclia-error{color:#b42318}
	.pl-nuclia-section-card{margin-top:12px;padding:12px;border:1px solid #e4e7ec;border-radius:10px;background:#fff}
	.pl-nuclia-flex-between{display:flex;align-items:center;justify-content:space-between;gap:10px}
	.pl-nuclia-label-table{margin-top:10px}
	.pl-nuclia-checkbox-row{display:block;margin:2px 0}
	.pl-nuclia-fallback-section{margin-top:12px;padding-top:10px;border-top:1px dashed #d0d5dd}
	.pl-nuclia-fallback-title{margin:0 0 6px}
	.nuclia-overall-status{margin-top:15px;padding:12px;border-radius:10px;background:#eef4ff;border:1px solid #c7d7fe}
	.nuclia-overall-status__stats{margin:0}
	.nuclia-overall-status__actions{margin:10px 0 0}
	.nuclia-connected-notice{margin-top:15px}
	.nuclia-connected-notice .dashicons{color:#067647}
</style>

<div class="wrap pl-nuclia-settings-page">
	<h1>
    	<?php echo esc_html( get_admin_page_title() ); ?>
    	&nbsp;<img width="24" height="24" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPHN2ZyB2aWV3Qm94PSI3LjE0OCAxMy40NTYgOTEuMDM1IDk0LjAzNyIgd2lkdGg9IjkxLjAzNSIgaGVpZ2h0PSI5NC4wMzciIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CiAgPGRlZnM+CiAgICA8c3R5bGU+LmNscy0xe2ZpbGw6I2ZmZDkxYjt9LmNscy0ye2ZpbGw6IzI1MDBmZjt9LmNscy0ze2ZpbGw6I2ZmMDA2YTt9PC9zdHlsZT4KICA8L2RlZnM+CiAgPHBhdGggY2xhc3M9ImNscy0xIiBkPSJNOTEuNjYsMzUuNzgsNTMuNDcsMTQuNDNhLjE5LjE5LDAsMCwwLS4xOCwwTDE0Ljk0LDM1LjQ5YS4xOS4xOSwwLDAsMCwwLC4zM0w1MC40LDU1LjUyYS4xOS4xOSwwLDAsMCwuMTgsMCw1LjQ3LDUuNDcsMCwwLDEsNS43MS4xMy4xNy4xNywwLDAsMCwuMTgsMEw5MS42NiwzNi4xMUEuMTkuMTksMCwwLDAsOTEuNjYsMzUuNzhaIi8+CiAgPHBhdGggY2xhc3M9ImNscy0yIiBkPSJNNTguNzcsNjAuMDhhLjcxLjcxLDAsMCwxLDAsLjE0QTUuNDcsNS40NywwLDAsMSw1Niw2NWEuMTYuMTYsMCwwLDAtLjA5LjE1djQxYS4xOS4xOSwwLDAsMCwuMjguMTZMOTQuNDEsODUuMTFhLjIuMiwwLDAsMCwuMDktLjE3VjQwLjU1YS4xOC4xOCwwLDAsMC0uMjctLjE3WiIvPgogIDxwYXRoIGNsYXNzPSJjbHMtMyIgZD0iTTUxLjA1LDY1LjI5djQxYS4xOC4xOCwwLDAsMS0uMjcuMTZMMTIuMjEsODVhLjIxLjIxLDAsMCwxLS4xLS4xN1Y0MC4yN2EuMTkuMTksMCwwLDEsLjI4LS4xNkw0Ny45LDU5LjgzYzAsLjEzLDAsLjI2LDAsLjM5QTUuNDYsNS40NiwwLDAsMCw1MSw2NS4xMy4xOC4xOCwwLDAsMSw1MS4wNSw2NS4yOVoiLz4KPC9zdmc+" />
    </h1>
	<form method="post" action="options.php" class="pl-nuclia-settings-form">
		<?php
		settings_fields( 'nuclia_settings' );
		do_settings_sections( $this->slug );
		submit_button();
		?>
	</form>
</div>
