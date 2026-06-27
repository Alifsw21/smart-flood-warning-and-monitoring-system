#!/usr/bin/env bash
set -u

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/../.." && pwd)"

cd "$repo_root" || exit 1

failed=0

if bash oauth-server/tests/http.sh; then
  echo "PASS oauth HTTP tests"
else
  echo "FAIL oauth HTTP tests"
  failed=1
fi

if [ "$failed" -eq 0 ]; then
  echo "PASS oauth-server test suite"
  exit 0
fi

echo "FAIL oauth-server test suite"
exit 1
