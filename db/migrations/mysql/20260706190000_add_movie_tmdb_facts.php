<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMovieTmdbFacts extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('movie');

        if ($table->hasColumn('budget') === false) {
            $table->addColumn('budget', 'biginteger', [
                'null' => true,
                'default' => null,
                'comment' => 'TMDB movie budget in USD',
            ]);
        }

        if ($table->hasColumn('revenue') === false) {
            $table->addColumn('revenue', 'biginteger', [
                'null' => true,
                'default' => null,
                'comment' => 'TMDB movie revenue in USD',
            ]);
        }

        if ($table->hasColumn('status') === false) {
            $table->addColumn('status', 'string', [
                'limit' => 40,
                'null' => true,
                'default' => null,
                'comment' => 'TMDB release status (e.g. Released, Post Production)',
            ]);
        }

        if ($table->hasColumn('original_title') === false) {
            $table->addColumn('original_title', 'string', [
                'limit' => 512,
                'null' => true,
                'default' => null,
                'comment' => 'TMDB original title',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('movie');

        foreach (['budget', 'revenue', 'status', 'original_title'] as $column) {
            if ($table->hasColumn($column) === true) {
                $table->removeColumn($column);
            }
        }

        $table->update();
    }
}
