<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRecoveryCodesTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute('DROP TABLE IF EXISTS `user_recovery_codes`');
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `user_recovery_codes` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `user_id` INTEGER NOT NULL,
                `code_hash` TEXT NOT NULL,
                `used_at` TEXT DEFAULT NULL,
                `created_at` TEXT NOT NULL,
                FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
            )
            SQL,
        );

        $this->execute(
            'CREATE INDEX idx_user_recovery_codes_user_id ON user_recovery_codes(user_id)',
        );
    }
}
