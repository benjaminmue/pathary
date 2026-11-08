<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Session tokens were stored in plaintext next to their SHA-256 hash, so anyone with
 * read access to the database could take over every session. Lookups use the hash
 * only now; this migration backfills missing hashes (keeping those sessions valid)
 * and then removes the plaintext column.
 */
final class DropPlaintextAuthToken extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('user_auth_token');

        if ($table->hasColumn('token') === false) {
            return;
        }

        // Hashed in PHP rather than SQL because SQLite has no SHA2() function.
        $rowsWithoutHash = $this->fetchAll('SELECT `id`, `token` FROM `user_auth_token` WHERE `token_hash` IS NULL');
        foreach ($rowsWithoutHash as $row) {
            $this->getQueryBuilder('update')
                ->update('user_auth_token')
                ->set('token_hash', hash('sha256', (string)$row['token']))
                ->where(['id' => $row['id']])
                ->execute();
        }

        $table->removeColumn('token')->update();
    }

    public function down(): void
    {
        $table = $this->table('user_auth_token');

        if ($table->hasColumn('token') === true) {
            return;
        }

        // The plaintext values cannot be restored; existing sessions keep working via token_hash.
        $table
            ->addColumn('token', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'after' => 'user_id'])
            ->update();
    }
}
