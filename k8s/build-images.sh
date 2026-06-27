#!/usr/bin/env bash
# Build local Docker images tagged for Kubernetes (Spec §6 / S5).
# Run from repo root: ./k8s/build-images.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

build() {
  local dir="$1"
  local tag="$2"
  echo "==> docker build -t ${tag} ${dir}"
  docker build -t "${tag}" "${dir}"
}

build oauth-server smartcity/oauth-server:latest
build express-gateway smartcity/api-gateway:latest
build php-user smartcity/php-user:latest
build php-river smartcity/php-river:latest
build php-analytics smartcity/php-analytics:latest
docker build -f php-analytics/Dockerfile.consumer -t smartcity/php-analytics-consumer:latest php-analytics
build python-ml-service smartcity/python-ml-service:latest

echo "Done. Images ready for imagePullPolicy: IfNotPresent on the cluster node."
