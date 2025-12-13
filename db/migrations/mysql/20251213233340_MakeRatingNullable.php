<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MakeRatingNullable extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating MODIFY COLUMN rating TINYINT NOT NULL;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating MODIFY COLUMN rating TINYINT NULL DEFAULT NULL;
            SQL,
        );
    }
}
