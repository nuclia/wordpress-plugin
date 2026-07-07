# Progress Agentic RAG Docker Tooling

Run these commands from the plugin root:

```sh
docker compose -f docker/docker-compose.yml run --rm php-lint
docker compose -f docker/docker-compose.yml run --rm phpunit
PROGRESS_AGENTIC_RAG_E2E_KEY=progress-agentic-rag-e2e WP_BASE_URL=http://host.docker.internal:8080 docker compose -f docker/docker-compose.yml run --rm playwright
RELEASE_DIR=build/progress-agentic-rag docker compose -f docker/docker-compose.yml run --rm release-check
```

Plugin Check needs the repository-root WordPress stack. From the repository root, after bootstrap:

```sh
docker compose --profile plugin-check run --rm plugin-check
```

Use the same `WORDPRESS_PORT` and `WP_URL` prefix if the root WordPress stack is running on a custom port.

Before running Playwright against the repository-root WordPress stack, install the test-only mu-plugin and shared key:

```sh
docker compose exec wordpress sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cp /var/www/html/wp-content/plugins/progress-agentic-rag/tests/playwright/fixtures/mu-plugins/progress-agentic-rag-e2e.php /var/www/html/wp-content/mu-plugins/progress-agentic-rag-e2e.php'
docker compose run --rm bootstrap
docker compose run --rm --entrypoint sh bootstrap -c '\
wp option update home http://host.docker.internal:8080 --allow-root --path=/var/www/html --url=http://localhost:8080 && \
wp option update siteurl http://host.docker.internal:8080 --allow-root --path=/var/www/html --url=http://localhost:8080 && \
wp option update progress_agentic_rag_e2e_key progress-agentic-rag-e2e --allow-root --path=/var/www/html --url=http://localhost:8080'
```

Run `npm ci` from the plugin root after dependency changes.

`PROGRESS_AGENTIC_RAG_DISABLE_SCHEDULER=1` is reserved for test environments that need to disable future background scheduling.

## WordPress.org deployment

The GitHub Actions deployment workflow publishes to WordPress.org SVN on pushes to `main`. The workflow uses the numeric `x.y.z` version from `progress-agentic-rag.php` as the WordPress.org SVN tag.

All pull requests run the `Tests` workflow only: PHP lint, PHPUnit, Playwright, release artifact creation, release metadata validation, and Plugin Check's Plugin repo category. The deploy workflow does not run on pull requests.

Configure these repository secrets before merging a release to `main`:

```text
SVN_USERNAME
SVN_PASSWORD
```

Release steps:

```sh
git checkout main
git pull --ff-only origin main
# Update progress-agentic-rag.php, PROGRESS_AGENTIC_RAG_VERSION, readme.txt Stable tag, and the changelog heading to the same x.y.z version.
git push origin main
```

To test the deployment pipeline without publishing, run `Deploy to WordPress.org SVN` manually from GitHub Actions. Manual runs execute the deployment checks and skip the SVN deploy step.

For local metadata validation against an intended tag, pass `RELEASE_VERSION`:

```sh
RELEASE_VERSION=0.1.0 RELEASE_DIR=build/progress-agentic-rag docker compose -f docker/docker-compose.yml run --rm release-check
```
