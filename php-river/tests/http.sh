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
  actual="$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$@" "http://127.0.0.1:8098$path")"

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
  actual="$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$@" "http://127.0.0.1:8098$path")"

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

php -S 127.0.0.1:8098 -t php-river/public >"$server_log" 2>&1 &
server_pid=$!
sleep 1

if ! kill -0 "$server_pid" 2>/dev/null; then
  echo "FAIL php -S failed to start"
  cat "$server_log"
  exit 1
fi

assert_status GET /nope 404 "GET /nope returns 404"
assert_status GET /health 200 "GET /health returns 200"
assert_status GET /api/river/sensors 200 "GET /api/river/sensors returns 200"
assert_status GET /api/river/zones 200 "GET /api/river/zones returns 200"
assert_status GET /api/river/sungai 200 "GET /api/river/sungai returns 200"
assert_status GET /api/river/readings 200 "GET /api/river/readings returns 200"
assert_status GET /api/environment/current 200 "GET /api/environment/current returns 200"
assert_status GET /api/traffic/current 200 "GET /api/traffic/current returns 200"
assert_status GET /api/traffic/history 200 "GET /api/traffic/history returns 200"
assert_status GET /api/river/sensors/999999 404 "GET missing sensor returns 404"
assert_status GET /api/river/sensors/1/readings 200 "GET /api/river/sensors/1/readings returns 200"
assert_status POST /api/environment/readings 400 "POST /api/environment/readings without body returns 400"
assert_status POST /api/traffic/readings 400 "POST /api/traffic/readings without body returns 400"
assert_status POST /api/river/zones 403 "POST /api/river/zones without admin returns 403"
assert_status PUT /api/river/sensors/1 403 "PUT /api/river/sensors/1 without admin returns 403"
assert_status DELETE /api/river/sensors/1 403 "DELETE /api/river/sensors/1 without admin returns 403"
assert_not_status PUT /api/river/sensors/999999 403 "PUT missing sensor passes admin gate" -H "X-User-Role: admin" -H "Content-Type: application/json" -d '{"idSungai":1,"idStation":99998,"namaNode":"gate-test","posisi":"hulu","elevasi":1}'

health_body="$(curl -s "http://127.0.0.1:8098/health")"
assert_body_contains "$health_body" status "/health body has status"
assert_body_contains "$health_body" service "/health body has service"
assert_body_contains "$health_body" php-river "/health body has php-river service name"

current_body="$(curl -s "http://127.0.0.1:8098/api/environment/current")"
assert_body_contains "$current_body" status "/api/environment/current body has status"

total=$((passed + failed))
echo "$passed/$total HTTP tests passed"

if [ "$failed" -gt 0 ]; then
  exit 1
fi
