#!/bin/sh
set -eu

phpunit_phar="${PHPUNIT_PHAR:-/tmp/phpunit-10.5.63.phar}"
phpunit_url="${PHPUNIT_PHAR_URL:-https://phar.phpunit.de/phpunit-10.5.63.phar}"

if [ ! -f "$phpunit_phar" ]; then
	php -r '$url = $argv[1]; $target = $argv[2]; if (! copy($url, $target)) { exit(1); }' "$phpunit_url" "$phpunit_phar"
fi

php "$phpunit_phar" -c phpunit.xml.dist
