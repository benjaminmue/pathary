<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Per-account login throttling: an attacker rotating IP addresses must still be
 * slowed down when targeting a single account.
 */
final class AddLoginAttemptAccountHash extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('login_attempts');

        if ($table->hasColumn('account_hash') === true) {
            return;
        }

        $table
            ->addColumn('account_hash', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'comment' => 'SHA-256 hash of the normalised login email',
            ])
            ->addIndex(['account_hash', 'attempted_at'], ['name' => 'idx_account_time'])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('login_attempts');

        if ($table->hasColumn('account_hash') === true) {
            $table->removeIndexByName('idx_account_time')->removeColumn('account_hash')->update();
        }
    }
}
