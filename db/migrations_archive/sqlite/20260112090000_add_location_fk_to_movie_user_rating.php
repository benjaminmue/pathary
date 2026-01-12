<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocationFkToMovieUserRating extends AbstractMigration
{
    /**
     * Add foreign key constraint for location_id in movie_user_rating table
     * SQLite requires recreating the entire table to modify foreign key constraints
     */
    public function up(): void
    {
        // Create new table with foreign key constraint
        $this->execute(
            <<<SQL
            CREATE TABLE `movie_user_rating_tmp` (
                `movie_id` INTEGER NOT NULL,
                `user_id` INTEGER NOT NULL,
                `rating` INTEGER DEFAULT NULL,
                `updated_at` TEXT DEFAULT NULL,
                `created_at` TEXT NOT NULL,
                `rating_popcorn` INTEGER DEFAULT NULL,
                `comment` TEXT DEFAULT NULL,
                `watched_year` INTEGER DEFAULT NULL,
                `watched_month` INTEGER DEFAULT NULL,
                `watched_day` INTEGER DEFAULT NULL,
                `location_id` INTEGER DEFAULT NULL,
                PRIMARY KEY (`movie_id`, `user_id`),
                FOREIGN KEY (`movie_id`) REFERENCES movie (`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE,
                FOREIGN KEY (`location_id`) REFERENCES location (`id`) ON DELETE RESTRICT
            )
            SQL
        );

        // Copy data
        $this->execute(
            'INSERT INTO `movie_user_rating_tmp`
             SELECT * FROM movie_user_rating'
        );

        // Swap tables
        $this->execute('DROP TABLE `movie_user_rating`');
        $this->execute('ALTER TABLE `movie_user_rating_tmp` RENAME TO `movie_user_rating`');
    }

    /**
     * Rollback: Restore original table without location foreign key
     */
    public function down(): void
    {
        // Create table without location foreign key
        $this->execute(
            <<<SQL
            CREATE TABLE `movie_user_rating_tmp` (
                `movie_id` INTEGER NOT NULL,
                `user_id` INTEGER NOT NULL,
                `rating` INTEGER DEFAULT NULL,
                `updated_at` TEXT DEFAULT NULL,
                `created_at` TEXT NOT NULL,
                `rating_popcorn` INTEGER DEFAULT NULL,
                `comment` TEXT DEFAULT NULL,
                `watched_year` INTEGER DEFAULT NULL,
                `watched_month` INTEGER DEFAULT NULL,
                `watched_day` INTEGER DEFAULT NULL,
                `location_id` INTEGER DEFAULT NULL,
                PRIMARY KEY (`movie_id`, `user_id`),
                FOREIGN KEY (`movie_id`) REFERENCES movie (`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE
            )
            SQL
        );

        // Copy data
        $this->execute(
            'INSERT INTO `movie_user_rating_tmp`
             SELECT * FROM movie_user_rating'
        );

        // Swap tables
        $this->execute('DROP TABLE `movie_user_rating`');
        $this->execute('ALTER TABLE `movie_user_rating_tmp` RENAME TO `movie_user_rating`');
    }
}
