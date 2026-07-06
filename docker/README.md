# Progress Agentic RAG Docker Tooling

Run these commands from the plugin root:

```sh
docker compose -f docker/docker-compose.yml run --rm php-lint
docker compose -f docker/docker-compose.yml run --rm phpunit
PROGRESS_AGENTIC_RAG_E2E_KEY=progress-agentic-rag-e2e WP_BASE_URL=http://host.docker.internal:8080 docker compose -f docker/docker-compose.yml run --rm playwright
RELEASE_DIR=build/progress-agentic-rag docker compose -f docker/docker-compose.yml run --rm release-check
```

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

The GitHub Actions deployment workflow publishes to WordPress.org SVN only from numeric release tags that point to `main`, for example `0.1.0`. Do not use a `v` prefix.

Pull requests targeting `main` and manual workflow runs execute the same deployment checks as a dry run: PHP lint, PHPUnit, Playwright, release artifact creation, and release metadata validation all run, but the SVN deploy step is skipped. GitHub's default pull request checkout tests the merge result for the branch being merged.

Configure these repository secrets before pushing a release tag:

```text
SVN_USERNAME
SVN_PASSWORD
```

Release steps:

```sh
git checkout main
git pull --ff-only origin main
# Update progress-agentic-rag.php, PROGRESS_AGENTIC_RAG_VERSION, readme.txt Stable tag, and the changelog heading to the same x.y.z version.
git tag 0.1.0
git push origin 0.1.0
```

To test the deployment pipeline without publishing, open or update a pull request targeting `main`, or run `Deploy to WordPress.org SVN` manually from GitHub Actions.

For local metadata validation against an intended tag, pass `RELEASE_VERSION`:

```sh
RELEASE_VERSION=0.1.0 RELEASE_DIR=build/progress-agentic-rag docker compose -f docker/docker-compose.yml run --rm release-check
```
