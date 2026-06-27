#!/usr/bin/env bash
set -euo pipefail

GATEWAY="${GATEWAY_URL:-http://localhost:3000}"
ML="${ML_URL:-http://localhost:5001}"
CITIZEN_SECRET="${CITIZEN_CLIENT_SECRET:-CitizenSecretDev123}"
BANJIR_BODY='{"idSungai":1,"curahHujan":12,"tinggiAir":2.5,"kelembapanTanah":60,"suhuMin":25,"suhuMax":32,"suhuRataRata":28,"kelembapanUdara":77,"sunShine":5,"kecepatanAngin":10,"arahAngin":180,"kecepatanRataRataAngin":8}'
CURAH_BODY='{"idNode":1,"tinggiAir":1.2,"kelembapanTanah":55,"suhuMin":24,"suhuMax":31,"suhuRataRata":27,"kelembapanUdara":70,"sunShine":6,"kecepatanAngin":9,"arahAngin":90,"kecepatanRataRataAngin":7}'

pass=0
fail=0

assert_eq() {
  local description="$1"
  local actual="$2"
  local expected="$3"

  if [ "$actual" = "$expected" ]; then
    pass=$((pass + 1))
    echo "PASS $description"
  else
    fail=$((fail + 1))
    echo "FAIL $description (expected $expected, got $actual)"
  fi
}

assert_node() {
  local description="$1"
  local body="$2"
  local script="$3"

  if printf '%s' "$body" | node -e "$script"; then
    pass=$((pass + 1))
    echo "PASS $description"
  else
    fail=$((fail + 1))
    echo "FAIL $description"
    printf '%s\n' "$body"
  fi
}

echo "S3 ML E2E tests (spec §10 S3, §7.4, §11) against ${GATEWAY}"

token_body="$(curl -sS -m 10 -X POST "${GATEWAY}/oauth/token" \
  -H "Content-Type: application/json" \
  -d "{\"grant_type\":\"password\",\"client_id\":\"citizen-app\",\"client_secret\":\"${CITIZEN_SECRET}\",\"username\":\"hadiputra2\",\"password\":\"kelompok2dev\"}")"

ACCESS_TOKEN="$(printf '%s' "$token_body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.stdout.write(d.access_token||'');")"

if [ -n "$ACCESS_TOKEN" ]; then
  pass=$((pass + 1))
  echo "PASS S3 OAuth password grant via gateway"
else
  fail=$((fail + 1))
  echo "FAIL S3 OAuth password grant via gateway"
  echo "$token_body"
fi

banjir_tmp="$(mktemp)"
banjir_time="$(curl -sS -m 10 -o "$banjir_tmp" -w "%{http_code} %{time_total}" -X POST "${GATEWAY}/predict/banjir" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "$BANJIR_BODY")"
banjir_http="${banjir_time%% *}"
banjir_elapsed="${banjir_time##* }"
banjir_body="$(cat "$banjir_tmp")"
rm -f "$banjir_tmp"

assert_eq "S3 POST /predict/banjir via gateway with OAuth token" "$banjir_http" "200"
assert_node "S3 banjir response status=success" "$banjir_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'?0:1)"
assert_node "S3 banjir response has data.hasil_prediksi" "$banjir_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data&&d.data.hasil_prediksi?0:1)"
assert_node "S3 banjir response has timestamp" "$banjir_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.timestamp?0:1)"
assert_node "S3 banjir response has service=python-ml-service" "$banjir_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.service==='python-ml-service'?0:1)"

if awk "BEGIN { exit !($banjir_elapsed < 0.5) }"; then
  pass=$((pass + 1))
  echo "PASS S3 banjir latency under 500ms (${banjir_elapsed}s)"
else
  fail=$((fail + 1))
  echo "FAIL S3 banjir latency under 500ms (${banjir_elapsed}s)"
fi

curah_http="$(curl -sS -m 10 -o /tmp/s3-curah-body.json -w "%{http_code}" -X POST "${GATEWAY}/predict/curah-hujan" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "$CURAH_BODY")"
curah_body="$(cat /tmp/s3-curah-body.json)"
rm -f /tmp/s3-curah-body.json

assert_eq "S3 POST /predict/curah-hujan via gateway with OAuth token" "$curah_http" "200"
assert_node "S3 curah-hujan response has data.estimasi_curah_hujan_mm" "$curah_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data&&typeof d.data.estimasi_curah_hujan_mm==='number'?0:1)"

no_auth_http="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X POST "${GATEWAY}/predict/banjir" \
  -H "Content-Type: application/json" \
  -d "$BANJIR_BODY")"
assert_eq "POST /predict/banjir without token returns 401" "$no_auth_http" "401"

health_body="$(curl -sS -m 10 "${ML}/health")"
assert_node "ML /health lists 3 loaded models" "$health_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(Array.isArray(d.models)&&d.models.length===3?0:1)"

gw_health_body="$(curl -sS -m 10 "${GATEWAY}/health")"
assert_node "Gateway /health reports python-ml-service up" "$gw_health_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); const item=(d.data&&d.data.upstreams||[]).find(s=>s.service==='python-ml-service'); process.exit(item&&item.status==='up'?0:1)"

get_rt_http="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X GET "${GATEWAY}/predict/realtime/banjir" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}")"
assert_eq "GET /predict/realtime/banjir regression via gateway" "$get_rt_http" "200"

total=$((pass + fail))
echo "${pass}/${total} S3 ML E2E tests passed"

if [ "$fail" -gt 0 ]; then
  exit 1
fi
