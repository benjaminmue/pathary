<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWatchedDateAndLocationToMovieUserRating extends AbstractMigration
{
    public function down() : void
    {
        // SQLite doesn't support DROP COLUMN before version 3.35.0
        // Use table recreation approach
        $this->execute(
            <<<SQL
            CREATE TABLE movie_user_rating_backup AS SELECT id, movie_id, user_id, rating, rating_popcorn, comment, created_at, updated_at FROM movie_user_rating;
            DROP TABLE movie_user_rating;
            CREATE TABLE movie_user_rating (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                rating INTEGER,
                rating_popcorn INTEGER,
                comment TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME,
                UNIQUE(movie_id, user_id),
                FOREIGN KEY (movie_id) REFERENCES movie(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
            );
            INSERT INTO movie_user_rating SELECT * FROM movie_user_rating_backup;
            DROP TABLE movie_user_rating_backup;
            SQL,
        );
    }

    public function up() : void
    {
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating ADD COLUMN watched_year INTEGER DEFAULT NULL;
            SQL,
        );
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating ADD COLUMN watched_month INTEGER DEFAULT NULL;
            SQL,
        );
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating ADD COLUMN watched_day INTEGER DEFAULT NULL;
            SQL,
        );
        $this->execute(
            <<<SQL
            ALTER TABLE movie_user_rating ADD COLUMN location_id INTEGER DEFAULT NULL;
            SQL,
        );
    }
}
