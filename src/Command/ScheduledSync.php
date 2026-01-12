<?php declare(strict_types=1);

namespace Movary\Command;

use Movary\Service\Omdb\OmdbMovieRatingSync;
use Movary\Service\ServerSettings;
use Movary\Service\Tmdb\SyncMovies;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'sync:scheduled',
    description: 'Daily scheduled sync for TMDB and OMDb data (max 1000 movies, 7+ days old).',
    aliases: ['sync:scheduled'],
    hidden: false,
)]
class ScheduledSync extends Command
{
    private const int MAX_MOVIES_PER_DAY = 1000;

    private const int MIN_HOURS_SINCE_SYNC = 168; // 7 days

    public function __construct(
        private readonly SyncMovies $tmdbSyncMovies,
        private readonly OmdbMovieRatingSync $omdbMovieRatingSync,
        private readonly ServerSettings $serverSettings,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->generateOutput($output, '=== Starting scheduled sync ===');
        $this->generateOutput($output, sprintf('Max movies: %d, Min age: %d hours (7 days)', self::MAX_MOVIES_PER_DAY, self::MIN_HOURS_SINCE_SYNC));

        $hasErrors = false;

        // Sync TMDB metadata
        try {
            $this->generateOutput($output, '');
            $this->generateOutput($output, '--- Syncing TMDB metadata ---');
            $this->tmdbSyncMovies->syncMovies(self::MIN_HOURS_SINCE_SYNC, self::MAX_MOVIES_PER_DAY, null);
            $this->generateOutput($output, '✓ TMDB sync completed successfully');
        } catch (Throwable $t) {
            $this->generateOutput($output, '✗ ERROR: TMDB sync failed');
            $this->logger->error('Scheduled TMDB sync failed', ['exception' => $t]);
            $hasErrors = true;
        }

        // Sync OMDb ratings (IMDb + Rotten Tomatoes) - only if configured
        $omdbApiKey = $this->serverSettings->getOmdbApiKey();
        if ($omdbApiKey !== null && $omdbApiKey !== '') {
            try {
                $this->generateOutput($output, '');
                $this->generateOutput($output, '--- Syncing OMDb ratings (IMDb + RT) ---');
                $this->omdbMovieRatingSync->syncMultipleMovieRatings(self::MIN_HOURS_SINCE_SYNC, self::MAX_MOVIES_PER_DAY, null);
                $this->generateOutput($output, '✓ OMDb sync completed successfully');
            } catch (Throwable $t) {
                $this->generateOutput($output, '✗ ERROR: OMDb sync failed');
                $this->logger->error('Scheduled OMDb sync failed', ['exception' => $t]);
                $hasErrors = true;
            }
        } else {
            $this->generateOutput($output, '');
            $this->generateOutput($output, '--- Skipping OMDb sync (no API key configured) ---');
            $this->logger->info('Scheduled OMDb sync skipped - no API key configured');
        }

        $this->generateOutput($output, '');
        $this->generateOutput($output, '=== Scheduled sync finished ===');

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
