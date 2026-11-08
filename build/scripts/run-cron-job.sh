#!/bin/bash
# Runs one bin/console.php command from cron with the container's runtime
# environment restored (cron scrubs it).
#
# Usage: run-cron-job.sh <console command> [arguments...]
#   e.g. run-cron-job.sh sync:scheduled

set -e  # Exit on error
set -u  # Exit on undefined variable

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <console command> [arguments...]" >&2
    exit 64
fi

# cron runs jobs with an empty environment, so the container's runtime variables
# (DB credentials, TMDB/OMDb keys, ENCRYPTION_KEY) are NOT available here. The
# entrypoint snapshotted them to this file at container start; source it so the
# job talks to the real database instead of silently falling back to SQLite.
CRON_ENV_FILE="${CRON_ENV_FILE:-/app/storage/.cron-env}"
if [ -f "$CRON_ENV_FILE" ]; then
    set -a  # auto-export everything sourced below
    # shellcheck disable=SC1090
    . "$CRON_ENV_FILE"
    set +a
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $CRON_ENV_FILE missing; refusing to run with an incomplete environment (would use the wrong database)" >&2
    exit 1
fi

cd /app

# Resolve the PHP binary portably. cron's default PATH is /usr/bin:/bin, which
# excludes /usr/local/bin where the php:8.x-apache (Debian) production image
# installs php; the alpine dev image puts it in /usr/bin. Hardcoding either path
# breaks the other, so extend PATH to cover both and resolve via command -v.
export PATH="/usr/local/bin:/usr/local/sbin:/usr/bin:/bin:${PATH:-}"
PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: php binary not found on PATH ($PATH)" >&2
    exit 127
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting cron job '$*' using $PHP_BIN"

# stdout reaches the Docker log through the cron line's redirect; the file keeps
# a history that survives container restarts.
"$PHP_BIN" bin/console.php "$@" 2>&1 | tee -a storage/logs/cron.log
EXIT_CODE=${PIPESTATUS[0]}

if [ "$EXIT_CODE" -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cron job '$*' completed successfully"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: cron job '$*' failed with exit code $EXIT_CODE"
fi

exit "$EXIT_CODE"
