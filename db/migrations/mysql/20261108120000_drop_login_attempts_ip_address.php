<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * login_attempts stored the client IP in plaintext next to its hash. The rate
 * limiter only ever reads the hash, so the plaintext column was personal data
 * without a purpose.
 */
final class DropLoginAttemptsIpAddress extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('login_attempts');
        if ($table->hasColumn('ip_address') === true) {
            $table->removeColumn('ip_address')->save();
        }
    }

    public function down(): void
    {
        // Restores the column only; the removed addresses are gone for good.
        $this->table('login_attempts')
            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => true,
                'comment' => 'Client IP (IPv4 or IPv6) for auditing',
            ])
            ->save();
    }
}
