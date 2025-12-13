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
            if php -r "
                try {
                    new PDO(
                        'mysql:host=${DATABASE_MYSQL_HOST};port=${DATABASE_MYSQL_PORT:-3306}',
                        '${DATABASE_MYSQL_USER}',
                        '${DATABASE_MYSQL_PASSWORD}',
                        [PDO::ATTR_TIMEOUT => 5]
                    );
                    exit(0);
                } catch (Exception \$e) {
                    exit(1);
                }
            " 2>/dev/null; then
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
    echo "[DB] Running database migrations..."

    if [ -f "/app/bin/console.php" ]; then
        php /app/bin/console.php database:migration:migrate --no-interaction || {
            echo "[DB] ERROR: Migration failed"
            exit 1
        }
        echo "[DB] Migrations completed successfully"
    else
        echo "[DB] No console.php found, skipping migrations"
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
        php /app/bin/console.php storage:create-public-link 2>/dev/null || true
    fi

    echo "[SYMLINK] Symlinks configured"
}

# -----------------------------------------------------------------------------
# Main execution
# -----------------------------------------------------------------------------
main() {
    validate_env
    setup_storage
    wait_for_mysql
    run_migrations
    setup_symlinks

    echo "=========================================="
    echo "Pathary is ready!"
    echo "=========================================="

    # Execute the main command (apache2-foreground)
    exec "$@"
}

main "$@"
