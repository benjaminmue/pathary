<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FixTrustedDevicesTableSchema extends AbstractMigration
{
    public function up() : void
    {
        // Check if old column names exist (production case)
        $hasOldSchema = $this->hasColumn('user_trusted_devices', 'device_token');

        if ($hasOldSchema === true) {
            // Production: Fix incorrect schema
            $this->execute(
                <<<SQL
                ALTER TABLE `user_trusted_devices`
                  CHANGE COLUMN `device_token` `token_hash` VARCHAR(255) NOT NULL,
                  CHANGE COLUMN `device_fingerprint` `user_agent` TEXT DEFAULT NULL,
                  ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL AFTER `user_agent`
                SQL,
            );
        }

        // If the correct schema already exists (local dev), do nothing
        // Migration is idempotent and safe to run in both environments
    }

    public function down() : void
    {
        // Rollback: revert to old schema if it was changed
        $hasNewSchema = $this->hasColumn('user_trusted_devices', 'token_hash');

        if ($hasNewSchema === true) {
            $this->execute(
                <<<SQL
                ALTER TABLE `user_trusted_devices`
                  DROP COLUMN IF EXISTS `ip_address`,
                  CHANGE COLUMN `token_hash` `device_token` VARCHAR(255) NOT NULL,
                  CHANGE COLUMN `user_agent` `device_fingerprint` TEXT NOT NULL
                SQL,
            );
        }
    }

    private function hasColumn(string $table, string $column) : bool
    {
        $rows = $this->fetchAll(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '$table'
               AND COLUMN_NAME = '$column'"
        );

        return count($rows) > 0;
    }
}
