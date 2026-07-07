#!/bin/bash
# =============================================================================
# Persist the container's runtime environment for cron
# =============================================================================
# cron runs every job with a scrubbed environment (only HOME/PATH/SHELL/LOGNAME
# plus anything set inside the crontab). None of the container's runtime
# variables that were passed via `docker run -e` (DB credentials, TMDB/OMDb API
# keys, ENCRYPTION_KEY, ...) reach the scheduled sync. Without them the sync
# falls back to the SQLite default (DATABASE_MODE unset) and never touches the
# real database, so IMDb / Rotten Tomatoes ratings are never updated.
#
# The entrypoint calls this at container start to snapshot the environment into
# a shell-sourceable file; run-scheduled-sync.sh sources it before invoking the
# console command. We dump *every* exported variable (not a hand-maintained
# allowlist, which previously drifted and forgot ENCRYPTION_KEY) with %q quoting
# so values containing spaces, quotes or '!' survive re-sourcing intact.
set -euo pipefail

ENV_FILE="${1:-/app/storage/.cron-env}"

umask 077
: > "$ENV_FILE"

var=""
for var in $(compgen -e); do
    # Skip shell-managed noise that must not leak into the cron shell.
    case "$var" in
        PWD|OLDPWD|SHLVL|_|HOME|HOSTNAME|TERM|PATH|SHELL|LOGNAME|USER) continue ;;
    esac
    printf '%s=%q\n' "$var" "${!var}" >> "$ENV_FILE"
done

echo "[CRON] Persisted $(wc -l < "$ENV_FILE" | tr -d ' ') runtime variables to $ENV_FILE"
