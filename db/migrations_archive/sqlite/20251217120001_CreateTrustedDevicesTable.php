<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTrustedDevicesTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute('DROP TABLE IF EXISTS `user_trusted_devices`');
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `user_trusted_devices` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `user_id` INTEGER NOT NULL,
                `token_hash` TEXT NOT NULL UNIQUE,
                `device_name` TEXT NOT NULL,
                `user_agent` TEXT DEFAULT NULL,
                `ip_address` TEXT DEFAULT NULL,
                `expires_at` TEXT NOT NULL,
                `created_at` TEXT NOT NULL,
                `last_used_at` TEXT DEFAULT NULL,
                FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
            )
            SQL,
        );

        $this->execute(
            'CREATE INDEX idx_user_trusted_devices_user_id ON user_trusted_devices(user_id)',
        );

        $this->execute(
            'CREATE UNIQUE INDEX idx_user_trusted_devices_token_hash ON user_trusted_devices(token_hash)',
        );

        $this->execute(
            'CREATE INDEX idx_user_trusted_devices_expires_at ON user_trusted_devices(expires_at)',
        );
    }
}
