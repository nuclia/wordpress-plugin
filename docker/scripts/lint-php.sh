#!/bin/sh
set -eu

find . \
	-path './node_modules' -prune -o \
	-name '*.php' -print0 | xargs -0 -n1 php -l
