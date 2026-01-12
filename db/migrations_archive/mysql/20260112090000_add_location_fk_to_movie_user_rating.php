<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocationFkToMovieUserRating extends AbstractMigration
{
    /**
     * Add foreign key constraint for location_id in movie_user_rating table
     * to prevent deleting locations that are in use
     */
    public function up(): void
    {
        // First, ensure location_id column exists and has correct type
        $this->execute(
            'ALTER TABLE `movie_user_rating`
             MODIFY COLUMN `location_id` INT UNSIGNED DEFAULT NULL'
        );

        // Add foreign key constraint with ON DELETE RESTRICT
        $this->execute(
            'ALTER TABLE `movie_user_rating`
             ADD CONSTRAINT `fk_movie_user_rating_location_id`
             FOREIGN KEY (`location_id`) REFERENCES `location` (`id`)
             ON DELETE RESTRICT'
        );
    }

    /**
     * Rollback: Remove foreign key constraint
     */
    public function down(): void
    {
        $this->execute(
            'ALTER TABLE `movie_user_rating`
             DROP FOREIGN KEY `fk_movie_user_rating_location_id`'
        );

        // Restore original column type
        $this->execute(
            'ALTER TABLE `movie_user_rating`
             MODIFY COLUMN `location_id` TINYINT UNSIGNED DEFAULT NULL'
        );
    }
}
