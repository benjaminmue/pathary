# Database Migration Consolidation Guide

## Current Situation
- **MySQL Migrations**: 97 files
- **SQLite Migrations**: 55 files
- **Database**: `movary` (to be renamed to `pathary` later)
- **Tables**: 36 tables

## Recommended Approach: Fresh Start

Since this is a new project with no production installations, we can consolidate all migrations into a single clean initial migration.

### Step 1: Archive Old Migrations

```bash
# Move old migrations to archive
mv db/migrations/mysql/*.php db/migrations_archive/mysql/
mv db/migrations/sqlite/*.php db/migrations_archive/sqlite/
```

### Step 2: Export Current Schema

```bash
# Export MySQL schema (no data, just structure)
docker compose exec mysql mysqldump -uroot -proot \
  --no-data \
  --skip-add-drop-table \
  --skip-comments \
  --skip-triggers \
  movary > db/schema_mysql.sql

# Export SQLite schema (if using SQLite)
docker compose exec app sqlite3 /app/storage/database/application.db .schema > db/schema_sqlite.sql
```

### Step 3: Create New Consolidated Migration

Use Phinx to create a new migration:

```bash
# Create new initial migration
php vendor/bin/phinx create InitialSchema -c ./settings/phinx.php
```

This will create:
- `db/migrations/mysql/YYYYMMDDHHMMSS_initial_schema.php`
- `db/migrations/sqlite/YYYYMMDDHHMMSS_initial_schema.php`

### Step 4: Populate the Migration

Copy the schema from `db/schema_mysql.sql` and `db/schema_sqlite.sql` into the new migration files.

**Example structure**:

```php
<?php
use Phinx\Migration\AbstractMigration;

class InitialSchema extends AbstractMigration
{
    public function up(): void
    {
        // Create all 36 tables here
        $this->execute(<<<SQL
            CREATE TABLE `location` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int unsigned DEFAULT NULL,
              `name` text NOT NULL,
              `is_cinema` tinyint(1) DEFAULT '0',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `user_id` (`user_id`),
              CONSTRAINT `location_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        SQL);

        // Insert default locations
        $this->execute(<<<SQL
            INSERT INTO `location` (`name`, `user_id`, `created_at`)
            SELECT * FROM (
                SELECT 'Cinema' AS name, NULL AS user_id, NOW() AS created_at
                UNION ALL
                SELECT 'Home' AS name, NULL AS user_id, NOW() AS created_at
                UNION ALL
                SELECT 'Other' AS name, NULL AS user_id, NOW() AS created_at
            ) AS defaults
            WHERE NOT EXISTS (
                SELECT 1 FROM `location` WHERE `user_id` IS NULL
            )
        SQL);

        // ... rest of tables
    }

    public function down(): void
    {
        // Drop all tables in reverse dependency order
        $this->execute('DROP TABLE IF EXISTS `location`');
        // ... rest of tables
    }
}
```

### Step 5: Reset Migration Tracking (For Fresh Installs Only!)

⚠️ **WARNING**: Only do this on your development machine!

```bash
# Clear migration history
docker compose exec mysql mysql -uroot -proot movary -e "TRUNCATE TABLE phinxlog;"

# Run new consolidated migration
make app_database_migrate
```

### Step 6: Test Fresh Installation

```bash
# Stop containers
docker compose down -v

# Remove database volume (THIS DELETES ALL DATA!)
docker volume rm pathary_mysql_data

# Start fresh
docker compose up -d

# Run migration
make app_database_migrate

# Verify all tables exist
docker compose exec mysql mysql -uroot -proot movary -e "SHOW TABLES;"
```

---

## Alternative: Automated Consolidation Script

I can also create a script that automatically:
1. Exports current schema
2. Creates consolidated migration
3. Archives old migrations
4. Updates phinxlog

Would you like me to create this automated script?

---

## Benefits of Consolidation

1. ✅ **Faster Setup**: New installations run 1 migration instead of 97
2. ✅ **Cleaner History**: Easy to understand the entire schema
3. ✅ **Easier Maintenance**: One file to update vs many
4. ✅ **Better Documentation**: Single source of truth for schema
5. ✅ **Reduced Complexity**: No need to track 97 migration files

---

## Important Notes

- Keep archived migrations in `db/migrations_archive/` for reference
- Add `db/migrations_archive/` to `.gitignore` if you don't want to track them
- Document the consolidation date in `CHANGELOG.md`
- Consider this the "v1.0" schema baseline

---

## Next Steps

After consolidation:
1. Update `README.md` to mention this is a fresh installation
2. Tag this as `v1.0.0-alpha` or similar
3. Future migrations will be incremental from this baseline
