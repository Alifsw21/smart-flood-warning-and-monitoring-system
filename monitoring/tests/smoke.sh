#!/usr/bin/env bash
# Smoke checks for Prometheus + Grafana (spec §11.1 monitoring bonus panels).
set -euo pipefail

PROMETHEUS_URL="${PROMETHEUS_URL:-http://localhost:9092}"
GRAFANA_URL="${GRAFANA_URL:-http://localhost:3014}"
GATEWAY_URL="${GATEWAY_URL:-http://localhost:3530}"

pass=0
fail=0

assert_ok() {
  local name="$1"
  shift
  if "$@"; then
    echo "PASS $name"
    pass=$((pass + 1))
  else
    echo "FAIL $name"
    fail=$((fail + 1))
  fi
}

assert_metrics() {
  curl -sf "${GATEWAY_URL}/metrics" -o /tmp/gateway-metrics.txt
  grep -q 'gateway_http_requests_total' /tmp/gateway-metrics.txt
}

query_request_rate() {
  curl -sf "${PROMETHEUS_URL}/api/v1/query?query=sum(rate(gateway_http_requests_total%5B5m%5D))" | grep -q '"status":"success"'
}

target_health() {
  local job="$1"
  PROMETHEUS_URL="$PROMETHEUS_URL" python3 - "$job" <<'PY'
import json
import os
import sys
import urllib.request

job = sys.argv[1]
with urllib.request.urlopen(f"{os.environ['PROMETHEUS_URL']}/api/v1/targets") as resp:
    data = json.load(resp)

for target in data.get("data", {}).get("activeTargets", []):
    if target.get("labels", {}).get("job") == job:
        sys.exit(0 if target.get("health") == "up" else 1)

sys.exit(1)
PY
}

assert_ok "prometheus healthy" curl -sf "${PROMETHEUS_URL}/-/healthy" >/dev/null

assert_ok "grafana healthy" curl -sf "${GRAFANA_URL}/api/health" >/dev/null

assert_ok "gateway /metrics reachable" assert_metrics

assert_ok "prometheus scrapes express-gateway" target_health express-gateway

assert_ok "prometheus scrapes cadvisor" target_health cadvisor

assert_ok "gateway request rate query" query_request_rate
echo "----"
echo "monitoring smoke: ${pass} passed, ${fail} failed"
test "$fail" -eq 0
