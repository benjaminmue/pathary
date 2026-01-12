<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCommentToMovieUserRating extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating DROP COLUMN comment;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating ADD COLUMN comment TEXT DEFAULT NULL;
            SQL,
        );
    }
}
