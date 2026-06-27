#!/usr/bin/env bash
set -euo pipefail

SERVICE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${ML_URL:-http://localhost:5001}"

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

BANJIR_BODY='{"idSungai":1,"curahHujan":12,"tinggiAir":2.5,"kelembapanTanah":60,"suhuMin":25,"suhuMax":32,"suhuRataRata":28,"kelembapanUdara":77,"sunShine":5,"kecepatanAngin":10,"arahAngin":180,"kecepatanRataRataAngin":8}'
CURAH_HUJAN_BODY='{"idNode":1,"tinggiAir":1.2,"kelembapanTanah":55,"suhuMin":24,"suhuMax":31,"suhuRataRata":27,"kelembapanUdara":70,"sunShine":6,"kecepatanAngin":9,"arahAngin":90,"kecepatanRataRataAngin":7}'

echo "Python ML HTTP tests against ${BASE_URL}"

assert_status GET /health 200 "GET /health"
assert_status POST /predict/banjir 200 "POST /predict/banjir" -H "Content-Type: application/json" -d "$BANJIR_BODY"
assert_status POST /predict/curah-hujan 200 "POST /predict/curah-hujan" -H "Content-Type: application/json" -d "$CURAH_HUJAN_BODY"
assert_status POST /predict/banjir 422 "POST /predict/banjir invalid payload" -H "Content-Type: application/json" -d '{"idSungai":0}'

assert_status POST /predict/traffic 200 "POST /predict/traffic alias" -H "Content-Type: application/json" -d "$BANJIR_BODY"
assert_status POST /predict/air-quality 200 "POST /predict/air-quality alias" -H "Content-Type: application/json" -d "$CURAH_HUJAN_BODY"
assert_status POST /detect/anomaly 200 "POST /detect/anomaly" -H "Content-Type: application/json" -d '{"sensor_value":12.5,"timestamp_hour":14,"rolling_mean_1h":4.0,"z_score":2.8}'
assert_status GET /model/feature-importance 200 "GET /model/feature-importance"

batch_body='{"items":[{"type":"traffic","payload":{"idSungai":1,"curahHujan":12,"tinggiAir":2.5,"kelembapanTanah":60,"suhuMin":25,"suhuMax":32,"suhuRataRata":28,"kelembapanUdara":77,"sunShine":5,"kecepatanAngin":10,"arahAngin":180,"kecepatanRataRataAngin":8}},{"type":"air-quality","payload":{"idNode":1,"tinggiAir":1.2,"kelembapanTanah":55,"suhuMin":24,"suhuMax":31,"suhuRataRata":27,"kelembapanUdara":70,"sunShine":6,"kecepatanAngin":9,"arahAngin":90,"kecepatanRataRataAngin":7}}]}'
assert_status POST /predict/batch 200 "POST /predict/batch" -H "Content-Type: application/json" -d "$batch_body"

banjir_body="$(curl -sS -m 10 -X POST "${BASE_URL}/predict/banjir" -H "Content-Type: application/json" -d "$BANJIR_BODY")"
if printf '%s' "$banjir_body" | grep -q '"hasil_prediksi"'; then
  pass=$((pass + 1))
  echo "PASS POST /predict/banjir returns hasil_prediksi"
else
  fail=$((fail + 1))
  echo "FAIL POST /predict/banjir missing hasil_prediksi"
fi

curah_body="$(curl -sS -m 10 -X POST "${BASE_URL}/predict/curah-hujan" -H "Content-Type: application/json" -d "$CURAH_HUJAN_BODY")"
if printf '%s' "$curah_body" | grep -q '"estimasi_curah_hujan_mm"'; then
  pass=$((pass + 1))
  echo "PASS POST /predict/curah-hujan returns estimasi_curah_hujan_mm"
else
  fail=$((fail + 1))
  echo "FAIL POST /predict/curah-hujan missing estimasi_curah_hujan_mm"
fi

total=$((pass + fail))
echo "${pass}/${total} python-ml-service HTTP tests passed"

if [ "$fail" -gt 0 ]; then
  exit 1
fi
