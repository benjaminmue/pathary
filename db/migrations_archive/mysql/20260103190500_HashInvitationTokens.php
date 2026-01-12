<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class HashInvitationTokens extends AbstractMigration
{
    public function down() : void
    {
        // Rename column back to 'token' (note: this will lose data as we can't reverse the hash)
        $this->execute(
            <<<SQL
            ALTER TABLE `user_invitation` CHANGE COLUMN `token_hash` `token` VARCHAR(255) NOT NULL UNIQUE;
            -- Clear all existing records as we cannot reverse hashes
            TRUNCATE TABLE `user_invitation`;
            SQL,
        );
    }

    public function up() : void
    {
        // Rename 'token' column to 'token_hash'
        // This will invalidate all existing tokens (users must be re-invited)
        $this->execute(
            <<<SQL
            -- Clear existing tokens first (plaintext tokens cannot be converted to hashes)
            TRUNCATE TABLE `user_invitation`;

            -- Rename column
            ALTER TABLE `user_invitation` CHANGE COLUMN `token` `token_hash` VARCHAR(255) NOT NULL UNIQUE;
            SQL,
        );
    }
}
