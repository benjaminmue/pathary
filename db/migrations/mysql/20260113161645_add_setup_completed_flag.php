<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSetupCompletedFlag extends AbstractMigration
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
        // Check if any users exist - if so, setup is already completed
        $userCount = (int)$this->getAdapter()->getConnection()
            ->query('SELECT COUNT(*) FROM `user`')
            ->fetchColumn();

        $setupCompleted = $userCount > 0 ? 'true' : 'false';

        // Insert setup_completed flag
        $this->table('server_setting')->insert([
            [
                'key' => 'setup_completed',
                'value' => $setupCompleted,
            ],
        ])->save();
    }
}
