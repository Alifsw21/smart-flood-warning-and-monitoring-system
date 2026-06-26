#!/usr/bin/env bash
set -u

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/../.." && pwd)"

cd "$repo_root" || exit 1

failed=0

if php php-analytics/tests/unit.php; then
  echo "PASS unit tests"
else
  echo "FAIL unit tests"
  failed=1
fi

if bash php-analytics/tests/http.sh; then
  echo "PASS HTTP tests"
else
  echo "FAIL HTTP tests"
  failed=1
fi

if [ "$failed" -eq 0 ]; then
  echo "PASS php-analytics test suite"
  exit 0
fi

echo "FAIL php-analytics test suite"
exit 1
