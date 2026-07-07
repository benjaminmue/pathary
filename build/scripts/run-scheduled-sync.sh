#!/bin/bash
# Wrapper script for cron-based scheduled sync
# Restores the container's runtime environment (cron scrubs it) and runs the sync

set -e  # Exit on error
set -u  # Exit on undefined variable

# cron runs jobs with an empty environment, so the container's runtime variables
# (DB credentials, TMDB/OMDb keys, ENCRYPTION_KEY) are NOT available here. The
# entrypoint snapshotted them to this file at container start; source it so the
# sync talks to the real database instead of silently falling back to SQLite.
# (The previous `export VAR="${VAR:-default}"` block was a no-op under cron: it
# read from the already-empty environment and exported empty/default values.)
CRON_ENV_FILE="${CRON_ENV_FILE:-/app/storage/.cron-env}"
if [ -f "$CRON_ENV_FILE" ]; then
    set -a  # auto-export everything sourced below
    # shellcheck disable=SC1090
    . "$CRON_ENV_FILE"
    set +a
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $CRON_ENV_FILE missing; refusing to run with an incomplete environment (would sync the wrong database)" >&2
    exit 1
fi

# Change to app directory
cd /app

# Log start to stdout (captured by Docker logs)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting scheduled sync (TMDB + OMDb)"

# Run the sync command
# Output goes to both file log (via >>) and stdout (via tee)
/usr/bin/php bin/console.php sync:scheduled 2>&1 | tee -a storage/logs/scheduled-sync.log

# Capture exit code
EXIT_CODE=${PIPESTATUS[0]}

# Log completion to stdout (captured by Docker logs)
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Scheduled sync completed successfully"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: Scheduled sync failed with exit code $EXIT_CODE"
fi

exit $EXIT_CODE
