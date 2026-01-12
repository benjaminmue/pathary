<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddProfileImageToUser extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE user DROP COLUMN profile_image;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE user ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL;
            SQL,
        );
    }
}
