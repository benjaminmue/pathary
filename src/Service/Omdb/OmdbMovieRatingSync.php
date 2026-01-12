<?php declare(strict_types=1);

namespace Movary\Service\Omdb;

use Exception;
use Movary\Api\Omdb\OmdbApi;
use Movary\Domain\Movie\MovieApi;
use Movary\Domain\Movie\MovieEntity;
use Movary\Domain\Movie\MovieRepository;
use Movary\ValueObject\OmdbRatings;
use Psr\Log\LoggerInterface;

class OmdbMovieRatingSync
{
    private const int DEFAULT_MIN_DELAY_BETWEEN_REQUESTS_IN_MS = 250000; // 250ms - OMDb allows 1000 requests/day

    private const int SLEEP_AFTER_FIRST_FAILED_REQUEST_IN_MS = 1000000; // 1 second

    public function __construct(
        private readonly OmdbApi $omdbApi,
        private readonly MovieApi $movieApi,
        private readonly MovieRepository $movieRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function syncMovieRating(int $movieId) : void
    {
        $movie = $this->movieApi->findById($movieId);
        $imdbId = $movie?->getImdbId();

        if ($movie === null || $imdbId === null) {
            return;
        }

        $this->logger->debug('OMDb: Start movie rating update', [$this->generateMovieLogData($movie)]);

        $omdbRatings = $this->fetchRatings($imdbId);
        if ($omdbRatings === null) {
            $this->logger->warning('OMDb: Could not fetch ratings', [$this->generateMovieLogData($movie)]);
            return;
        }

        // Check if ratings have changed
        $hasChanged = $this->hasRatingsChanged($movie, $omdbRatings);
        if ($hasChanged === false) {
            $this->logger->debug('OMDb: Skipped updating unchanged movie ratings', [$this->generateMovieLogData($movie)]);
            return;
        }

        $this->movieRepository->updateOmdbRatings($movieId, $omdbRatings);

        $this->logger->info('OMDb: Updated movie ratings', [
            array_merge(
                $this->generateMovieLogData($movie),
                [
                    'oldImdbRating' => $movie->getImdbRatingAverage(),
                    'oldImdbVotes' => $movie->getImdbVoteCount(),
                    'oldRtRating' => $movie->getRtRatingAverage(),
                    'newImdbRating' => $omdbRatings->getImdbRating(),
                    'newImdbVotes' => $omdbRatings->getImdbVotes(),
                    'newRtRating' => $omdbRatings->getRottenTomatoesRating(),
                    'metacritic' => $omdbRatings->getMetacriticRating(),
                ],
            )
        ]);
    }

    public function syncMultipleMovieRatings(
        ?int $maxAgeInHours = null,
        ?int $movieCountSyncThreshold = null,
        ?array $movieIds = null,
        ?bool $onlyNeverSynced = false,
    ) : void {
        $movieIds = $this->movieRepository->fetchMovieIdsForOmdbSync($maxAgeInHours, $movieCountSyncThreshold, $movieIds, (bool)$onlyNeverSynced);

        $totalMovies = count($movieIds);
        $this->logger->info('OMDb: Starting sync', ['totalMovies' => $totalMovies]);

        $syncedCount = 0;
        foreach ($movieIds as $index => $movieId) {
            $this->syncMovieRating($movieId);
            $syncedCount++;

            if ($index === array_key_last($movieIds) || ((int)$movieCountSyncThreshold !== 0 && (int)$index + 1 >= $movieCountSyncThreshold)) {
                break;
            }

            // Rate limiting: 250ms between requests to stay within 1000 requests/day free tier
            usleep(self::DEFAULT_MIN_DELAY_BETWEEN_REQUESTS_IN_MS);
        }

        $this->logger->info('OMDb: Sync completed', ['syncedMovies' => $syncedCount]);
    }

    private function fetchRatings(string $imdbId) : ?OmdbRatings
    {
        try {
            return $this->omdbApi->fetchMovieRatings($imdbId);
        } catch (Exception $e) {
            $this->logger->warning('OMDb: First attempt failed, retrying', ['imdbId' => $imdbId, 'error' => $e->getMessage()]);

            // Retry request with a little delay to circumvent one-time network issues
            usleep(self::SLEEP_AFTER_FIRST_FAILED_REQUEST_IN_MS);

            try {
                return $this->omdbApi->fetchMovieRatings($imdbId);
            } catch (Exception $e) {
                $this->logger->error('OMDb: Second attempt failed', ['imdbId' => $imdbId, 'error' => $e->getMessage()]);
                return null;
            }
        }
    }

    private function hasRatingsChanged(MovieEntity $movie, OmdbRatings $omdbRatings) : bool
    {
        return $omdbRatings->getImdbRating() !== $movie->getImdbRatingAverage()
            || $omdbRatings->getImdbVotes() !== $movie->getImdbVoteCount()
            || $omdbRatings->getRottenTomatoesRating() !== $movie->getRtRatingAverage();
    }

    private function generateMovieLogData(MovieEntity $movie) : array
    {
        return [
            'movieId' => $movie->getId(),
            'imdbId' => $movie->getImdbId(),
            'movieTitle' => $movie->getTitle(),
        ];
    }
}
