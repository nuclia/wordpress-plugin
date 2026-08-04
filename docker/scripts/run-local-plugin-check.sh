#!/bin/sh
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
plugin_root=$(CDPATH= cd -- "$script_dir/../.." && pwd -P)
repo_root=$(CDPATH= cd -- "$plugin_root/../../.." && pwd -P)
build_dir="$plugin_root/build"
release_zip="${PLUGIN_CHECK_ZIP:-$build_dir/progress-agentic-rag-connector-1.0.0.zip}"
extract_dir="$build_dir/plugin-check-extracted"
fixture_dir="$extract_dir/progress-agentic-rag-connector"

cleanup() {
	rm -rf "$extract_dir"
}
trap cleanup EXIT HUP INT TERM

cleanup
if [ ! -f "$release_zip" ]; then
	echo "Plugin release ZIP not found: $release_zip" >&2
	exit 1
fi

mkdir -p "$extract_dir"
unzip -q "$release_zip" -d "$extract_dir"

if [ ! -f "$fixture_dir/progress-agentic-rag.php" ]; then
	echo "Expected plugin root not found in release ZIP: progress-agentic-rag-connector" >&2
	exit 1
fi

RELEASE_DIR="$fixture_dir" sh "$script_dir/check-release.sh"

cd "$repo_root"
WORDPRESS_PORT="${WORDPRESS_PORT:-8080}" WP_URL="${WP_URL:-http://wordpress}" docker compose --profile plugin-check run --rm \
	-v "$fixture_dir:/var/www/html/wp-content/plugins/progress-agentic-rag-connector:ro" \
	plugin-check
