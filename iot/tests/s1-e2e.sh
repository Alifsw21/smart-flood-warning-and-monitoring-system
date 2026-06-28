#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
ML_DIR="$(cd "$DIR/../../python-ml-service" && pwd)"

export MQTT_HOST="${MQTT_HOST:-localhost}"
export MQTT_PORT="${MQTT_PORT:-1883}"
export MQTT_TOPIC_PREFIX="${MQTT_TOPIC_PREFIX:-kelompok2/sensors}"
export MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
export MYSQL_PORT="${MYSQL_PORT:-3307}"
export MYSQL_DATABASE="${MYSQL_DATABASE:-kelompok2}"
export RIVER_DB_USER="${RIVER_DB_USER:-river}"
export RIVER_DB_PASSWORD="${RIVER_DB_PASSWORD:-RiverSecret}"
export REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
export REDIS_PORT="${REDIS_PORT:-6379}"

echo "S1 IoT E2E tests against MQTT ${MQTT_HOST}:${MQTT_PORT}"

cd "$ML_DIR"
python3 ../iot/tests/s1_e2e.py
