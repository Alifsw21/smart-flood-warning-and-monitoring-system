#!/usr/bin/env bash
# Full php-analytics smoke test — direct service, gateway, seed data, RabbitMQ consumer (Spec §4.3, §7.3, S6).
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$DIR/../.." && pwd)"
GATEWAY_DIR="$ROOT/express-gateway"

BASE="${ANALYTICS_URL:-http://localhost:8003}"
GATEWAY="${GATEWAY_URL:-http://localhost:3000}"
JWT_SECRET="${JWT_SECRET:-dev-jwt-secret-change-me}"
CITIZEN_SECRET="${CITIZEN_CLIENT_SECRET:-CitizenSecretDev123}"

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3307}"
MYSQL_DATABASE="${MYSQL_DATABASE:-kelompok2}"
ANALYTICS_DB_USER="${ANALYTICS_DB_USER:-analytics}"
ANALYTICS_DB_PASSWORD="${ANALYTICS_DB_PASSWORD:-AnalyticSecret}"

RABBITMQ_HOST="${RABBITMQ_HOST:-localhost}"
RABBITMQ_PORT="${RABBITMQ_PORT:-5672}"
RABBITMQ_USER="${RABBITMQ_USER:-smartcity}"
RABBITMQ_PASSWORD="${RABBITMQ_PASSWORD:-RabbitSecret}"

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

curl_json() {
  local method="$1"
  local url="$2"
  shift 2
  curl -sS -m 15 -X "$method" "$url" "$@"
}

USER_TOKEN="$(cd "$GATEWAY_DIR" && node -e "const jwt=require('jsonwebtoken'); console.log(jwt.sign({id:2,role:'user'}, process.argv[1], {expiresIn:'1h'}));" "$JWT_SECRET")"
ADMIN_TOKEN="$(cd "$GATEWAY_DIR" && node -e "const jwt=require('jsonwebtoken'); console.log(jwt.sign({id:1,role:'admin'}, process.argv[1], {expiresIn:'1h'}));" "$JWT_SECRET")"

echo "php-analytics smoke tests"
echo "  direct:  ${BASE}"
echo "  gateway: ${GATEWAY}"

# --- readiness ---
for i in $(seq 1 30); do
  code="$(curl -sS -m 3 -o /dev/null -w "%{http_code}" "${BASE}/health" || true)"
  if [ "$code" = "200" ]; then
    break
  fi
  sleep 2
done

health_code="$(curl -sS -m 10 -o /tmp/php-analytics-health.json -w "%{http_code}" "${BASE}/health")"
health_body="$(cat /tmp/php-analytics-health.json)"
assert_eq "GET /health returns 200" "$health_code" "200"
assert_node "GET /health status=success" "$health_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'&&d.service==='php-analytics'?0:1)"
assert_node "GET /health db connected" "$health_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data&&d.data.db==='connected'?0:1)"

# --- routing errors ---
assert_eq "GET unknown path 404" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" "${BASE}/nope")" "404"
assert_eq "POST /api/analytics/peringatan 405" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X POST "${BASE}/api/analytics/peringatan")" "405"
assert_eq "POST /api/environment/alerts 405" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X POST "${BASE}/api/environment/alerts")" "405"
assert_eq "GET invalid id 400" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" "${BASE}/api/analytics/peringatan/abc")" "400"
assert_eq "GET missing id 404" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" "${BASE}/api/analytics/peringatan/999999")" "404"

# --- ensure seed fixture (§8) when DB predates analytics seed migration ---
seed_count="$(docker exec smartcity-mysql mysql -N -uroot -pRootSecret kelompok2 -e "SELECT COUNT(*) FROM analytics_peringatan;" 2>/dev/null || echo "0")"
if [ "${seed_count:-0}" -lt 15 ]; then
  docker exec -i smartcity-mysql mysql -uroot -pRootSecret kelompok2 2>/dev/null <<'SQL'
INSERT IGNORE INTO analytics_peringatan (id, idSungai, tipePeringatan, nilaiProbabilitas, recorded_at) VALUES
(1, 1, 'normal', 0.12, '2026-06-20 08:00:00'),
(2, 2, 'normal', 0.18, '2026-06-20 09:15:00'),
(3, 3, 'waspada', 0.58, '2026-06-21 14:30:00'),
(4, 1, 'waspada', 0.62, '2026-06-22 06:45:00'),
(5, 4, 'normal', 0.09, '2026-06-22 11:00:00'),
(6, 5, 'bencana', 0.88, '2026-06-23 02:20:00'),
(7, 3, 'bencana', 0.91, '2026-06-23 03:10:00'),
(8, 2, 'waspada', 0.55, '2026-06-24 16:00:00'),
(9, 1, 'normal', 0.15, '2026-06-25 07:30:00'),
(10, 4, 'waspada', 0.67, '2026-06-25 18:45:00'),
(11, 5, 'waspada', 0.71, '2026-06-26 05:00:00'),
(12, 1, 'bencana', 0.86, '2026-06-26 09:30:00'),
(13, 2, 'normal', 0.11, '2026-06-26 12:00:00'),
(14, 3, 'normal', 0.22, '2026-06-26 15:20:00'),
(15, 4, 'bencana', 0.93, '2026-06-26 20:10:00');
SQL
  echo "INFO applied analytics_peringatan seed fixture"
