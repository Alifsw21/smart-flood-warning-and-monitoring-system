#!/usr/bin/env bash
set -u

cd "$(dirname "$0")/../.."

echo "Running php-river test suite..."
bash php-river/tests/reset-seed-pollution.sh
php php-river/tests/unit.php
bash php-river/tests/http.sh
bash php-river/tests/integration.sh
