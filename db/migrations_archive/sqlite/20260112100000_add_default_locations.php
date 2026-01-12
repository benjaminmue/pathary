<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDefaultLocations extends AbstractMigration
{
    /**
     * Add default system-wide locations: Cinema, Home, Other
     * These are provided for convenience but can be deleted by admins
     */
    public function up(): void
    {
        // Insert default locations only if none exist yet
        // This prevents duplicate entries if migration is run multiple times
        $this->execute(
            <<<SQL
            INSERT INTO `location` (`name`, `user_id`, `created_at`)
            SELECT 'Cinema', NULL, datetime('now')
            WHERE NOT EXISTS (SELECT 1 FROM `location` WHERE `user_id` IS NULL)
            UNION ALL
            SELECT 'Home', NULL, datetime('now')
            WHERE NOT EXISTS (SELECT 1 FROM `location` WHERE `user_id` IS NULL)
            UNION ALL
            SELECT 'Other', NULL, datetime('now')
            WHERE NOT EXISTS (SELECT 1 FROM `location` WHERE `user_id` IS NULL)
            SQL
        );
    }

    /**
     * Remove default locations
     * NOTE: This will only remove the exact default locations if they haven't been modified
     */
    public function down(): void
    {
        $this->execute(
            "DELETE FROM `location`
             WHERE `user_id` IS NULL
             AND `name` IN ('Cinema', 'Home', 'Other')"
        );
    }
}
