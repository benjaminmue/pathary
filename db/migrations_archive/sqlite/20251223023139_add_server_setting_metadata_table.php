<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddServerSettingMetadataTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            DROP TABLE `server_setting_metadata`
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `server_setting_metadata` (
                `key` TEXT NOT NULL PRIMARY KEY,
                `updated_at` TEXT NOT NULL,
                `updated_by_user_id` INTEGER DEFAULT NULL,
                FOREIGN KEY (`updated_by_user_id`) REFERENCES `user`(`id`) ON DELETE SET NULL
            )
            SQL,
        );
    }
}
