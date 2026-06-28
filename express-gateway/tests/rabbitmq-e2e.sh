#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
ML_DIR="$(cd "$DIR/../../python-ml-service" && pwd)"

export RABBITMQ_HOST="${RABBITMQ_HOST:-localhost}"
export RABBITMQ_PORT="${RABBITMQ_PORT:-5672}"
export RABBITMQ_USER="${RABBITMQ_USER:-smartcity}"
export RABBITMQ_PASSWORD="${RABBITMQ_PASSWORD:-RabbitSecret}"
export RABBITMQ_VHOST="${RABBITMQ_VHOST:-/}"
export MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
export MYSQL_PORT="${MYSQL_PORT:-3350}"
export MYSQL_DATABASE="${MYSQL_DATABASE:-kelompok2}"
export ANALYTICS_DB_USER="${ANALYTICS_DB_USER:-analytics}"
export ANALYTICS_DB_PASSWORD="${ANALYTICS_DB_PASSWORD:-AnalyticSecret}"
export RIVER_DB_USER="${RIVER_DB_USER:-river}"
export RIVER_DB_PASSWORD="${RIVER_DB_PASSWORD:-RiverSecret}"

echo "RabbitMQ E2E tests (spec §4.5, S6) against ${RABBITMQ_HOST}:${RABBITMQ_PORT}"

cd "$ML_DIR"
python3 tests/rabbitmq_e2e.py
