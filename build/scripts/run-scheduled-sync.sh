#!/bin/bash
# Wrapper script for cron-based scheduled sync
# Exports environment variables from container to cron context

set -e  # Exit on error
set -u  # Exit on undefined variable

# Export required environment variables (set by Docker Compose)
export TMDB_API_KEY="${TMDB_API_KEY:-}"
export DATABASE_MODE="${DATABASE_MODE:-sqlite}"
export DATABASE_MYSQL_HOST="${DATABASE_MYSQL_HOST:-}"
export DATABASE_MYSQL_PORT="${DATABASE_MYSQL_PORT:-3306}"
export DATABASE_MYSQL_NAME="${DATABASE_MYSQL_NAME:-}"
export DATABASE_MYSQL_USER="${DATABASE_MYSQL_USER:-}"
export DATABASE_MYSQL_PASSWORD="${DATABASE_MYSQL_PASSWORD:-}"
export APPLICATION_URL="${APPLICATION_URL:-http://localhost}"
export TZ="${TZ:-UTC}"

# Optional: OMDb API key for IMDb + Rotten Tomatoes ratings
export OMDB_API_KEY="${OMDB_API_KEY:-}"

# Optional: Image caching setting
export TMDB_ENABLE_IMAGE_CACHING="${TMDB_ENABLE_IMAGE_CACHING:-0}"

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
