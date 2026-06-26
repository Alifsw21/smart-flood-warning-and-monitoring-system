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

if [ "$failed" -eq 0 ]; then
  echo "PASS iot test suite"
  exit 0
fi

echo "FAIL iot test suite"
exit 1