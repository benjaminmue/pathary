<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddOAuthMonitoringFields extends AbstractMigration
{
    public function down() : void
    {
        // Remove oauth_admin_banner_ack table
        $this->execute('DROP TABLE IF EXISTS `oauth_admin_banner_ack`');

        // Remove monitoring fields from oauth_email_config
        $this->execute(
            <<<SQL
            ALTER TABLE `oauth_email_config`
                DROP COLUMN `last_failure_at`,
                DROP COLUMN `last_error_code`,
                DROP COLUMN `reauth_required`,
                DROP COLUMN `alert_level`,
                DROP COLUMN `next_notification_at`
            SQL,
        );
    }

    public function up() : void
    {
        // Add monitoring fields to oauth_email_config table
        $this->execute(
            <<<SQL
            ALTER TABLE `oauth_email_config`
                ADD COLUMN `last_failure_at` DATETIME DEFAULT NULL COMMENT 'Timestamp of last token refresh failure',
                ADD COLUMN `last_error_code` VARCHAR(100) DEFAULT NULL COMMENT 'Last OAuth error code (sanitized)',
                ADD COLUMN `reauth_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether re-authorization is required',
                ADD COLUMN `alert_level` VARCHAR(20) NOT NULL DEFAULT 'ok' COMMENT 'Alert level: ok, warn, critical, expired',
                ADD COLUMN `next_notification_at` DATETIME DEFAULT NULL COMMENT 'Next scheduled notification time',
                ADD INDEX `idx_alert_level` (`alert_level`),
                ADD INDEX `idx_next_notification_at` (`next_notification_at`)
            SQL,
        );

        // Create table for per-admin banner acknowledgements
        $this->execute(
            <<<SQL
            CREATE TABLE `oauth_admin_banner_ack` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) UNSIGNED NOT NULL COMMENT 'Admin user who acknowledged',
                `alert_level_acked` VARCHAR(20) NOT NULL COMMENT 'Alert level at time of acknowledgement',
                `acked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When banner was acknowledged',
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_user_alert` (`user_id`, `alert_level_acked`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_acked_at` (`acked_at`),
                CONSTRAINT `fk_oauth_banner_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
            ) COLLATE="utf8mb4_unicode_ci" ENGINE=InnoDB COMMENT='Admin acknowledgements of OAuth monitoring banners'
            SQL,
        );
    }
}
