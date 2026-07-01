#!/bin/sh
set -eu

if [ ! -d node_modules ]; then
	npm install
fi

npx playwright test -c playwright.config.ts
