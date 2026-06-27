#!/usr/bin/env bash
set -u

passed=0
failed=0
server_pid=""
server_log="$(mktemp)"

PORT="${OAUTH_TEST_PORT:-3092}"
BASE_URL="http://127.0.0.1:${PORT}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3307}"
DB_USER="${DB_USER:-auth}"
DB_PASSWORD="${DB_PASSWORD:-AuthSecret}"
DB_NAME="${DB_NAME:-kelompok2}"
JWT_SECRET="${JWT_SECRET:-dev-jwt-secret-change-me}"
GATEWAY_CLIENT_SECRET="${GATEWAY_CLIENT_SECRET:-GatewaySecretDev123}"
CITIZEN_CLIENT_SECRET="${CITIZEN_CLIENT_SECRET:-CitizenSecretDev123}"
DEV_USERNAME="${DEV_USERNAME:-hadiputra2}"
DEV_PASSWORD="${DEV_PASSWORD:-kelompok2dev}"

cleanup() {
  if [ -n "$server_pid" ] && kill -0 "$server_pid" 2>/dev/null; then
    kill "$server_pid" 2>/dev/null
    wait "$server_pid" 2>/dev/null
  fi

  rm -f "$server_log"
}

trap cleanup EXIT

assert_json_field() {
  local body="$1"
  local field="$2"
  local description="$3"

  if printf '%s' "$body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d['$field'] ? 0 : 1)"; then
    passed=$((passed + 1))
    echo "PASS $description"
  else
    failed=$((failed + 1))
    echo "FAIL $description"
  fi
}

assert_status() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local description="$4"
  shift 4

  local actual
  actual="$(curl -sS -m 10 -o /tmp/oauth-test-body.txt -w "%{http_code}" -X "$method" "$@" "${BASE_URL}${path}")"

  if [ "$actual" = "$expected" ]; then
    passed=$((passed + 1))
    echo "PASS $description"
  else
    failed=$((failed + 1))
    echo "FAIL $description (expected $expected, got $actual)"
    if [ -f /tmp/oauth-test-body.txt ]; then
      cat /tmp/oauth-test-body.txt
      echo
    fi
  fi
}

if ! command -v mysql >/dev/null 2>&1; then
  echo "SKIP oauth HTTP tests (mysql client not available)"
  exit 0
fi

if ! mysql -h "$DB_HOST" -P "$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT 1" >/dev/null 2>&1; then
  echo "SKIP oauth HTTP tests (MySQL unavailable at ${DB_HOST}:${DB_PORT})"
  exit 0
fi

oauth_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PORT="$PORT" \
JWT_SECRET="$JWT_SECRET" \
DB_HOST="$DB_HOST" \
DB_PORT="$DB_PORT" \
DB_USER="$DB_USER" \
DB_PASSWORD="$DB_PASSWORD" \
DB_NAME="$DB_NAME" \
OAUTH_DEFAULT_CLIENT_SECRET="$CITIZEN_CLIENT_SECRET" \
node "$oauth_dir/server.js" >"$server_log" 2>&1 &
server_pid=$!
sleep 1

if ! kill -0 "$server_pid" 2>/dev/null; then
  echo "FAIL oauth server failed to start"
  cat "$server_log"
  exit 1
fi

echo "OAuth HTTP tests against ${BASE_URL}"

assert_status GET /health 200 "GET /health"
assert_status POST /api/auth/login 401 "POST /api/auth/login rejects invalid credentials" \
  -H "Content-Type: application/json" \
  -d '{"username":"invalid","password":"invalid"}'

password_body="$(curl -sS -m 10 -X POST "${BASE_URL}/oauth/token" \
  -H "Content-Type: application/json" \
  -d "{\"grant_type\":\"password\",\"client_id\":\"citizen-app\",\"client_secret\":\"${CITIZEN_CLIENT_SECRET}\",\"username\":\"${DEV_USERNAME}\",\"password\":\"${DEV_PASSWORD}\"}")"

assert_json_field "$password_body" "access_token" "password grant returns access_token"
assert_json_field "$password_body" "refresh_token" "password grant returns refresh_token"

ACCESS_TOKEN="$(printf '%s' "$password_body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.stdout.write(d.access_token||'');")"
REFRESH_TOKEN="$(printf '%s' "$password_body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.stdout.write(d.refresh_token||'');")"

client_body="$(curl -sS -m 10 -X POST "${BASE_URL}/oauth/token" \
  -H "Content-Type: application/json" \
  -d "{\"grant_type\":\"client_credentials\",\"client_id\":\"gateway\",\"client_secret\":\"${GATEWAY_CLIENT_SECRET}\"}")"

assert_json_field "$client_body" "access_token" "client_credentials grant returns access_token"

introspect_body="$(curl -sS -m 10 -X POST "${BASE_URL}/oauth/introspect" \
  -H "Content-Type: application/json" \
  -d "{\"token\":\"${ACCESS_TOKEN}\",\"client_id\":\"gateway\",\"client_secret\":\"${GATEWAY_CLIENT_SECRET}\"}")"

if printf '%s' "$introspect_body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.active===true && String(d.sub)==='2' ? 0 : 1)"; then
  passed=$((passed + 1))
  echo "PASS introspect marks issued access token active"
else
  failed=$((failed + 1))
  echo "FAIL introspect marks issued access token active"
  echo "$introspect_body"
fi

refresh_body="$(curl -sS -m 10 -X POST "${BASE_URL}/oauth/token" \
  -H "Content-Type: application/json" \
  -d "{\"grant_type\":\"refresh_token\",\"client_id\":\"citizen-app\",\"client_secret\":\"${CITIZEN_CLIENT_SECRET}\",\"refresh_token\":\"${REFRESH_TOKEN}\"}")"

assert_json_field "$refresh_body" "access_token" "refresh_token grant returns new access_token"

NEW_ACCESS_TOKEN="$(printf '%s' "$refresh_body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.stdout.write(d.access_token||'');")"

assert_status POST /oauth/revoke 200 "POST /oauth/revoke succeeds" \
  -H "Content-Type: application/json" \
  -d "{\"token\":\"${NEW_ACCESS_TOKEN}\",\"client_id\":\"gateway\",\"client_secret\":\"${GATEWAY_CLIENT_SECRET}\"}"

revoked_body="$(curl -sS -m 10 -X POST "${BASE_URL}/oauth/introspect" \
  -H "Content-Type: application/json" \
  -d "{\"token\":\"${NEW_ACCESS_TOKEN}\",\"client_id\":\"gateway\",\"client_secret\":\"${GATEWAY_CLIENT_SECRET}\"}")"

if printf '%s' "$revoked_body" | node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8')); process.exit(d.active===false ? 0 : 1)"; then
  passed=$((passed + 1))
  echo "PASS revoked token is inactive on introspect"
else
  failed=$((failed + 1))
  echo "FAIL revoked token is inactive on introspect"
  echo "$revoked_body"
fi

total=$((passed + failed))
echo "${passed}/${total} oauth HTTP tests passed"

if [ "$failed" -gt 0 ]; then
  exit 1
fi
