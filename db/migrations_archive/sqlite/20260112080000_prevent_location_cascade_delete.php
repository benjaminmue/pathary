<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PreventLocationCascadeDelete extends AbstractMigration
{
    /**
     * Prevent deleting locations that are in use by changing foreign key
     * from ON DELETE CASCADE to ON DELETE RESTRICT.
     *
     * SQLite requires recreating the entire table to modify foreign key constraints.
     */
    public function up(): void
    {
        // Create new table with RESTRICT constraint
        $this->execute(
            <<<SQL
            CREATE TABLE `movie_user_watch_dates_tmp` (
                `movie_id` INTEGER NOT NULL,
                `user_id` INTEGER NOT NULL,
                `watched_at` TEXT,
                `plays` INTEGER DEFAULT 1,
                `comment` TEXT,
                `position` INTEGER NOT NULL DEFAULT 1,
                `location_id` INTEGER DEFAULT NULL,
                UNIQUE (`movie_id`, `user_id`, `watched_at`),
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE,
                FOREIGN KEY (`movie_id`) REFERENCES movie (`id`),
                FOREIGN KEY (`location_id`) REFERENCES location (`id`) ON DELETE RESTRICT
            )
            SQL
        );

        // Copy data
        $this->execute(
            'INSERT INTO `movie_user_watch_dates_tmp`
             SELECT * FROM movie_user_watch_dates'
        );

        // Swap tables
        $this->execute('DROP TABLE `movie_user_watch_dates`');
        $this->execute('ALTER TABLE `movie_user_watch_dates_tmp` RENAME TO `movie_user_watch_dates`');
    }

    /**
     * Rollback to original CASCADE behavior (not recommended)
     */
    public function down(): void
    {
        // Create table with CASCADE constraint
        $this->execute(
            <<<SQL
            CREATE TABLE `movie_user_watch_dates_tmp` (
                `movie_id` INTEGER NOT NULL,
                `user_id` INTEGER NOT NULL,
                `watched_at` TEXT,
                `plays` INTEGER DEFAULT 1,
                `comment` TEXT,
                `position` INTEGER NOT NULL DEFAULT 1,
                `location_id` INTEGER DEFAULT NULL,
                UNIQUE (`movie_id`, `user_id`, `watched_at`),
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE,
                FOREIGN KEY (`movie_id`) REFERENCES movie (`id`),
                FOREIGN KEY (`location_id`) REFERENCES location (`id`) ON DELETE CASCADE
            )
            SQL
        );

        // Copy data
        $this->execute(
            'INSERT INTO `movie_user_watch_dates_tmp`
             SELECT * FROM movie_user_watch_dates'
        );

        // Swap tables
        $this->execute('DROP TABLE `movie_user_watch_dates`');
        $this->execute('ALTER TABLE `movie_user_watch_dates_tmp` RENAME TO `movie_user_watch_dates`');
    }
}
