#!/bin/sh
set -eu

release_dir="${RELEASE_DIR:-build/progress-agentic-rag-connector}"
script_dir="$(CDPATH= cd "$(dirname "$0")" && pwd)"

if [ ! -d "$release_dir" ]; then
	echo "Release directory not found: $release_dir"
	exit 1
fi

sh "$script_dir/check-release-metadata.sh"

for path in .git .github .distignore .gitignore .DS_Store docker tests node_modules package.json package-lock.json phpunit.xml.dist playwright.config.ts README.md; do
	if [ -e "$release_dir/$path" ]; then
		echo "Release artifact includes dev-only path: $path"
		exit 1
	fi
done

if [ ! -f "$release_dir/vendor/action-scheduler/action-scheduler.php" ]; then
	echo "Release artifact is missing the bundled Action Scheduler runtime."
	exit 1
fi

unexpected_vendor_path="$(find "$release_dir/vendor" -mindepth 1 -maxdepth 1 ! -name action-scheduler -print -quit)"
if [ -n "$unexpected_vendor_path" ]; then
	echo "Release artifact includes an unexpected vendor path: $unexpected_vendor_path"
	exit 1
fi

for path in README.md readme.txt changelog.txt; do
	if [ -e "$release_dir/vendor/action-scheduler/$path" ]; then
		echo "Release artifact includes unnecessary Action Scheduler documentation: vendor/action-scheduler/$path"
		exit 1
	fi
done

echo "Release artifact excludes dev-only paths and contains only the required Action Scheduler vendor package."
