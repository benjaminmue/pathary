<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    public function up(): void
    {
        // NOTE: This is a placeholder for SQLite migration
        // The SQLite schema should match the MySQL schema

        // For now, throw an error directing to manual setup
        throw new \RuntimeException(
            'SQLite initial schema not yet implemented. ' .
            'Please copy the table definitions from the MySQL migration ' .
            'and adapt them for SQLite syntax.'
        );
    }

    public function down(): void
    {
        // Drop all tables (implement if needed)
    }
}
