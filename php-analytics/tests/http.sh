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
  actual="$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$@" "http://127.0.0.1:8099$path")"

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
  actual="$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$@" "http://127.0.0.1:8099$path")"

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

php -S 127.0.0.1:8099 -t php-analytics/public >"$server_log" 2>&1 &
server_pid=$!
sleep 1

if ! kill -0 "$server_pid" 2>/dev/null; then
  echo "FAIL php -S failed to start"
  cat "$server_log"
  exit 1
fi

assert_status GET /nope 404 "GET /nope returns 404"
assert_status POST /api/analytics/peringatan 405 "POST /api/analytics/peringatan returns 405"
assert_status DELETE /api/analytics/peringatan/5 403 "DELETE without role header returns 403"
assert_not_status DELETE /api/analytics/peringatan/5 403 "DELETE with admin role passes admin gate" -H "X-User-Role: admin"
assert_status GET /api/analytics/peringatan/abc 400 "GET invalid id returns 400"
assert_status GET /health 503 "GET /health returns 503 without DB"

health_body="$(curl -s "http://127.0.0.1:8099/health")"
assert_body_contains "$health_body" status "/health body has status"
assert_body_contains "$health_body" code "/health body has code"
assert_body_contains "$health_body" data "/health body has data"
assert_body_contains "$health_body" message "/health body has message"
assert_body_contains "$health_body" timestamp "/health body has timestamp"
assert_body_contains "$health_body" service "/health body has service"

total=$((passed + failed))
echo "$passed/$total HTTP tests passed"

if [ "$failed" -gt 0 ]; then
  exit 1
fi
