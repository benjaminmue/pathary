<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWatchedDateAndLocationToMovieUserRating extends AbstractMigration
{
    public function down() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating
                DROP COLUMN watched_year,
                DROP COLUMN watched_month,
                DROP COLUMN watched_day,
                DROP COLUMN location_id;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating
                ADD COLUMN watched_year SMALLINT UNSIGNED DEFAULT NULL,
                ADD COLUMN watched_month TINYINT UNSIGNED DEFAULT NULL,
                ADD COLUMN watched_day TINYINT UNSIGNED DEFAULT NULL,
                ADD COLUMN location_id TINYINT UNSIGNED DEFAULT NULL;
            SQL,
        );
    }
}