fi

list_body="$(curl_json GET "${BASE}/api/analytics/peringatan")"
assert_node "GET /api/analytics/peringatan status=success" "$list_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'?0:1)"
assert_node "GET /api/analytics/peringatan has standard JSON fields" "$list_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.code&&d.message&&d.timestamp&&d.service==='php-analytics'?0:1)"
assert_node "seed data has at least 15 peringatan rows" "$list_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(Array.isArray(d.data)&&d.data.length>=15?0:1)"
assert_node "peringatan rows include lokasiSungai join" "$list_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data[0]&&d.data[0].lokasiSungai?0:1)"

# --- filters ---
filter_body="$(curl_json GET "${BASE}/api/analytics/peringatan?tipePeringatan=bencana")"
assert_node "filter tipePeringatan=bencana returns only bencana" "$filter_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data.length>0&&d.data.every(r=>r.tipePeringatan==='bencana')?0:1)"

filter_sungai="$(curl_json GET "${BASE}/api/analytics/peringatan?idSungai=1")"
assert_node "filter idSungai=1 matches sungai" "$filter_sungai" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data.length>0&&d.data.every(r=>Number(r.idSungai)===1)?0:1)"

bad_filter_code="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" "${BASE}/api/analytics/peringatan?tipePeringatan=invalid")"
assert_eq "invalid filter returns 400" "$bad_filter_code" "400"

filter_dates="$(curl_json GET "${BASE}/api/analytics/peringatan?from=2026-06-23&to=2026-06-24")"
assert_node "filter from/to date range returns rows" "$filter_dates" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(Array.isArray(d.data)&&d.data.length>=1?0:1)"

assert_eq "PUT /api/analytics/peringatan 405" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X PUT "${BASE}/api/analytics/peringatan/6")" "405"

# --- show by id (use known seed id 6 = bencana) ---
show_body="$(curl_json GET "${BASE}/api/analytics/peringatan/6")"
assert_node "GET /api/analytics/peringatan/6 returns bencana row" "$show_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data&&Number(d.data.id)===6&&d.data.tipePeringatan==='bencana'?0:1)"

# --- spec alias §7.3 active alerts ---
alerts_body="$(curl_json GET "${BASE}/api/environment/alerts")"
assert_node "GET /api/environment/alerts status=success" "$alerts_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'?0:1)"
assert_node "GET /api/environment/alerts excludes normal" "$alerts_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data.every(r=>r.tipePeringatan==='waspada'||r.tipePeringatan==='bencana')?0:1)"
assert_node "GET /api/environment/alerts has at least 9 active rows" "$alerts_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.data.length>=9?0:1)"

# --- admin delete (use seed id 14 = normal) ---
assert_eq "DELETE without admin role 403" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X DELETE "${BASE}/api/analytics/peringatan/14")" "403"
del_code="$(curl -sS -m 10 -o /tmp/php-analytics-del.json -w "%{http_code}" -X DELETE "${BASE}/api/analytics/peringatan/14" -H "X-User-Role: admin")"
del_body="$(cat /tmp/php-analytics-del.json)"
assert_eq "DELETE with admin role 200" "$del_code" "200"
assert_node "DELETE response status=success" "$del_body" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'?0:1)"
assert_eq "DELETE missing row 404" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X DELETE "${BASE}/api/analytics/peringatan/14" -H "X-User-Role: admin")" "404"

