<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddServerSettingMetadataTable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute('DROP TABLE `server_setting_metadata`');
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            CREATE TABLE `server_setting_metadata` (
                `key` VARCHAR(255) NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `updated_by_user_id` INT(10) UNSIGNED DEFAULT NULL,
                PRIMARY KEY (`key`),
                FOREIGN KEY (`updated_by_user_id`) REFERENCES `user`(`id`) ON DELETE SET NULL
            ) COLLATE="utf8mb4_unicode_ci" ENGINE=InnoDB
            SQL,
        );
    }
}
