#!/bin/sh
set -eu

cd "$(dirname "$0")/../.."
compose_file=docker/docker-compose.scaling.yml

npm ci --quiet --ignore-scripts

# These services and volumes belong only to the isolated scaling project.
trap 'docker compose -f "$compose_file" stop wordpress db >/dev/null' EXIT
docker compose -f "$compose_file" up -d --wait db wordpress
docker compose -f "$compose_file" run --rm setup
docker compose -f "$compose_file" run --rm tests
