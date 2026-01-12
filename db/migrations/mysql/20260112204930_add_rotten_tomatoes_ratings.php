<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRottenTomatoesRatings extends AbstractMigration
{
    public function up(): void
    {
        $this->table('movie')
            ->addColumn('rt_rating_average', 'integer', [
                'null' => true,
                'default' => null,
                'signed' => false,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'after' => 'imdb_rating_vote_count',
                'comment' => 'Rotten Tomatoes Tomatometer rating (0-100)',
            ])
            ->addColumn('rt_rating_vote_count', 'integer', [
                'null' => true,
                'default' => null,
                'signed' => false,
                'after' => 'rt_rating_average',
                'comment' => 'Rotten Tomatoes number of reviews',
            ])
            ->addColumn('updated_at_omdb', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'updated_at_imdb',
                'comment' => 'Last time OMDb data was synced',
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('movie')
            ->removeColumn('rt_rating_average')
            ->removeColumn('rt_rating_vote_count')
            ->removeColumn('updated_at_omdb')
            ->save();
    }
}
