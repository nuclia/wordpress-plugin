#!/bin/sh
set -eu

release_dir="${RELEASE_DIR:-build/progress-agentic-rag}"
script_dir="$(CDPATH= cd "$(dirname "$0")" && pwd)"

if [ ! -d "$release_dir" ]; then
	echo "Release directory not found: $release_dir"
	exit 1
fi

sh "$script_dir/check-release-metadata.sh"

for path in .git .github .distignore .gitignore docker tests node_modules vendor package.json package-lock.json phpunit.xml.dist playwright.config.ts; do
	if [ -e "$release_dir/$path" ]; then
		echo "Release artifact includes dev-only path: $path"
		exit 1
	fi
done

echo "Release artifact excludes dev-only paths."
