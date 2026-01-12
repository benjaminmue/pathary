<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSystemWideLocations extends AbstractMigration
{
    /**
     * Migrate up: Allow NULL user_id in location table for system-wide locations
     * System-wide locations (user_id = NULL) can be managed by admins and selected by all users
     */
    public function up(): void
    {
        // MySQL supports ALTER TABLE MODIFY to change column constraints
        $this->execute(
            'ALTER TABLE `location` MODIFY COLUMN `user_id` INT DEFAULT NULL'
        );
    }

    /**
     * Migrate down: Restore original schema requiring user_id
     */
    public function down(): void
    {
        // Remove any system-wide locations (user_id IS NULL) before reverting
        $this->execute('DELETE FROM `location` WHERE user_id IS NULL');

        // Restore NOT NULL constraint
        $this->execute(
            'ALTER TABLE `location` MODIFY COLUMN `user_id` INT NOT NULL'
        );
    }
}
