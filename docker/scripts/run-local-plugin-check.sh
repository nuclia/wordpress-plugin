#!/bin/sh
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
plugin_root=$(CDPATH= cd -- "$script_dir/../.." && pwd -P)
repo_root=$(CDPATH= cd -- "$plugin_root/../../.." && pwd -P)
build_dir="$plugin_root/build"
fixture_dir="$build_dir/progress-agentic-rag"

cleanup() {
	rm -rf "$fixture_dir"
	rmdir "$build_dir" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM

cleanup
mkdir -p "$fixture_dir"
rsync -a \
	--exclude-from="$plugin_root/.distignore" \
	--exclude='/.distignore' \
	--exclude='/.gitignore' \
	"$plugin_root"/ "$fixture_dir"/

cd "$repo_root"
WORDPRESS_PORT="${WORDPRESS_PORT:-8080}" WP_URL="${WP_URL:-http://wordpress}" docker compose --profile plugin-check run --rm \
	-v "$fixture_dir:/var/www/html/wp-content/plugins/progress-agentic-rag:ro" \
	plugin-check
