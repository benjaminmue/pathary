#!/bin/bash
# =============================================================================
# Regression test: cron scheduled-sync environment propagation
# =============================================================================
# Reproduces the bug where the nightly sync ran with cron's scrubbed environment,
# fell back to SQLite (DATABASE_MODE unset) and never touched the real MySQL DB,
# so IMDb / Rotten Tomatoes ratings were never synced.
#
# It writes the env snapshot, then runs a shell with a scrubbed environment
# (env -i, exactly what cron does) and asserts the runtime variables are
# restored intact — including values with '!' and '=' which naive quoting drops.
#
# Fails without the fix (no write-cron-env.sh / no sourcing). Passes with it.
set -uo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

# Synthetic runtime environment (NOT real credentials). The password and key
# deliberately contain '!' and '=' so the test proves %q quoting survives them.
export DATABASE_MODE="mysql"
export DATABASE_MYSQL_HOST="db.internal.example"
export DATABASE_MYSQL_PASSWORD='dummy-P@ss!word=42'  # special chars must survive
export TMDB_API_KEY="tmdb-test-key"
export OMDB_API_KEY="omdb-test-key"
export ENCRYPTION_KEY="dGVzdC1lbmNyeXB0aW9uLWtleQ=="  # fake base64; was missing from the old allowlist

# Guard against the hardcoded PHP path regression: the php:8.x-apache production
# image has php at /usr/local/bin/php, not /usr/bin/php, so the wrapper must
# resolve it via command -v, never hardcode /usr/bin/php.
WRAPPER="$DIR/run-scheduled-sync.sh"
if grep -qE '(^|[^-])/usr/bin/php\b' "$WRAPPER"; then
    echo "FAIL: run-scheduled-sync.sh hardcodes /usr/bin/php (breaks the Debian prod image)"
    exit 1
fi
if ! grep -q 'command -v php' "$WRAPPER"; then
    echo "FAIL: run-scheduled-sync.sh does not resolve php via 'command -v php'"
    exit 1
fi

bash "$DIR/write-cron-env.sh" "$TMP" >/dev/null

# Simulate cron: scrubbed environment, then source the snapshot like the wrapper does.
result=$(env -i HOME=/root PATH=/usr/bin:/bin SHELL=/bin/sh /bin/bash -c "
    set -a; . '$TMP'; set +a
    printf '%s|%s|%s|%s|%s|%s' \
        \"\${DATABASE_MODE:-UNSET}\" \
        \"\${DATABASE_MYSQL_HOST:-UNSET}\" \
        \"\${DATABASE_MYSQL_PASSWORD:-UNSET}\" \
        \"\${TMDB_API_KEY:-UNSET}\" \
        \"\${OMDB_API_KEY:-UNSET}\" \
        \"\${ENCRYPTION_KEY:-UNSET}\"
")

expected="mysql|db.internal.example|dummy-P@ss!word=42|tmdb-test-key|omdb-test-key|dGVzdC1lbmNyeXB0aW9uLWtleQ=="

if [ "$result" = "$expected" ]; then
    echo "PASS: cron-scrubbed shell restored the full runtime environment"
    exit 0
fi

echo "FAIL: environment not restored under cron simulation"
echo "  expected: $expected"
echo "  actual:   $result"
exit 1
