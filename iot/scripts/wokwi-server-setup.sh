#!/usr/bin/env bash
# One-shot: point Node-RED at HiveMQ so Wokwi (IS_DEPLOYED=false) and server share one broker.
# Run on course server from repo root: bash iot/scripts/wokwi-server-setup.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-.env}"
touch "$ENV_FILE"

set_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

set_env IOT_MQTT_BROKER broker.hivemq.com
set_env IOT_MQTT_PORT 1883
set_env OAUTH_CLIENT_SECRET GatewaySecretDev123

pip3 install --user paho-mqtt 2>/dev/null || pip3 install paho-mqtt 2>/dev/null || true

docker compose up -d --build mosquitto oauth-server express-gateway php-river node-red rabbitmq redis mysql python-ml-consumer-banjir

echo "Waiting for Node-RED..."
sleep 15
docker logs smartcity-node-red 2>&1 | grep node-red-patch-mqtt || true
curl -sf http://localhost:3530/health | head -c 200 || true
echo ""
echo "Done. Wokwi: IS_DEPLOYED=false, broker.hivemq.com:1883 — then Run both sensors."
