<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPopcornRatingToMovieUserRating extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating DROP COLUMN rating_popcorn;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating ADD COLUMN rating_popcorn INTEGER DEFAULT NULL;
            SQL,
        );
    }
}
