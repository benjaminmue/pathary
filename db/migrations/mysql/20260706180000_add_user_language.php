<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUserLanguage extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('user');

        if ($table->hasColumn('language') === true) {
            return;
        }

        $table
            ->addColumn('language', 'string', [
                'limit' => 5,
                'null' => true,
                'default' => null,
                'comment' => 'Preferred UI language (e.g. de, en); null uses the server default',
            ])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('user');

        if ($table->hasColumn('language') === true) {
            $table->removeColumn('language')->update();
        }
    }
}
