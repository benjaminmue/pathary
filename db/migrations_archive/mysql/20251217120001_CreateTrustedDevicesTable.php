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
                `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT(10) UNSIGNED NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL UNIQUE,
                `device_name` VARCHAR(256) NOT NULL,
                `user_agent` TEXT DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                `last_used_at` DATETIME DEFAULT NULL,
                FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE,
                INDEX idx_user_trusted_devices_user_id (`user_id`),
                UNIQUE INDEX idx_user_trusted_devices_token_hash (`token_hash`),
                INDEX idx_user_trusted_devices_expires_at (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
    }
}
