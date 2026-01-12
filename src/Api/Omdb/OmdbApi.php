<?php declare(strict_types=1);

namespace Movary\Api\Omdb;

use Movary\ValueObject\OmdbRatings;
use Psr\Log\LoggerInterface;

class OmdbApi
{
    public function __construct(
        private readonly OmdbClient $omdbClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch ratings for a movie by IMDb ID
     * Returns IMDb rating, Rotten Tomatoes, and Metacritic scores
     */
    public function fetchMovieRatings(string $imdbId) : ?OmdbRatings
    {
        try {
            $response = $this->omdbClient->get([
                'i' => $imdbId,
                'type' => 'movie',
            ]);

            if (empty($response)) {
                $this->logger->warning('OMDb: Empty response for IMDb ID', ['imdbId' => $imdbId]);
                return null;
            }

            // Extract ratings from the Ratings array
            $imdbRating = null;
            $imdbVotes = null;
            $rottenTomatoesRating = null;
            $metacriticRating = null;

            // Parse main IMDb rating
            if (isset($response['imdbRating']) && $response['imdbRating'] !== 'N/A') {
                $imdbRating = (float)$response['imdbRating'];
            }

            // Parse IMDb vote count
            if (isset($response['imdbVotes']) && $response['imdbVotes'] !== 'N/A') {
                // Remove commas and convert to int (e.g., "1,234,567" -> 1234567)
                $imdbVotes = (int)str_replace(',', '', $response['imdbVotes']);
            }

            // Parse ratings array
            if (isset($response['Ratings']) && is_array($response['Ratings'])) {
                foreach ($response['Ratings'] as $rating) {
                    if ($rating['Source'] === 'Rotten Tomatoes') {
                        // Convert "91%" to 91
                        $rottenTomatoesRating = (int)str_replace('%', '', $rating['Value']);
                    } elseif ($rating['Source'] === 'Metacritic') {
                        // Convert "69/100" to 69
                        $metacriticRating = (int)explode('/', $rating['Value'])[0];
                    }
                }
            }

            $this->logger->debug('OMDb: Fetched ratings', [
                'imdbId' => $imdbId,
                'imdbRating' => $imdbRating,
                'imdbVotes' => $imdbVotes,
                'rottenTomatoes' => $rottenTomatoesRating,
                'metacritic' => $metacriticRating,
            ]);

            return OmdbRatings::create(
                $imdbRating,
                $imdbVotes,
                $rottenTomatoesRating,
                $metacriticRating
            );
        } catch (\Exception $e) {
            $this->logger->error('OMDb: Error fetching ratings', [
                'imdbId' => $imdbId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
