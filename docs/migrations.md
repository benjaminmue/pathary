# Database Migrations

Pathary uses [Phinx](https://phinx.org/) for database migrations. This document explains how migrations are handled in different environments.

## Automatic Migrations at Container Startup

When the Pathary container starts, it automatically runs pending database migrations before the application becomes available. This ensures:

- Production deployments always have the correct schema
- No manual intervention required after pulling a new image
- Container fails fast if migrations are broken (prevents running with outdated schema)

### Startup Sequence

1. Validate environment variables
2. Set up storage directories
3. Wait for MySQL to be ready (if using MySQL)
4. **Run database migrations** (if `MIGRATIONS_AUTO_RUN=1`)
5. Set up symlinks
6. Start Apache

### Disabling Automatic Migrations

In some scenarios (debugging, manual control), you may want to disable automatic migrations:

```bash
docker run -e MIGRATIONS_AUTO_RUN=0 ...
```

When `MIGRATIONS_AUTO_RUN=0`:
- Migrations are skipped at startup
- Container starts with whatever schema exists
- You must run migrations manually

## Manual Migration Commands

### Run Migrations

```bash
# Inside the container
php /app/vendor/bin/phinx migrate -c /app/settings/phinx.php

# Or via Docker
docker exec pathary-app php /app/vendor/bin/phinx migrate -c /app/settings/phinx.php
```

### Check Migration Status

```bash
docker exec pathary-app php /app/vendor/bin/phinx status -c /app/settings/phinx.php
```

### Rollback Last Migration

```bash
docker exec pathary-app php /app/vendor/bin/phinx rollback -c /app/settings/phinx.php
```

## CI/CD Validation

The GitHub Actions workflow validates migrations on every push to `main` and on every tag:

### Workflow Steps

1. **Build**: Docker image is built (no database access)
2. **Validate Migrations**:
   - Starts a MySQL 8.0 service container
   - Runs all migrations against a fresh database
   - Verifies migration status
   - Fails the workflow if any migration fails
3. **Push**: Only if migrations pass, the image is pushed to GHCR

This ensures broken migrations never make it to published images.

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MIGRATIONS_AUTO_RUN` | `1` | Set to `0` to disable automatic migrations at startup |
| `DATABASE_MODE` | `sqlite` | Database type: `mysql` or `sqlite` |
| `DATABASE_MYSQL_HOST` | - | MySQL host (required if mode=mysql) |
| `DATABASE_MYSQL_PORT` | `3306` | MySQL port |
| `DATABASE_MYSQL_NAME` | - | Database name (required if mode=mysql) |
| `DATABASE_MYSQL_USER` | - | Database user (required if mode=mysql) |
| `DATABASE_MYSQL_PASSWORD` | - | Database password (required if mode=mysql) |

## Creating New Migrations

Migrations are stored in:
- `db/migrations/mysql/` - MySQL migrations
- `db/migrations/sqlite/` - SQLite migrations

To create a new migration:

```bash
# Generate timestamp
date +%Y%m%d%H%M%S
# Example output: 20251213120000

# Create migration file
# db/migrations/mysql/20251213120000_AddNewFeature.php
```

Migration template:

```php
<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNewFeature extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            <<<SQL
            -- Your SQL here
            SQL,
        );
    }

    public function down(): void
    {
        $this->execute(
            <<<SQL
            -- Rollback SQL here
            SQL,
        );
    }
}
```

## Troubleshooting

### Container won't start after update

Check the logs for migration errors:

```bash
docker logs pathary-app
```

If migrations failed, you may need to:
1. Check if the database is accessible
2. Review the migration that failed
3. Manually fix the database state if needed

### Migration already ran but schema is wrong

Check the `phinxlog` table to see which migrations have been recorded:

```sql
SELECT * FROM phinxlog ORDER BY version DESC;
```

If a migration is marked as run but the schema doesn't reflect it, you may need to manually fix the schema or rollback and re-run.
