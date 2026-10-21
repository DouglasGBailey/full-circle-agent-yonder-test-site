#!/usr/bin/env bash
# Start a throwaway copy of the site for the Playwright tests.
#
# - Its own SQLite database (truncated on every start), so tests never touch
#   database/database.sqlite.
# - CACHE_STORE=array: each request gets an empty cache, so the rate limiters
#   never trigger here. PHPUnit tests the limits; these tests walk the flows.
# - Real environment variables win over .env (Laravel's dotenv is immutable).
# - Pilot passwords go to a file OUTSIDE the repo (pilot:accounts refuses
#   anything inside it); the specs read guest@example.com's password from it.
set -euo pipefail
cd "$(dirname "$0")/../.."

STATE_DIR="${E2E_STATE_DIR:-${TMPDIR:-/tmp}/saltmarsh-e2e}"
PORT="${E2E_PORT:-8001}"
mkdir -p "$STATE_DIR"
: > "$STATE_DIR/e2e.sqlite"

export APP_ENV=local APP_DEBUG=false APP_URL="http://127.0.0.1:${PORT}"
export DB_CONNECTION=sqlite DB_DATABASE="$STATE_DIR/e2e.sqlite"
export CACHE_STORE=array SESSION_DRIVER=database
export FCA_API_TOKEN="${E2E_API_TOKEN:-e2e-local-token-not-a-secret-32chars-min}"

php artisan migrate --force --no-interaction >/dev/null
php artisan pilot:accounts --reset --out="$STATE_DIR/accounts.txt" >/dev/null

# Several workers, so the three browsers can run side by side.
export PHP_CLI_SERVER_WORKERS=4
# Laravel's router script loads index.php from the current folder, so start in public/.
cd public
exec php -S "127.0.0.1:${PORT}" -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
