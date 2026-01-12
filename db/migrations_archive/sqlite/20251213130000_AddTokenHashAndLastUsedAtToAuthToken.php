<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTokenHashAndLastUsedAtToAuthToken extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE `user_auth_token` DROP COLUMN `token_hash`;
            ALTER TABLE `user_auth_token` DROP COLUMN `last_used_at`;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE `user_auth_token` ADD COLUMN `token_hash` TEXT DEFAULT NULL;
            ALTER TABLE `user_auth_token` ADD COLUMN `last_used_at` TEXT DEFAULT NULL;
            SQL,
        );

        // SQLite doesn't have SHA2, so we'll compute hashes in PHP during token validation
        // Existing tokens will be migrated on first use
        $this->execute(
            <<<SQL
            CREATE INDEX IF NOT EXISTS `idx_token_hash` ON `user_auth_token` (`token_hash`);
            SQL,
        );
    }
}
