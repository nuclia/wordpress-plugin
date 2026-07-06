#!/bin/sh
set -eu

if [ ! -d node_modules ]; then
	npm ci
fi

npx playwright test -c playwright.config.ts
