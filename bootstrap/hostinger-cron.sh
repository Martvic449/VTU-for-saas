#!/usr/bin/env bash
# Hostinger shared-hosting entry point for Laravel's scheduler and bounded queue worker.
# Configure one Hostinger Custom cron job to run this script every minute.
set -Eeuo pipefail

APP_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

PHP_BIN="${PHP_CLI:-php}"
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP CLI was not found. Set PHP_CLI to Hostinger's PHP 8.2+ CLI binary." >&2
    exit 1
fi

"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || {
    echo "The CLI PHP version must be 8.2 or newer." >&2
    exit 1
}

if [[ ! -f artisan || ! -f vendor/autoload.php ]]; then
    echo "Laravel artisan or Composer dependencies are missing; cron work was not started." >&2
    exit 1
fi

if [[ ! -d storage/framework || ! -w storage/framework ]]; then
    echo "storage/framework must exist and be writable." >&2
    exit 1
fi

# Run due scheduled tasks first. This dispatches reserved transactions and
# recovers due webhook deliveries into the database queue.
"$PHP_BIN" artisan schedule:run --no-interaction

if ! command -v flock >/dev/null 2>&1; then
    echo "The flock utility is required to prevent overlapping queue workers on shared hosting." >&2
    exit 1
fi

# The lock is automatically released if the worker exits or the cron process
# is terminated. A second cron tick skips queue processing while one is active.
exec 9>"$APP_ROOT/storage/framework/queue-worker.lock"
if flock -n 9; then
    "$PHP_BIN" artisan queue:work database \
        --stop-when-empty \
        --max-time=50 \
        --timeout=100 \
        --tries=1 \
        --no-interaction
else
    echo "A queue worker is already running; this tick only ran the scheduler."
fi
