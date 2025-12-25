<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserInvitationTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            DROP TABLE `user_invitation`
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `user_invitation` (
                `id` INTEGER PRIMARY KEY,
                `user_id` INTEGER NOT NULL,
                `token` TEXT NOT NULL UNIQUE,
                `expires_at` TEXT NOT NULL,
                `used_at` TEXT DEFAULT NULL,
                `created_at` TEXT NOT NULL,
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE
            )
            SQL,
        );
    }
}
