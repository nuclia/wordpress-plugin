#!/bin/sh
set -eu

find . \
	-path './vendor' -prune -o \
	-path './node_modules' -prune -o \
	-name '*.php' -print0 | xargs -0 -n1 php -l
