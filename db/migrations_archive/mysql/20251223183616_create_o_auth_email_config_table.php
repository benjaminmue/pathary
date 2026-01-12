<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOAuthEmailConfigTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute('DROP TABLE `oauth_email_config`');
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `oauth_email_config` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `provider` VARCHAR(50) NOT NULL COMMENT 'OAuth provider: gmail or microsoft',
                `client_id` VARCHAR(255) NOT NULL COMMENT 'Public OAuth client identifier',
                `client_secret_encrypted` TEXT NOT NULL COMMENT 'Encrypted client secret (AES-256-CBC + base64)',
                `client_secret_iv` VARCHAR(255) NOT NULL COMMENT 'Initialization vector for client_secret decryption',
                `tenant_id` VARCHAR(255) DEFAULT NULL COMMENT 'Microsoft only: Azure AD tenant ID',
                `refresh_token_encrypted` TEXT DEFAULT NULL COMMENT 'Encrypted refresh token (AES-256-CBC + base64)',
                `refresh_token_iv` VARCHAR(255) DEFAULT NULL COMMENT 'Initialization vector for refresh_token decryption',
                `from_address` VARCHAR(255) NOT NULL COMMENT 'Email address to send from (must match OAuth account)',
                `scopes` VARCHAR(500) DEFAULT NULL COMMENT 'OAuth scopes granted (space-separated)',
                `token_status` VARCHAR(50) NOT NULL DEFAULT 'not_connected' COMMENT 'Status: not_connected, active, expired, error',
                `token_error` TEXT DEFAULT NULL COMMENT 'Last error message from OAuth provider',
                `client_secret_expires_at` DATETIME DEFAULT NULL COMMENT 'When client secret expires (Microsoft only)',
                `connected_at` DATETIME DEFAULT NULL COMMENT 'When OAuth connection was established',
                `last_token_refresh_at` DATETIME DEFAULT NULL COMMENT 'Last time access token was refreshed',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_provider` (`provider`),
                INDEX `idx_token_status` (`token_status`)
            ) COLLATE="utf8mb4_unicode_ci" ENGINE=InnoDB COMMENT='OAuth 2.0 email configuration for Gmail and Microsoft 365'
            SQL,
        );
    }
}
