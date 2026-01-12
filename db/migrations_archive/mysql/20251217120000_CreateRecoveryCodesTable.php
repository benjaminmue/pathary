<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRecoveryCodesTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute('DROP TABLE IF EXISTS `user_recovery_codes`');
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `user_recovery_codes` (
                `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT(10) UNSIGNED NOT NULL,
                `code_hash` VARCHAR(255) NOT NULL,
                `used_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE,
                INDEX idx_user_recovery_codes_user_id (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
    }
}
