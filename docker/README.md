# Progress Agentic RAG Docker Tooling

Run these commands from the plugin root:

```sh
docker compose -f docker/docker-compose.yml run --rm php-lint
docker compose -f docker/docker-compose.yml run --rm phpunit
docker compose -f docker/docker-compose.yml run --rm playwright
RELEASE_DIR=build/progress-agentic-rag docker compose -f docker/docker-compose.yml run --rm release-check
```

`PROGRESS_AGENTIC_RAG_DISABLE_SCHEDULER=1` is reserved for test environments that need to disable future background scheduling.
