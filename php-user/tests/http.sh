#!/usr/bin/env bash
set -u

passed=0
failed=0
server_pid=""
server_log="$(mktemp)"

cleanup() {
  if [ -n "$server_pid" ] && kill -0 "$server_pid" 2>/dev/null; then
    kill "$server_pid" 2>/dev/null
    wait "$server_pid" 2>/dev/null
  fi

  rm -f "$server_log"
}

trap cleanup EXIT

assert_status() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local description="$4"
  shift 4

  local actual
  actual="$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$@" "http://127.0.0.1:8100$path")"

  if [ "$actual" = "$expected" ]; then
    passed=$((passed + 1))
    echo "PASS $description"
  else
    failed=$((failed + 1))
    echo "FAIL $description (expected $expected, got $actual)"
  fi
}

assert_not_status() {
  local method="$1"
  local path="$2"
  local unexpected="$3"
  local description="$4"
  shift 4

  local actual
  actual="$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$@" "http://127.0.0.1:8100$path")"

  if [ "$actual" != "$unexpected" ]; then
    passed=$((passed + 1))
    echo "PASS $description"
  else
    failed=$((failed + 1))
    echo "FAIL $description (did not expect $unexpected)"
  fi
}

assert_body_contains() {
  local body="$1"
  local key="$2"
  local description="$3"

  if printf '%s' "$body" | grep -q "\"$key\""; then
    passed=$((passed + 1))
    echo "PASS $description"
  else
    failed=$((failed + 1))
    echo "FAIL $description"
  fi
}

if [ ! -f php-user/vendor/autoload.php ]; then
  (cd php-user && composer install --no-dev --quiet)
fi

php -S 127.0.0.1:8100 -t php-user/public >"$server_log" 2>&1 &
server_pid=$!
sleep 1

if ! kill -0 "$server_pid" 2>/dev/null; then
  echo "FAIL php -S failed to start"
  cat "$server_log"
  exit 1
fi

assert_status GET /nope 404 "GET /nope returns 404"
assert_status GET / 200 "GET / serves login page"

assert_status POST /api/flood-history 405 "POST /api/flood-history returns 405"
assert_status DELETE /api/flood-history/5 403 "DELETE flood-history without admin role returns 403"
assert_not_status DELETE /api/flood-history/5 403 "DELETE flood-history with admin role passes gate" -H "X-User-Role: admin"
assert_status GET /api/flood-history/abc 400 "GET invalid flood-history id returns 400"

assert_status GET /api/users 403 "GET /api/users without admin returns 403"
assert_not_status GET /api/users 403 "GET /api/users with admin passes gate" -H "X-User-Role: admin"
assert_status POST /api/users 403 "POST /api/users without admin returns 403"
assert_status GET /api/users/abc 400 "GET invalid user id returns 400"
assert_status GET /api/users/1 403 "GET /api/users/1 without auth context returns 403"
assert_not_status GET /api/users/1 403 "GET own profile allowed" -H "X-User-Id: 1" -H "X-User-Role: user"

assert_status GET /api/laporan 500 "GET /api/laporan without DB returns database error"
assert_status POST /api/laporan 401 "POST /api/laporan without user id returns 401"
assert_not_status POST /api/laporan 401 "POST /api/laporan with user id passes auth gate" \
  -H "X-User-Id: 2" -H "X-User-Role: user" \
  -H "Content-Type: application/json" \
  -d '{"deskripsiLaporan":"Sensor banjir di gang 3 tidak akurat sejak kemarin malam."}'
assert_status GET /api/laporan/abc 400 "GET invalid laporan id returns 400"

assert_status GET /api/notifications 401 "GET /api/notifications without user id returns 401"
assert_not_status GET /api/notifications 401 "GET /api/notifications with user id passes auth gate" -H "X-User-Id: 2" -H "X-User-Role: user"
assert_status PATCH /api/laporan/1/status 403 "PATCH laporan status without admin returns 403"
assert_not_status PATCH /api/laporan/1/status 403 "PATCH laporan status with admin passes gate" \
  -H "X-User-Role: admin" -H "Content-Type: application/json" -d '{"status":"in_progress"}'
assert_status PATCH /api/reports/1/status 403 "PATCH /api/reports alias without admin returns 403"
assert_status GET /api/citizens 403 "GET /api/citizens alias without admin returns 403"

assert_status GET /health 503 "GET /health returns 503 without DB"

health_body="$(curl -s "http://127.0.0.1:8100/health")"
assert_body_contains "$health_body" status "/health body has status"
assert_body_contains "$health_body" service "/health body has service"

total=$((passed + failed))
echo "$passed/$total HTTP tests passed"

if [ "$failed" -gt 0 ]; then
  exit 1
fi
