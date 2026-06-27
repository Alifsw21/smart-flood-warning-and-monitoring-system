#!/usr/bin/env bash
set -u

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/../.." && pwd)"

cd "$repo_root" || exit 1

failed=0

if [ ! -f php-user/vendor/autoload.php ]; then
  (cd php-user && composer install --no-dev --quiet)
fi

if php php-user/tests/unit.php; then
  echo "PASS unit tests"
else
  echo "FAIL unit tests"
  failed=1
fi

if bash php-user/tests/http.sh; then
  echo "PASS HTTP tests"
else
  echo "FAIL HTTP tests"
  failed=1
fi

if [ "$failed" -eq 0 ]; then
  echo "PASS php-user test suite"
  exit 0
fi

echo "FAIL php-user test suite"
exit 1
