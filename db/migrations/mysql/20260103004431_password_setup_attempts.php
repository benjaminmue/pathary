<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PasswordSetupAttempts extends AbstractMigration
{
    /**
     * Create password_setup_attempts table for rate limiting password setup attempts.
     *
     * Tracks password setup attempts per invitation token to prevent brute-force attacks.
     * Rate limit: 5 failed attempts per 15 minutes per token.
     */
    public function change(): void
    {
        $table = $this->table('password_setup_attempts');
        $table->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false, 'comment' => 'SHA-256 hash of invitation token'])
            ->addColumn('attempted_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('success', 'boolean', ['null' => false, 'default' => false, 'comment' => 'Whether password was successfully set'])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true, 'comment' => 'IP address of the attempt (IPv4 or IPv6)'])
            ->addIndex(['token_hash', 'attempted_at'], ['name' => 'idx_token_time'])
            ->create();
    }
}
