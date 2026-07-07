#!/bin/sh
set -eu

wp_path="${WP_PATH:-/var/www/html}"
wp_url="${WP_URL:-http://wordpress}"
plugin_check_version="${PLUGIN_CHECK_VERSION:-2.0.0}"
plugin_check_target="${PLUGIN_CHECK_TARGET:-progress-agentic-rag/progress-agentic-rag.php}"
plugin_check_slug="${PLUGIN_CHECK_SLUG:-progress-agentic-rag}"
plugin_check_categories="${PLUGIN_CHECK_CATEGORIES:-plugin_repo}"
plugin_check_mode="${PLUGIN_CHECK_MODE:-new}"
plugin_check_output="$(mktemp)"

cleanup() {
	rm -f "$plugin_check_output"
}
trap cleanup EXIT

wp_cli() {
	wp --allow-root --path="$wp_path" --url="$wp_url" "$@"
}

install_plugin_check() {
	attempt=1
	max_attempts=5
	delay=5

	while true; do
		if wp_cli plugin install plugin-check --version="$plugin_check_version" --activate --force; then
			return 0
		fi

		if [ "$attempt" -ge "$max_attempts" ]; then
			echo "Unable to install Plugin Check $plugin_check_version after $attempt attempts." >&2
			return 1
		fi

		attempt=$(( attempt + 1 ))
		echo "Plugin Check install failed; retrying in $delay seconds (attempt $attempt of $max_attempts)." >&2
		sleep "$delay"
		delay=$(( delay * 2 ))
	done
}

if ! wp_cli core is-installed >/dev/null 2>&1; then
	echo "WordPress is not installed at $wp_path."
	exit 1
fi

if ! wp_cli plugin is-installed plugin-check >/dev/null 2>&1; then
	install_plugin_check
else
	wp_cli plugin activate plugin-check >/dev/null
fi

plugin_check_cli="$wp_path/wp-content/plugins/plugin-check/cli.php"
if [ ! -f "$plugin_check_cli" ]; then
	echo "Plugin Check CLI loader not found: $plugin_check_cli"
	exit 1
fi

set +e
wp_cli plugin check "$plugin_check_target" \
	--require="$plugin_check_cli" \
	--categories="$plugin_check_categories" \
	--format=strict-json \
	--fields=file,line,column,type,code,message,docs \
	--mode="$plugin_check_mode" \
	--slug="$plugin_check_slug" \
	--exclude-directories=includes/libraries/action-scheduler \
	> "$plugin_check_output"
plugin_check_status=$?
set -e

if [ "$plugin_check_status" -ne 0 ]; then
	cat "$plugin_check_output"
	exit "$plugin_check_status"
fi

php -r '
$output = trim( file_get_contents( $argv[1] ) );

if ( "" === $output ) {
	fwrite( STDERR, "Plugin Check produced no output.\n" );
	exit( 2 );
}

if ( 0 === strpos( $output, "Success:" ) ) {
	echo $output . PHP_EOL;
	exit( 0 );
}

$start = strpos( $output, "[" );
$end   = strrpos( $output, "]" );

if ( false !== $start && false !== $end && $end >= $start ) {
	$output = substr( $output, $start, $end - $start + 1 );
}

$results = json_decode( $output, true );

if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $results ) ) {
	fwrite( STDERR, "Unable to parse Plugin Check strict JSON output.\n" );
	echo $output . PHP_EOL;
	exit( 2 );
}

$count = count( $results );

if ( $count > 0 ) {
	fwrite( STDERR, sprintf( "Plugin Check found %d Plugin repo issue(s).\n", $count ) );

	$types = array();
	$codes = array();

	foreach ( $results as $result ) {
		$type = isset( $result["type"] ) ? $result["type"] : "unknown";
		$code = isset( $result["code"] ) ? $result["code"] : "unknown";

		$types[ $type ] = isset( $types[ $type ] ) ? $types[ $type ] + 1 : 1;
		$codes[ $code ] = isset( $codes[ $code ] ) ? $codes[ $code ] + 1 : 1;
	}

	arsort( $types );
	arsort( $codes );

	fwrite( STDERR, "Issue types:\n" );
	foreach ( $types as $type => $type_count ) {
		fwrite( STDERR, sprintf( "  %s: %d\n", $type, $type_count ) );
	}

	fwrite( STDERR, "Top issue codes:\n" );
	$shown = 0;
	foreach ( $codes as $code => $code_count ) {
		fwrite( STDERR, sprintf( "  %d %s\n", $code_count, $code ) );
		++$shown;
		if ( 10 <= $shown ) {
			break;
		}
	}

	fwrite( STDERR, "First findings:\n" );
	foreach ( array_slice( $results, 0, 25 ) as $result ) {
		$file    = isset( $result["file"] ) ? $result["file"] : "(unknown file)";
		$line    = isset( $result["line"] ) ? $result["line"] : 0;
		$type    = isset( $result["type"] ) ? $result["type"] : "unknown";
		$code    = isset( $result["code"] ) ? $result["code"] : "unknown";
		$message = isset( $result["message"] ) ? html_entity_decode( $result["message"], ENT_QUOTES ) : "";

		fwrite( STDERR, sprintf( "  %s:%s %s %s - %s\n", $file, $line, $type, $code, $message ) );
	}

	exit( 1 );
}
' "$plugin_check_output"

echo "Plugin Check found no Plugin repo issues."
