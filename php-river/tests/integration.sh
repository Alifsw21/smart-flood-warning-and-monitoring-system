#!/usr/bin/env bash
set -u

cd "$(dirname "$0")/../.."
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

php -S 127.0.0.1:8098 -t php-river/public >"$server_log" 2>&1 &
server_pid=$!
sleep 1

if ! kill -0 "$server_pid" 2>/dev/null; then
  echo "FAIL php -S failed to start"
  cat "$server_log"
  exit 1
fi

php php-river/tests/integration.php http://127.0.0.1:8098
