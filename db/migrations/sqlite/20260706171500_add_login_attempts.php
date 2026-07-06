<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLoginAttempts extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('login_attempts') === true) {
            return;
        }

        $this->table('login_attempts')
            ->addColumn('ip_hash', 'string', [
                'limit' => 64,
                'null' => false,
                'comment' => 'SHA-256 hash of the client IP address',
            ])
            ->addColumn('attempted_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addColumn('success', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'Whether the authentication attempt succeeded',
            ])
            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => true,
                'comment' => 'Client IP (IPv4 or IPv6) for auditing',
            ])
            ->addIndex(['ip_hash', 'attempted_at'], ['name' => 'idx_ip_time'])
            ->create();
    }

    public function down(): void
    {
        if ($this->hasTable('login_attempts') === true) {
            $this->table('login_attempts')->drop()->save();
        }
    }
}
