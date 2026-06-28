#!/usr/bin/env bash
# Spec §10 S2 — OAuth login -> submit laporan -> RabbitMQ report.submitted -> citizen notification
set -euo pipefail

GATEWAY="${GATEWAY_URL:-http://localhost:3530}"
CLIENT_ID="${OAUTH_CLIENT_ID:-citizen-app}"
CLIENT_SECRET="${OAUTH_CLIENT_SECRET:-CitizenSecretDev123}"
USERNAME="${OAUTH_USERNAME:-hadiputra2}"
PASSWORD="${OAUTH_PASSWORD:-kelompok2dev}"

pass=0
fail=0

pass_test() { pass=$((pass + 1)); echo "PASS $1"; }
fail_test() { fail=$((fail + 1)); echo "FAIL $1"; }

echo "S2 E2E: OAuth + laporan + notification against ${GATEWAY}"

token_body="$(curl -sS -m 15 -X POST "${GATEWAY}/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "grant_type=password" \
  --data-urlencode "username=${USERNAME}" \
  --data-urlencode "password=${PASSWORD}" \
  --data-urlencode "client_id=${CLIENT_ID}" \
  --data-urlencode "client_secret=${CLIENT_SECRET}")"

access_token="$(node -e "const d=JSON.parse(process.argv[1]); process.stdout.write(d.access_token||'');" "$token_body")"

if [ -n "$access_token" ]; then
  pass_test "OAuth password grant via gateway"
else
  fail_test "OAuth password grant via gateway"
  echo "$token_body"
  exit 1
fi

report_body='{"deskripsiLaporan":"S2 E2E automated test — laporan kerusakan sensor banjir di zona demo."}'
report_resp="$(curl -sS -m 15 -X POST "${GATEWAY}/api/reports" \
  -H "Authorization: Bearer ${access_token}" \
  -H "Content-Type: application/json" \
  -d "$report_body")"

report_id="$(node -e "const d=JSON.parse(process.argv[1]); const row=d.data||{}; process.stdout.write(String(row.id||''));" "$report_resp")"

if [ -n "$report_id" ]; then
  pass_test "POST /api/reports created laporan #${report_id}"
else
  fail_test "POST /api/reports"
  echo "$report_resp"
  exit 1
fi

sleep 3

notif_resp="$(curl -sS -m 15 -X GET "${GATEWAY}/api/notifications" \
  -H "Authorization: Bearer ${access_token}")"

if node -e "
const d=JSON.parse(require('fs').readFileSync(0,'utf8'));
const items=Array.isArray(d.data)?d.data:[];
const hit=items.some(n=>String(n.title||'').includes('Laporan #${report_id}'));
process.exit(hit?0:1);
" <<<"$notif_resp"; then
  pass_test "GET /api/notifications contains laporan notification"
else
  fail_test "GET /api/notifications contains laporan notification (is php-user-notification-consumer running?)"
  echo "$notif_resp"
fi

echo "S2 summary: ${pass} passed, ${fail} failed"
[ "$fail" -eq 0 ]
