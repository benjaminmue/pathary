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
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `user_id` INTEGER NOT NULL,
                `event_type` TEXT NOT NULL,
                `ip_address` TEXT DEFAULT NULL,
                `user_agent` TEXT DEFAULT NULL,
                `metadata` TEXT DEFAULT NULL,
                `created_at` TEXT NOT NULL,
                FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
            )
            SQL,
        );

        $this->execute(
            'CREATE INDEX idx_user_security_audit_log_user_id ON user_security_audit_log(user_id)',
        );

        $this->execute(
            'CREATE INDEX idx_user_security_audit_log_event_type ON user_security_audit_log(event_type)',
        );

        $this->execute(
            'CREATE INDEX idx_user_security_audit_log_created_at ON user_security_audit_log(created_at)',
        );
    }
}
