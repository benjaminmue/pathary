<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSecurityAuditLogTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute('DROP TABLE IF EXISTS `user_security_audit_log`');
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `user_security_audit_log` (
                `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT(10) UNSIGNED NOT NULL,
                `event_type` VARCHAR(50) NOT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` TEXT DEFAULT NULL,
                `metadata` TEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE,
                INDEX idx_user_security_audit_log_user_id (`user_id`),
                INDEX idx_user_security_audit_log_event_type (`event_type`),
                INDEX idx_user_security_audit_log_created_at (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
    }
}
