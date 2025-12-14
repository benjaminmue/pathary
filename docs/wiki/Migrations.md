# Migrations

This page covers database schema management using Phinx migrations.

## Overview

Pathary uses [Phinx](https://phinx.org/) for database migrations. Migrations are PHP classes that define schema changes.

## Migration Locations

```
db/migrations/
├── mysql/      # MySQL-specific migrations
└── sqlite/     # SQLite-specific migrations
```

Each database type has its own migration set due to syntax differences.

## Configuration

**File**: `settings/phinx.php`

```php
return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/../db/migrations/' . $databaseMode,
    ],
    'environments' => [
        'default_environment' => $databaseMode,
        'mysql' => [
            'adapter' => 'mysql',
            'host' => $config->getAsString('DATABASE_MYSQL_HOST'),
            'name' => $config->getAsString('DATABASE_MYSQL_NAME'),
            'user' => $config->getAsString('DATABASE_MYSQL_USER'),
            'pass' => $config->getAsString('DATABASE_MYSQL_PASSWORD'),
            // ...
        ],
        'sqlite' => [
            'adapter' => 'sqlite',
            'name' => $config->getAsString('DATABASE_SQLITE'),
        ],
    ],
];
```

## When Migrations Run

### Automatic (Container Startup)

**File**: `build/scripts/entrypoint.sh`

```bash
if [ "$DATABASE_DISABLE_AUTO_MIGRATION" != "true" ]; then
  RETRY_COUNT=0
  MAX_RETRIES=5

  while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    /usr/bin/php /app/bin/console.php database:migration:migrate

    if [ $? -eq 0 ]; then
      echo "SUCCESS: Automatic database migration succeeded"
      break
    else
      RETRY_COUNT=$((RETRY_COUNT + 1))
      echo "ERROR: Automatic database migration failed, attempt $RETRY_COUNT"
      sleep 5
    fi
  done
fi
```

Key behaviors:
- Runs on every container start (unless disabled)
- Retries up to 5 times with 5-second delays
- Useful for waiting on database availability

### Disable Auto-Migration

```env
DATABASE_DISABLE_AUTO_MIGRATION=true
```

### Manual Execution

```bash
# Inside container
php bin/console.php database:migration:migrate

# From host via Docker
docker exec pathary-app php bin/console.php database:migration:migrate
```

## CLI Commands

### Check Status

```bash
docker exec pathary-app php bin/console.php database:migration:status
```

Output:
```
 Status  [Migration ID]   Migration Name
-----------------------------------------
     up  20210124104021   SetupBaseTables
     up  20220510185016   AddUser
     up  20251214000000   AddWatchedDateAndLocationToMovieUserRating
```

### Run Migrations

```bash
# Run all pending
docker exec pathary-app php bin/console.php database:migration:migrate

# Dry run (show what would run)
docker exec pathary-app php bin/console.php database:migration:migrate --dry-run
```

### Rollback

```bash
# Rollback last migration
docker exec pathary-app php bin/console.php database:migration:rollback

# Rollback to specific version
docker exec pathary-app php bin/console.php database:migration:rollback -t 20220510185016
```

## Migration Structure

### File Naming

```
{timestamp}_{MigrationName}.php

Example:
20251214000000_AddWatchedDateAndLocationToMovieUserRating.php
```

The timestamp format is `YYYYMMDDHHmmss`.

### Migration Class

```php
<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWatchedDateAndLocationToMovieUserRating extends AbstractMigration
{
    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating
                ADD COLUMN watched_year SMALLINT UNSIGNED DEFAULT NULL,
                ADD COLUMN watched_month TINYINT UNSIGNED DEFAULT NULL,
                ADD COLUMN watched_day TINYINT UNSIGNED DEFAULT NULL,
                ADD COLUMN location_id TINYINT UNSIGNED DEFAULT NULL;
            SQL,
        );
    }

    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating
                DROP COLUMN watched_year,
                DROP COLUMN watched_month,
                DROP COLUMN watched_day,
                DROP COLUMN location_id;
            SQL,
        );
    }
}
```

## Creating a New Migration

### 1. Create Migration File

Create matching files for both MySQL and SQLite:

```bash
# MySQL
touch db/migrations/mysql/20251215120000_AddNewFeature.php

# SQLite
touch db/migrations/sqlite/20251215120000_AddNewFeature.php
```

### 2. Write Migration Logic

**MySQL version** (`db/migrations/mysql/20251215120000_AddNewFeature.php`):

```php
<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNewFeature extends AbstractMigration
{
    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie ADD COLUMN new_field VARCHAR(256) DEFAULT NULL;
            SQL,
        );
    }

    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie DROP COLUMN new_field;
            SQL,
        );
    }
}
```

**SQLite version** (`db/migrations/sqlite/20251215120000_AddNewFeature.php`):

```php
<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNewFeature extends AbstractMigration
{
    public function up() : void
    {
        // SQLite requires separate ALTER statements
        $this->execute('ALTER TABLE movie ADD COLUMN new_field TEXT DEFAULT NULL;');
    }

    public function down() : void
    {
        // SQLite <3.35 doesn't support DROP COLUMN
        // Use table recreation for older versions
        $this->execute(
            <<<SQL
            CREATE TABLE movie_backup AS SELECT ... FROM movie;
            DROP TABLE movie;
            CREATE TABLE movie (...);
            INSERT INTO movie SELECT ... FROM movie_backup;
            DROP TABLE movie_backup;
            SQL,
        );
    }
}
```

### 3. Test Migration

```bash
# Run migration
docker exec pathary-app php bin/console.php database:migration:migrate

# Check status
docker exec pathary-app php bin/console.php database:migration:status

# Test rollback
docker exec pathary-app php bin/console.php database:migration:rollback
```

## Conventions

### Naming

- Use descriptive names: `AddCommentToMovieUserRating`, `RemoveDeprecatedColumn`
- Match class name to filename

### SQL Syntax

- MySQL and SQLite have different syntax for some operations
- Always create both versions
- Test both database modes

### Down Methods

- Always implement `down()` for rollback capability
- Be careful with data loss (DROP COLUMN deletes data)

### Indexes

```php
// MySQL
$this->execute('CREATE INDEX idx_name ON table(column);');

// SQLite
$this->execute('CREATE INDEX IF NOT EXISTS idx_name ON table(column);');
```

## Troubleshooting

### Migration Fails on Startup

Check logs:
```bash
docker compose logs pathary
```

Common causes:
- Database not ready (retries should help)
- Syntax error in migration
- Missing database permissions

### "Table already exists"

If a migration partially ran:
```bash
# Check migration status
docker exec pathary-app php bin/console.php database:migration:status

# Manually mark as run (if table exists)
# Edit phinxlog table directly
```

### Rollback Fails

- Check that `down()` is implemented
- Verify rollback SQL is valid
- Some operations (data deletion) cannot be undone

### SQLite DROP COLUMN Issues

SQLite versions before 3.35.0 don't support `DROP COLUMN`. Use table recreation:

```php
public function down() : void
{
    // Create backup without the column
    $this->execute('CREATE TABLE tmp AS SELECT col1, col2 FROM original;');
    $this->execute('DROP TABLE original;');
    $this->execute('ALTER TABLE tmp RENAME TO original;');
}
```

## Migration History

The `phinxlog` table tracks which migrations have run:

```sql
SELECT * FROM phinxlog ORDER BY version DESC LIMIT 5;
```

| version | migration_name | start_time | end_time | breakpoint |
|---------|----------------|------------|----------|------------|
| 20251214000000 | AddWatchedDateAndLocation | 2025-12-14 15:30:00 | 2025-12-14 15:30:01 | 0 |

## Related Pages

- [Database](Database.md) - Schema overview
- [Getting Started](Getting-Started.md) - Initial setup
- [Deployment](Deployment.md) - Production considerations

---

[← Back to Wiki Home](README.md)
