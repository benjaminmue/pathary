<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EmailSendLog extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('email_send_log');
        $table->addColumn('recipient_email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email_type', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Type: welcome, password_reset, etc.'])
            ->addColumn('sender_user_id', 'integer', ['null' => true, 'comment' => 'Admin who triggered the email (for welcome emails)'])
            ->addColumn('sent_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('success', 'boolean', ['null' => false, 'default' => true, 'comment' => 'Whether email was sent successfully'])
            ->addColumn('error_message', 'text', ['null' => true, 'comment' => 'Error message if send failed'])
            ->addIndex(['recipient_email', 'sent_at'], ['name' => 'idx_email_rate_limit'])
            ->addIndex(['email_type', 'sent_at'], ['name' => 'idx_email_type_time'])
            ->addIndex(['sender_user_id', 'sent_at'], ['name' => 'idx_sender_time'])
            ->create();
    }
}
