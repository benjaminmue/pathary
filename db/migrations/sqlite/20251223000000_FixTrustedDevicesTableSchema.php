<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FixTrustedDevicesTableSchema extends AbstractMigration
{
    public function up() : void
    {
        // SQLite doesn't support RENAME COLUMN in all versions
        // Check if old schema exists
        $hasOldSchema = $this->hasColumn('user_trusted_devices', 'device_token');

        if ($hasOldSchema === true) {
            // SQLite: Recreate table with correct schema
            $this->execute(
                <<<SQL
                -- Create new table with correct schema
                CREATE TABLE `user_trusted_devices_new` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `token_hash` VARCHAR(255) NOT NULL UNIQUE,
                    `device_name` VARCHAR(256) NOT NULL,
                    `user_agent` TEXT DEFAULT NULL,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `last_used_at` DATETIME DEFAULT NULL,
                    FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
                );

                -- Copy data from old table (mapping old column names to new)
                INSERT INTO `user_trusted_devices_new`
                    (`id`, `user_id`, `token_hash`, `device_name`, `user_agent`, `ip_address`, `expires_at`, `created_at`, `last_used_at`)
                SELECT
                    `id`, `user_id`, `device_token`, `device_name`, `device_fingerprint`, NULL, `expires_at`, `created_at`, `last_used_at`
                FROM `user_trusted_devices`;

                -- Drop old table
                DROP TABLE `user_trusted_devices`;

                -- Rename new table
                ALTER TABLE `user_trusted_devices_new` RENAME TO `user_trusted_devices`;

                -- Recreate indexes
                CREATE INDEX idx_user_trusted_devices_user_id ON `user_trusted_devices` (`user_id`);
                CREATE UNIQUE INDEX idx_user_trusted_devices_token_hash ON `user_trusted_devices` (`token_hash`);
                CREATE INDEX idx_user_trusted_devices_expires_at ON `user_trusted_devices` (`expires_at`);
                SQL,
            );
        }

        // If correct schema already exists, do nothing
    }

    public function down() : void
    {
        // Rollback: revert to old schema if it was changed
        $hasNewSchema = $this->hasColumn('user_trusted_devices', 'token_hash');

        if ($hasNewSchema === true) {
            $this->execute(
                <<<SQL
                -- Create table with old schema
                CREATE TABLE `user_trusted_devices_old` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `device_token` VARCHAR(255) NOT NULL UNIQUE,
                    `device_name` VARCHAR(256) NOT NULL,
                    `device_fingerprint` TEXT NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `last_used_at` DATETIME DEFAULT NULL,
                    FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
                );

                -- Copy data back (mapping new column names to old)
                INSERT INTO `user_trusted_devices_old`
                    (`id`, `user_id`, `device_token`, `device_name`, `device_fingerprint`, `expires_at`, `created_at`, `last_used_at`)
                SELECT
                    `id`, `user_id`, `token_hash`, `device_name`, COALESCE(`user_agent`, ''), `expires_at`, `created_at`, `last_used_at`
                FROM `user_trusted_devices`;

                -- Drop new table
                DROP TABLE `user_trusted_devices`;

                -- Rename old table back
                ALTER TABLE `user_trusted_devices_old` RENAME TO `user_trusted_devices`;

                -- Recreate indexes
                CREATE INDEX idx_user_trusted_devices_user_id ON `user_trusted_devices` (`user_id`);
                CREATE UNIQUE INDEX idx_user_trusted_devices_token_hash ON `user_trusted_devices` (`device_token`);
                CREATE INDEX idx_user_trusted_devices_expires_at ON `user_trusted_devices` (`expires_at`);
                SQL,
            );
        }
    }

    private function hasColumn(string $table, string $column) : bool
    {
        $rows = $this->fetchAll(
            "SELECT name FROM pragma_table_info('$table') WHERE name = '$column'"
        );

        return count($rows) > 0;
    }
}
