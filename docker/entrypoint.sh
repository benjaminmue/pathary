#!/bin/bash
set -e

# =============================================================================
# Pathary Docker Entrypoint
# =============================================================================

echo "=========================================="
echo "Pathary Container Starting..."
echo "=========================================="

# -----------------------------------------------------------------------------
# Validate required environment variables
# -----------------------------------------------------------------------------
validate_env() {
    local missing=()

    # TMDB API key is always required
    if [ -z "$TMDB_API_KEY" ]; then
        missing+=("TMDB_API_KEY")
    fi

    # Database configuration validation
    if [ "$DATABASE_MODE" = "mysql" ]; then
        echo "[ENV] Database mode: MySQL"

        if [ -z "$DATABASE_MYSQL_HOST" ]; then
            missing+=("DATABASE_MYSQL_HOST")
        fi
        if [ -z "$DATABASE_MYSQL_NAME" ]; then
            missing+=("DATABASE_MYSQL_NAME")
        fi
        if [ -z "$DATABASE_MYSQL_USER" ]; then
            missing+=("DATABASE_MYSQL_USER")
        fi
        if [ -z "$DATABASE_MYSQL_PASSWORD" ]; then
            missing+=("DATABASE_MYSQL_PASSWORD")
        fi
    else
        echo "[ENV] Database mode: SQLite (default)"
    fi

    if [ ${#missing[@]} -ne 0 ]; then
        echo "=========================================="
        echo "ERROR: Missing required environment variables:"
        for var in "${missing[@]}"; do
            echo "  - $var"
        done
        echo "=========================================="
        exit 1
    fi

    echo "[ENV] All required environment variables are set"
}

# -----------------------------------------------------------------------------
# Wait for MySQL to be ready (if using MySQL)
# -----------------------------------------------------------------------------
wait_for_mysql() {
    if [ "$DATABASE_MODE" = "mysql" ]; then
        echo "[DB] Waiting for MySQL to be ready..."

        local max_attempts=30
        local attempt=1

        while [ $attempt -le $max_attempts ]; do
            # Credentials are read with getenv() inside PHP instead of being
            # interpolated into the source: a quote in the password would break
            # (or inject code into) the generated script.
            if php -r '
                try {
                    new PDO(
                        sprintf("mysql:host=%s;port=%s", getenv("DATABASE_MYSQL_HOST"), getenv("DATABASE_MYSQL_PORT") ?: "3306"),
                        getenv("DATABASE_MYSQL_USER"),
                        getenv("DATABASE_MYSQL_PASSWORD"),
                        [PDO::ATTR_TIMEOUT => 5]
                    );
                    exit(0);
                } catch (Exception $e) {
                    exit(1);
                }
            ' 2>/dev/null; then
                echo "[DB] MySQL is ready!"
                return 0
            fi

            echo "[DB] MySQL not ready yet (attempt $attempt/$max_attempts)..."
            sleep 2
            attempt=$((attempt + 1))
        done

        echo "[DB] ERROR: Could not connect to MySQL after $max_attempts attempts"
        exit 1
    fi
}

# -----------------------------------------------------------------------------
# Run database migrations
# -----------------------------------------------------------------------------
run_migrations() {
    # Check if migrations are enabled (default: enabled)
    if [ "${MIGRATIONS_AUTO_RUN:-1}" = "0" ]; then
        echo "[DB] Migrations disabled (MIGRATIONS_AUTO_RUN=0)"
        return 0
    fi

    echo "[DB] Running database migrations..."

    if [ -f "/app/bin/console.php" ]; then
        php /app/bin/console.php database:migration:migrate --no-interaction || {
            echo "[DB] ERROR: Migration failed!"
            echo "[DB] Container startup aborted to prevent running with broken schema."
            exit 1
        }
        echo "[DB] Migrations completed successfully"
    else
        # Fallback to phinx if console.php doesn't have the command
        if [ -f "/app/vendor/bin/phinx" ] && [ -f "/app/settings/phinx.php" ]; then
            php /app/vendor/bin/phinx migrate -c /app/settings/phinx.php || {
                echo "[DB] ERROR: Migration failed!"
                echo "[DB] Container startup aborted to prevent running with broken schema."
                exit 1
            }
            echo "[DB] Migrations completed successfully"
        else
            echo "[DB] No migration tool found, skipping migrations"
        fi
    fi
}

# -----------------------------------------------------------------------------
# Ensure storage directories exist with correct permissions
# -----------------------------------------------------------------------------
setup_storage() {
    echo "[STORAGE] Setting up storage directories..."

    # Create required directories
    mkdir -p /app/storage/logs
    mkdir -p /app/storage/app/public
    mkdir -p /app/storage/profile-images
    mkdir -p /app/storage/cache

    # Compiled templates belong to the image that produced them; start every
    # container (and therefore every new image) with an empty cache.
    rm -rf /app/storage/cache/twig

    # Set ownership to www-data
    chown -R www-data:www-data /app/storage
    chmod -R 775 /app/storage

    echo "[STORAGE] Storage directories ready"
}

# -----------------------------------------------------------------------------
# Create public storage symlink if needed
# -----------------------------------------------------------------------------
setup_symlinks() {
    echo "[SYMLINK] Setting up public storage symlink..."

    if [ -f "/app/bin/console.php" ]; then
        # public/ is read-only for www-data, so the image cache and profile
        # images only work through this link into storage/.
        php /app/bin/console.php storage:link || echo "[SYMLINK] WARNING: could not create public/storage"
    fi

    echo "[SYMLINK] Symlinks configured"
}

# -----------------------------------------------------------------------------
# Start cron daemon for scheduled syncs
# -----------------------------------------------------------------------------
start_cron() {
    echo "[CRON] Starting cron daemon for scheduled syncs..."

    # Snapshot the container environment for the cron job. cron scrubs the
    # environment before running a job, so without this the scheduled sync would
    # not see DATABASE_MYSQL_*, TMDB_API_KEY, OMDB_API_KEY or ENCRYPTION_KEY and
    # would fall back to an empty SQLite database (see run-cron-job.sh).
    if [ -f "/app/build/scripts/write-cron-env.sh" ]; then
        if /bin/bash /app/build/scripts/write-cron-env.sh /app/storage/.cron-env; then
            # The jobs run as www-data (see /etc/cron.d/pathary-sync); the file
            # holds credentials, so keep it private to that user.
            chown www-data:www-data /app/storage/.cron-env
            chmod 600 /app/storage/.cron-env
        else
            echo "[CRON] WARNING: could not persist cron environment"
        fi
    fi

    # Start cron in the background
    cron

    echo "[CRON] Cron daemon started (jobs in /etc/cron.d/pathary-sync)"
}

# -----------------------------------------------------------------------------
# Trust X-Forwarded-For / X-Forwarded-Proto only from the configured proxies
# -----------------------------------------------------------------------------
# TRUSTED_PROXIES: comma or space separated IPs/CIDRs of the reverse proxy
# (e.g. Nginx Proxy Manager). For requests whose TCP peer is one of them, Apache
# replaces REMOTE_ADDR with the client IP from X-Forwarded-For and sets
# HTTPS=on when X-Forwarded-Proto is https. Unset means no proxy is trusted and
# both headers are ignored, so a client can never spoof its address or scheme.
configure_trusted_proxies() {
    local conf=/etc/apache2/conf-enabled/zz-pathary-remoteip.conf
    rm -f "$conf"

    local -a proxy_list
    read -r -a proxy_list <<< "${TRUSTED_PROXIES//,/ }"
    if [ "${#proxy_list[@]}" -eq 0 ]; then
        echo "[PROXY] TRUSTED_PROXIES not set: X-Forwarded-For/-Proto are ignored"
        return 0
    fi

    local proxy proxies="${proxy_list[*]}" peer_match=""
    for proxy in "${proxy_list[@]}"; do
        # Only IPv4/IPv6 addresses with an optional prefix length; anything else
        # could inject directives into the generated Apache config.
        if ! printf '%s' "$proxy" | grep -Eq '^[0-9A-Fa-f:.]+(/[0-9]{1,3})?$'; then
            echo "[PROXY] ERROR: invalid TRUSTED_PROXIES entry: $proxy" >&2
            exit 1
        fi
        peer_match="${peer_match:+$peer_match || }%{CONN_REMOTE_ADDR} -ipmatch '$proxy'"
    done

    # CONN_REMOTE_ADDR is the TCP peer (the proxy), not the client address that
    # mod_remoteip puts into REMOTE_ADDR, so X-Forwarded-Proto is only honoured
    # when the request really came through a trusted proxy.
    {
        echo "RemoteIPHeader X-Forwarded-For"
        echo "RemoteIPInternalProxy $proxies"
        echo "SetEnvIfExpr \"req('X-Forwarded-Proto') == 'https' && ($peer_match)\" HTTPS=on"
    } > "$conf"

    if ! apache2ctl -t >/dev/null 2>&1; then
        echo "[PROXY] ERROR: generated Apache config is invalid:" >&2
        cat "$conf" >&2
        exit 1
    fi

    echo "[PROXY] Trusting X-Forwarded-For/-Proto from: $proxies"
}

# -----------------------------------------------------------------------------
# Main execution
# -----------------------------------------------------------------------------
main() {
    validate_env
    configure_trusted_proxies
    setup_storage
    wait_for_mysql
    run_migrations
    setup_symlinks
    start_cron

    echo "=========================================="
    echo "Pathary is ready!"
    echo "=========================================="

    # Execute the main command (apache2-foreground)
    exec "$@"
}

main "$@"
