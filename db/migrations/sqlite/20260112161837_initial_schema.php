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
        // Rolling back the baseline would drop every table, including all user
        // data. There is no state to roll back to, so refuse instead of
        // silently wiping the database (e.g. after one rollback too many).
        throw new \Phinx\Migration\IrreversibleMigrationException(
            'The initial schema cannot be rolled back. Restore a database backup instead.',
        );
    }
}
