#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR/.."

bash tests/smoke.sh
bash tests/s3-ml-e2e.sh
if [ "${SKIP_RABBITMQ_E2E:-}" != "1" ]; then
  bash tests/rabbitmq-e2e.sh
fi
