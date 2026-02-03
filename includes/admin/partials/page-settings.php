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

<div class="wrap">
	<h1>
    	<?php echo esc_html( get_admin_page_title() ); ?>
    	&nbsp;<img width="24" height="24" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPHN2ZyB2aWV3Qm94PSI3LjE0OCAxMy40NTYgOTEuMDM1IDk0LjAzNyIgd2lkdGg9IjkxLjAzNSIgaGVpZ2h0PSI5NC4wMzciIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CiAgPGRlZnM+CiAgICA8c3R5bGU+LmNscy0xe2ZpbGw6I2ZmZDkxYjt9LmNscy0ye2ZpbGw6IzI1MDBmZjt9LmNscy0ze2ZpbGw6I2ZmMDA2YTt9PC9zdHlsZT4KICA8L2RlZnM+CiAgPHBhdGggY2xhc3M9ImNscy0xIiBkPSJNOTEuNjYsMzUuNzgsNTMuNDcsMTQuNDNhLjE5LjE5LDAsMCwwLS4xOCwwTDE0Ljk0LDM1LjQ5YS4xOS4xOSwwLDAsMCwwLC4zM0w1MC40LDU1LjUyYS4xOS4xOSwwLDAsMCwuMTgsMCw1LjQ3LDUuNDcsMCwwLDEsNS43MS4xMy4xNy4xNywwLDAsMCwuMTgsMEw5MS42NiwzNi4xMUEuMTkuMTksMCwwLDAsOTEuNjYsMzUuNzhaIi8+CiAgPHBhdGggY2xhc3M9ImNscy0yIiBkPSJNNTguNzcsNjAuMDhhLjcxLjcxLDAsMCwxLDAsLjE0QTUuNDcsNS40NywwLDAsMSw1Niw2NWEuMTYuMTYsMCwwLDAtLjA5LjE1djQxYS4xOS4xOSwwLDAsMCwuMjguMTZMOTQuNDEsODUuMTFhLjIuMiwwLDAsMCwuMDktLjE3VjQwLjU1YS4xOC4xOCwwLDAsMC0uMjctLjE3WiIvPgogIDxwYXRoIGNsYXNzPSJjbHMtMyIgZD0iTTUxLjA1LDY1LjI5djQxYS4xOC4xOCwwLDAsMS0uMjcuMTZMMTIuMjEsODVhLjIxLjIxLDAsMCwxLS4xLS4xN1Y0MC4yN2EuMTkuMTksMCwwLDEsLjI4LS4xNkw0Ny45LDU5LjgzYzAsLjEzLDAsLjI2LDAsLjM5QTUuNDYsNS40NiwwLDAsMCw1MSw2NS4xMy4xOC4xOCwwLDAsMSw1MS4wNSw2NS4yOVoiLz4KPC9zdmc+" />
    </h1>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'nuclia_settings' );
		do_settings_sections( $this->slug );
		submit_button();
		?>
	</form>
</div>
