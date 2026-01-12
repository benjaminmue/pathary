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
        // SQLite doesn't support ALTER TABLE to modify constraints directly
        // We need to recreate the table with updated schema

        $this->execute(
            <<<SQL
            CREATE TABLE `location_tmp` (
                `id` INTEGER NOT NULL,
                `user_id` TEXT DEFAULT NULL,
                `name` TEXT NOT NULL,
                `is_cinema` INTEGER DEFAULT 0,
                `created_at` TEXT NOT NULL,
                `updated_at` TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE
            )
            SQL,
        );

        // Copy existing data
        $this->execute(
            'INSERT INTO `location_tmp` (id, user_id, name, is_cinema, created_at, updated_at)
            SELECT id, user_id, name, is_cinema, created_at, updated_at FROM location',
        );

        // Replace old table with new one
        $this->execute('DROP TABLE `location`');
        $this->execute('ALTER TABLE `location_tmp` RENAME TO `location`');
    }

    /**
     * Migrate down: Restore original schema requiring user_id
     */
    public function down(): void
    {
        // Remove any system-wide locations (user_id IS NULL) before reverting
        $this->execute('DELETE FROM location WHERE user_id IS NULL');

        $this->execute(
            <<<SQL
            CREATE TABLE `location_tmp` (
                `id` INTEGER NOT NULL,
                `user_id` TEXT NOT NULL,
                `name` TEXT NOT NULL,
                `is_cinema` INTEGER DEFAULT 0,
                `created_at` TEXT NOT NULL,
                `updated_at` TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (`user_id`) REFERENCES user (`id`) ON DELETE CASCADE
            )
            SQL,
        );

        $this->execute(
            'INSERT INTO `location_tmp` (id, user_id, name, is_cinema, created_at, updated_at)
            SELECT id, user_id, name, is_cinema, created_at, updated_at FROM location',
        );

        $this->execute('DROP TABLE `location`');
        $this->execute('ALTER TABLE `location_tmp` RENAME TO `location`');
    }
}
