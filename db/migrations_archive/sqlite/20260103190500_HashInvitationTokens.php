<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class HashInvitationTokens extends AbstractMigration
{
    public function down() : void
    {
        // Rename column back to 'token' (note: this will lose data)
        $this->execute(
            <<<SQL
            -- SQLite doesn't support direct column rename, need to recreate table
            CREATE TABLE user_invitation_backup (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
            );

            -- Cannot copy data as we can't reverse the hash
            -- This migration is effectively one-way

            DROP TABLE user_invitation;
            ALTER TABLE user_invitation_backup RENAME TO user_invitation;
            SQL,
        );
    }

    public function up() : void
    {
        // Rename 'token' column to 'token_hash'
        // This will invalidate all existing tokens (users must be re-invited)
        $this->execute(
            <<<SQL
            -- SQLite doesn't support ALTER COLUMN, need to recreate table
            CREATE TABLE user_invitation_new (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
            );

            -- Do NOT copy existing data - tokens need to be re-generated as hashed
            -- Existing plaintext tokens cannot be converted to hashes

            DROP TABLE user_invitation;
            ALTER TABLE user_invitation_new RENAME TO user_invitation;
            SQL,
        );
    }
}
