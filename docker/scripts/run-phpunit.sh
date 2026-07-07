#!/bin/sh
set -eu

phpunit_phar="${PHPUNIT_PHAR:-/tmp/phpunit-10.5.63.phar}"
phpunit_url="${PHPUNIT_PHAR_URL:-https://phar.phpunit.de/phpunit-10.5.63.phar}"

if [ ! -f "$phpunit_phar" ]; then
	php -r '$url = $argv[1]; $target = $argv[2]; if (! copy($url, $target)) { exit(1); }' "$phpunit_url" "$phpunit_phar"
fi

if [ "${PHPUNIT_COVERAGE:-0}" = "1" ]; then
	if ! php -m | grep -qi '^pcov$'; then
		printf "\n" | pecl install pcov-1.0.11 >/tmp/progress-agentic-rag-pcov-install.log
		docker-php-ext-enable pcov >/tmp/progress-agentic-rag-pcov-enable.log
	fi

	mkdir -p build/coverage
	php -d pcov.enabled=1 -d pcov.directory=/plugin/src "$phpunit_phar" -c phpunit.xml.dist --testdox --coverage-text --coverage-clover build/coverage/clover.xml
	exit
fi

php "$phpunit_phar" -c phpunit.xml.dist --testdox
