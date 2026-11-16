#!/bin/sh
set -eu

cd /var/www/html
if ! wp core is-installed --allow-root; then
	wp core install --allow-root --url=http://wordpress --title="RAG scaling tests" \
		--admin_user=admin --admin_password=scaling-only --admin_email=scaling@example.test --skip-email
fi
wp plugin activate progress-agentic-rag --allow-root --url=http://wordpress
wp option update progress_agentic_rag_e2e_key scaling-only --allow-root --url=http://wordpress
