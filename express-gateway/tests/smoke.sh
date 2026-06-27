#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${GATEWAY_URL:-http://localhost:3000}"
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

echo "Gateway smoke tests against ${BASE_URL}"

assert_status GET /health 200 "GET /health"
assert_status GET /metrics 200 "GET /metrics"
assert_status GET /api/river/zones 401 "GET protected route without token"
assert_status GET /api/river/zones 200 "GET protected route with user token" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status POST /api/auth/login 401 "POST /api/auth/login without token" -H "Content-Type: application/json" -d '{"username":"x","password":"y"}'
assert_status GET /api/sensor 200 "GET /api/sensor with trailing slash fix" -H "Authorization: Bearer ${USER_TOKEN}"
assert_status GET /predict/realtime/banjir 200 "GET ML predict proxy" -H "Authorization: Bearer ${USER_TOKEN}"
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
