#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${GATEWAY_URL:-http://localhost:3530}"
JWT_SECRET="${JWT_SECRET:-dev-jwt-secret-change-me}"
GATEWAY_DIR="$(cd "$(dirname "$0")/.." && pwd)"

pass=0
fail=0

assert_status() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local description="$4"
  shift 4

  local actual
  actual="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X "$method" "$@" "${BASE_URL}${path}")"

  if [ "$actual" = "$expected" ]; then
    pass=$((pass + 1))
    echo "PASS $description ($actual)"
  else
    fail=$((fail + 1))
    echo "FAIL $description (expected $expected, got $actual)"
  fi
}

USER_TOKEN="$(cd "$GATEWAY_DIR" && node -e "const jwt=require('jsonwebtoken'); console.log(jwt.sign({id:2,role:'user'}, process.argv[1], {expiresIn:'1h'}));" "$JWT_SECRET")"
ADMIN_TOKEN="$(cd "$GATEWAY_DIR" && node -e "const jwt=require('jsonwebtoken'); console.log(jwt.sign({id:1,role:'admin'}, process.argv[1], {expiresIn:'1h'}));" "$JWT_SECRET")"
IOT_BODY='{"idNode":1,"tinggiAir":12,"kelembapanTanah":55,"curahHujan":6,"suhuRataRata":29,"kelembapanUdara":72,"kecepatanAngin":8}'

BANJIR_BODY='{"idSungai":1,"curahHujan":12,"tinggiAir":2.5,"kelembapanTanah":60,"suhuMin":25,"suhuMax":32,"suhuRataRata":28,"kelembapanUdara":77,"sunShine":5,"kecepatanAngin":10,"arahAngin":180,"kecepatanRataRataAngin":8}'
CURAH_HUJAN_BODY='{"idNode":1,"tinggiAir":1.2,"kelembapanTanah":55,"suhuMin":24,"suhuMax":31,"suhuRataRata":27,"kelembapanUdara":70,"sunShine":6,"kecepatanAngin":9,"arahAngin":90,"kecepatanRataRataAngin":7}'

BATCH_BODY='{"items":[{"type":"traffic","payload":{"idSungai":1,"curahHujan":12,"tinggiAir":2.5,"kelembapanTanah":60,"suhuMin":25,"suhuMax":32,"suhuRataRata":28,"kelembapanUdara":77,"sunShine":5,"kecepatanAngin":10,"arahAngin":180,"kecepatanRataRataAngin":8}}]}'
ANOMALY_BODY='{"sensor_value":12.5,"timestamp_hour":14,"rolling_mean_1h":4.0,"z_score":2.8}'

echo "Gateway smoke tests against ${BASE_URL}"

assert_status GET /health 200 "GET /health"
assert_status GET /metrics 200 "GET /metrics"
assert_status GET /api/river/zones 401 "GET protected route without token"
assert_status GET /api/river/zones 200 "GET protected route with user token" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status POST /api/auth/login 401 "POST /api/auth/login without token" -H "Content-Type: application/json" -d '{"username":"x","password":"y"}'
assert_status GET /api/sensor 200 "GET /api/sensor with trailing slash fix" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status GET /predict/realtime/banjir 200 "GET ML predict proxy" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status POST /predict/banjir 200 "POST /predict/banjir via gateway" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d "$BANJIR_BODY"
assert_status POST /predict/curah-hujan 200 "POST /predict/curah-hujan via gateway" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d "$CURAH_HUJAN_BODY"
assert_status POST /predict/traffic 200 "POST /predict/traffic spec alias via gateway" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d "$BANJIR_BODY"
assert_status POST /predict/air-quality 200 "POST /predict/air-quality spec alias via gateway" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d "$CURAH_HUJAN_BODY"
assert_status POST /detect/anomaly 200 "POST /detect/anomaly via gateway" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d "$ANOMALY_BODY"
assert_status GET /model/feature-importance 200 "GET /model/feature-importance via gateway" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status POST /predict/batch 200 "POST /predict/batch via gateway" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d "$BATCH_BODY"
assert_status GET /api/notifications 200 "GET /api/notifications via gateway" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status GET /api/flood-history 200 "GET /api/flood-history via gateway" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status GET /api/citizens 403 "GET /api/citizens alias as user returns 403" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status GET /api/citizens 200 "GET /api/citizens alias as admin" -H "Authorization: Bearer ${ADMIN_TOKEN}"
assert_status POST /iot/traffic 201 "POST /iot/traffic IoT ingress" -H "Authorization: Bearer ${ADMIN_TOKEN}" -H "Content-Type: application/json" -d "$IOT_BODY"
assert_status POST /iot/air 201 "POST /iot/air IoT ingress" -H "Authorization: Bearer ${ADMIN_TOKEN}" -H "Content-Type: application/json" -d "$IOT_BODY"
assert_status POST /api/river/zones 403 "POST admin route as user" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d '{"nama_kota":"Denied City"}'

metrics_body="$(curl -sS -m 10 "${BASE_URL}/metrics")"
if printf '%s' "$metrics_body" | grep -q 'gateway_http_requests_total'; then
  pass=$((pass + 1))
  echo "PASS /metrics exposes gateway_http_requests_total"
else
  fail=$((fail + 1))
  echo "FAIL /metrics missing gateway_http_requests_total"
fi

total=$((pass + fail))
echo "${pass}/${total} gateway smoke tests passed"

if [ "$fail" -gt 0 ]; then
  exit 1
fi
