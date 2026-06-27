#!/usr/bin/env bash
set -u

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

repo_root="$(cd "$script_dir/../.." && pwd)"

cd "$repo_root" || exit 1

failed=0

if php iot/tests/unit.php; then
  echo "PASS IoT unit tests"
else
  echo "FAIL IoT unit tests"
  failed=1
fi

if [ "$failed" -eq 0 ] && [ "${RUN_S1_E2E:-}" = "1" ]; then
  if bash iot/tests/s1-e2e.sh; then
    echo "PASS S1 IoT E2E"
  else
    echo "FAIL S1 IoT E2E"
    failed=1
  fi
fi

if [ "$failed" -eq 0 ]; then
  echo "PASS iot test suite"
  exit 0
fi

echo "FAIL iot test suite"
exit 1