# --- gateway proxy ---
gw_health="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" "${GATEWAY}/health" || echo "000")"
if [ "$gw_health" = "200" ] || [ "$gw_health" = "503" ]; then
  assert_eq "gateway GET /api/analytics without token 401" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" "${GATEWAY}/api/analytics/peringatan")" "401"

  gw_list="$(curl_json GET "${GATEWAY}/api/analytics/peringatan" -H "Authorization: Bearer ${USER_TOKEN}")"
  assert_node "gateway GET /api/analytics/peringatan with JWT" "$gw_list" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'&&Array.isArray(d.data)?0:1)"

  gw_alerts="$(curl_json GET "${GATEWAY}/api/environment/alerts" -H "Authorization: Bearer ${USER_TOKEN}")"
  assert_node "gateway GET /api/environment/alerts routes to php-analytics" "$gw_alerts" "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.status==='success'&&d.service==='php-analytics'?0:1)"

  gw_env_post="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X POST "${GATEWAY}/api/environment/readings" -H "Authorization: Bearer ${USER_TOKEN}" -H "Content-Type: application/json" -d '{}')"
  assert_eq "gateway POST /api/environment/readings still routes to php-river" "$gw_env_post" "400"

  assert_eq "gateway DELETE peringatan as user 403" "$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X DELETE "${GATEWAY}/api/analytics/peringatan/9" -H "Authorization: Bearer ${USER_TOKEN}")" "403"

  gw_del="$(curl -sS -m 10 -o /dev/null -w "%{http_code}" -X DELETE "${GATEWAY}/api/analytics/peringatan/9" -H "Authorization: Bearer ${ADMIN_TOKEN}")"
  assert_eq "gateway DELETE peringatan as admin 200" "$gw_del" "200"
else
  echo "SKIP gateway tests (gateway not reachable at ${GATEWAY})"
fi

# --- RabbitMQ consumer (S6) ---
before_count="$(docker exec smartcity-mysql mysql -N -u"${ANALYTICS_DB_USER}" -p"${ANALYTICS_DB_PASSWORD}" "${MYSQL_DATABASE}" -e "SELECT COUNT(*) FROM analytics_peringatan;" 2>/dev/null || echo "")"
if [ -n "$before_count" ]; then
  python3 - <<'PY'
import json, os, sys
import pika

payload = {
    "event": "anomaly.alert",
    "idSungai": 2,
    "tipePeringatan": "waspada",
    "nilaiProbabilitas": 0.77,
    "tinggiAir": 3.2,
}

conn = pika.BlockingConnection(pika.ConnectionParameters(
    host=os.environ.get("RABBITMQ_HOST", "localhost"),
    port=int(os.environ.get("RABBITMQ_PORT", "5672")),
    credentials=pika.PlainCredentials(
        os.environ.get("RABBITMQ_USER", "smartcity"),
        os.environ.get("RABBITMQ_PASSWORD", "RabbitSecret"),
    ),
))
ch = conn.channel()
ch.exchange_declare("city.events", "topic", durable=True)
ch.queue_declare("anomaly.alert", durable=True)
ch.queue_bind("anomaly.alert", "city.events", "anomaly.alert")
ch.basic_publish("city.events", "anomaly.alert", json.dumps(payload))
conn.close()
print("published anomaly.alert")
PY

  sleep 3
  after_count="$(docker exec smartcity-mysql mysql -N -u"${ANALYTICS_DB_USER}" -p"${ANALYTICS_DB_PASSWORD}" "${MYSQL_DATABASE}" -e "SELECT COUNT(*) FROM analytics_peringatan;" 2>/dev/null)"
  if [ "$after_count" -gt "$before_count" ]; then
    pass=$((pass + 1))
    echo "PASS RabbitMQ consumer persisted new peringatan (${before_count} -> ${after_count})"
  else
    fail=$((fail + 1))
    echo "FAIL RabbitMQ consumer did not persist peringatan (${before_count} -> ${after_count})"
  fi

  riwayat_count="$(docker exec smartcity-mysql mysql -N -uroot -pRootSecret "${MYSQL_DATABASE}" -e "SELECT COUNT(*) FROM user_riwayatBanjir WHERE idSungai=2 AND tinggiAir >= 3.1 AND tinggiAir <= 3.3;" 2>/dev/null || echo "0")"
  if [ "${riwayat_count:-0}" -ge 1 ] 2>/dev/null; then
    pass=$((pass + 1))
    echo "PASS RabbitMQ consumer inserted user_riwayatBanjir for waspada"
  else
    fail=$((fail + 1))
    echo "FAIL RabbitMQ consumer missing user_riwayatBanjir side-effect"
  fi
else
  echo "SKIP RabbitMQ consumer test (mysql container unreachable)"
fi

# --- consumer container running ---
if docker ps --format '{{.Names}}' | grep -q '^smartcity-php-analytics-consumer$'; then
  pass=$((pass + 1))
  echo "PASS php-analytics-consumer container is running"
else
  fail=$((fail + 1))
  echo "FAIL php-analytics-consumer container is not running"
fi

total=$((pass + fail))
echo "${pass}/${total} php-analytics smoke tests passed"

rm -f /tmp/php-analytics-health.json /tmp/php-analytics-del.json

if [ "$fail" -gt 0 ]; then
  exit 1
fi
