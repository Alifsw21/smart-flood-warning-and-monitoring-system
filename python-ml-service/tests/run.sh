#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR/.."

if [ ! -f models/deteksi_banjir_berdasarkan_waterLevel.pkl ]; then
  echo "Training ML models (missing .pkl files)..."
  python3 trains/train_model_water_level.py
  python3 trains/train_model_cuaca.py
  python3 trains/train_model_curah_hujan.py
fi

python3 - <<'PY'
from predictions import BanjirInput, CurahHujanInput, load_models, predict_banjir, predict_curah_hujan

load_models()
banjir = predict_banjir(BanjirInput(
    idSungai=1,
    curahHujan=12,
    tinggiAir=2.5,
    kelembapanTanah=60,
))
assert banjir["data"]["hasil_prediksi"] in {"NORMAL", "WASPADA", "BENCANA"}

curah = predict_curah_hujan(CurahHujanInput(
    idNode=1,
    tinggiAir=1.2,
    kelembapanTanah=55,
))
assert curah["data"]["estimasi_curah_hujan_mm"] >= 0
print("PASS unit prediction helpers")
PY

if [ -z "${ML_URL:-}" ]; then
  TEST_PORT="${ML_TEST_PORT:-18080}"
  export ML_URL="http://127.0.0.1:${TEST_PORT}"
  uvicorn main:app --host 127.0.0.1 --port "$TEST_PORT" >/tmp/python-ml-test.log 2>&1 &
  server_pid=$!
  trap 'kill "$server_pid" >/dev/null 2>&1 || true' EXIT

  for _ in $(seq 1 30); do
    if curl -sS -m 2 "${ML_URL}/health" >/dev/null 2>&1; then
      break
    fi
    sleep 0.5
  done
fi

bash tests/http.sh
