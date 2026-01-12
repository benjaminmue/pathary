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
            ALTER TABLE `user_auth_token` ADD COLUMN `token_hash` VARCHAR(64) DEFAULT NULL AFTER `token`;
            ALTER TABLE `user_auth_token` ADD COLUMN `last_used_at` DATETIME DEFAULT NULL AFTER `created_at`;
            UPDATE `user_auth_token` SET `token_hash` = SHA2(`token`, 256) WHERE `token_hash` IS NULL;
            ALTER TABLE `user_auth_token` ADD INDEX `idx_token_hash` (`token_hash`);
            SQL,
        );
    }
}
