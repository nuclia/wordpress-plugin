#!/bin/sh
set -eu

release_dir="${RELEASE_DIR:-build/progress-agentic-rag}"
expected_version="${RELEASE_VERSION:-${GITHUB_REF_NAME:-}}"
main_file="$release_dir/progress-agentic-rag.php"
readme_file="$release_dir/readme.txt"

fail() {
	echo "$1"
	exit 1
}

trim() {
	sed 's/^[[:space:]]*//; s/[[:space:]]*$//'
}

validate_release_version() {
	if ! printf '%s\n' "$1" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
		fail "Release version must use numeric x.y.z format without a v prefix: $1"
	fi
}

if [ ! -d "$release_dir" ]; then
	fail "Release directory not found: $release_dir"
fi

if [ ! -f "$main_file" ]; then
	if [ -f "$release_dir/progress-agentic-rag/progress-agentic-rag.php" ]; then
		fail "Release artifact is nested under progress-agentic-rag; WordPress.org trunk must contain plugin files directly."
	fi

	fail "Main plugin file not found in release root: $main_file"
fi

if [ ! -f "$readme_file" ]; then
	fail "readme.txt not found in release root: $readme_file"
fi

plugin_header_version="$(awk -F 'Version:[[:space:]]*' '/^[[:space:]]*\*[[:space:]]Version:/ { print $2; exit }' "$main_file" | tr -d '\r' | trim)"
constant_version="$(awk -F "'" '/PROGRESS_AGENTIC_RAG_VERSION/ { print $4; exit }' "$main_file" | tr -d '\r' | trim)"
stable_tag="$(awk -F 'Stable tag:[[:space:]]*' '/^Stable tag:/ { print $2; exit }' "$readme_file" | tr -d '\r' | trim)"

if [ -z "$plugin_header_version" ]; then
	fail "Plugin header Version is missing."
fi

if [ -z "$constant_version" ]; then
	fail "PROGRESS_AGENTIC_RAG_VERSION constant is missing."
fi

if [ -z "$stable_tag" ]; then
	fail "readme.txt Stable tag is missing."
fi

if [ -z "$expected_version" ]; then
	expected_version="$plugin_header_version"
fi

validate_release_version "$expected_version"

if [ "$plugin_header_version" != "$expected_version" ]; then
	fail "Plugin header Version ($plugin_header_version) does not match release version ($expected_version)."
fi

if [ "$constant_version" != "$expected_version" ]; then
	fail "PROGRESS_AGENTIC_RAG_VERSION ($constant_version) does not match release version ($expected_version)."
fi

if [ "$stable_tag" = "trunk" ]; then
	fail "readme.txt Stable tag must be a numeric release version, not trunk."
fi

if [ "$stable_tag" != "$expected_version" ]; then
	fail "readme.txt Stable tag ($stable_tag) does not match release version ($expected_version)."
fi

if ! grep -Fq "= $expected_version =" "$readme_file"; then
	fail "readme.txt Changelog is missing a heading for $expected_version."
fi

echo "Release metadata matches version $expected_version."
