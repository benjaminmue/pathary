<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOAuthEmailConfigTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            DROP TABLE `oauth_email_config`
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `oauth_email_config` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                `provider` TEXT NOT NULL,
                `client_id` TEXT NOT NULL,
                `client_secret_encrypted` TEXT NOT NULL,
                `client_secret_iv` TEXT NOT NULL,
                `tenant_id` TEXT DEFAULT NULL,
                `refresh_token_encrypted` TEXT DEFAULT NULL,
                `refresh_token_iv` TEXT DEFAULT NULL,
                `from_address` TEXT NOT NULL,
                `scopes` TEXT DEFAULT NULL,
                `token_status` TEXT NOT NULL DEFAULT 'not_connected',
                `token_error` TEXT DEFAULT NULL,
                `client_secret_expires_at` TEXT DEFAULT NULL,
                `connected_at` TEXT DEFAULT NULL,
                `last_token_refresh_at` TEXT DEFAULT NULL,
                `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL,
        );

        // Create indexes
        $this->execute('CREATE INDEX `idx_provider` ON `oauth_email_config` (`provider`)');
        $this->execute('CREATE INDEX `idx_token_status` ON `oauth_email_config` (`token_status`)');
    }
}
