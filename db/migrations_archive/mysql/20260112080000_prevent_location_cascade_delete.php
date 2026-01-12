<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PreventLocationCascadeDelete extends AbstractMigration
{
    /**
     * Prevent deleting locations that are in use by changing foreign key
     * from ON DELETE CASCADE to ON DELETE RESTRICT.
     *
     * This protects user data - attempting to delete a location that has
     * been used in movie watch dates will fail with a constraint error.
     */
    public function up(): void
    {
        // Drop existing foreign key constraint
        $this->execute(
            'ALTER TABLE `movie_user_watch_dates`
             DROP FOREIGN KEY `fk_movie_user_watch_dates_location_id`'
        );

        // Re-add with RESTRICT instead of CASCADE
        $this->execute(
            'ALTER TABLE `movie_user_watch_dates`
             ADD CONSTRAINT `fk_movie_user_watch_dates_location_id`
             FOREIGN KEY (`location_id`) REFERENCES `location` (`id`)
             ON DELETE RESTRICT'
        );
    }

    /**
     * Rollback to original CASCADE behavior (not recommended)
     */
    public function down(): void
    {
        // Drop RESTRICT constraint
        $this->execute(
            'ALTER TABLE `movie_user_watch_dates`
             DROP FOREIGN KEY `fk_movie_user_watch_dates_location_id`'
        );

        // Re-add with original CASCADE behavior
        $this->execute(
            'ALTER TABLE `movie_user_watch_dates`
             ADD CONSTRAINT `fk_movie_user_watch_dates_location_id`
             FOREIGN KEY (`location_id`) REFERENCES `location` (`id`)
             ON DELETE CASCADE'
        );
    }
}
