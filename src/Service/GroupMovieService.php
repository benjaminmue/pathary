<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class GroupMovieService
{
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly ImageUrlService $imageUrlService,
    ) {
    }

    /**
     * Get latest movies added by any user, ordered by most recently added.
     * A movie is "added" when any user creates the first watch/history entry for that movie.
     * last_added_at = MAX(watched_at or created timestamp) across all users.
     *
     * @return array<int, array{
     *     movie_id: int,
     *     title: string,
     *     release_date: ?string,
     *     tmdb_poster_path: ?string,
     *     poster_path: ?string,
     *     last_added_at: string
     * }>
     */
    public function getLatestAddedMovies(int $limit = 20) : array
    {
        $movies = $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                m.id AS movie_id,
                m.title,
                m.release_date,
                m.tmdb_poster_path,
                m.poster_path,
                MAX(muwd.watched_at) AS last_added_at
            FROM movie_user_watch_dates muwd
            JOIN movie m ON m.id = muwd.movie_id
            WHERE muwd.watched_at IS NOT NULL
            GROUP BY m.id
            ORDER BY last_added_at DESC
            LIMIT ?
            SQL,
            [$limit],
            [ParameterType::INTEGER],
        );

        return $this->imageUrlService->replacePosterPathWithImageSrcUrl($movies);
    }

    /**
     * Get aggregate statistics for a movie across all users.
     *
     * @return array{
     *     avg_popcorn: ?float,
     *     rating_count: int,
     *     last_activity_at: ?string
     * }
     */
    public function getMovieGroupStats(int $movieId) : array
    {
        $ratingStats = $this->dbConnection->fetchAssociative(
            <<<SQL
            SELECT
                AVG(rating_popcorn) AS avg_popcorn,
                COUNT(rating_popcorn) AS rating_count,
                MAX(updated_at) AS last_rating_activity
            FROM movie_user_rating
            WHERE movie_id = ? AND rating_popcorn IS NOT NULL
            SQL,
            [$movieId],
        );

        $lastWatchActivity = $this->dbConnection->fetchOne(
            <<<SQL
            SELECT MAX(watched_at) AS last_watch_activity
            FROM movie_user_watch_dates
            WHERE movie_id = ?
            SQL,
            [$movieId],
        );

        $lastRatingActivity = $ratingStats['last_rating_activity'] ?? null;
        $lastActivityAt = $this->getLatestTimestamp($lastRatingActivity, $lastWatchActivity ?: null);

        return [
            'avg_popcorn' => $ratingStats['avg_popcorn'] !== null ? round((float)$ratingStats['avg_popcorn'], 1) : null,
            'rating_count' => (int)($ratingStats['rating_count'] ?? 0),
            'last_activity_at' => $lastActivityAt,
        ];
    }

    /**
     * Get individual ratings from all users for a movie.
     * Returns shuffled results for random order on each request.
     *
     * Expected fields: user_name, rating_popcorn, comment, watched_year,
     * watched_month, watched_day, location_id, location_name, updated_at
     *
     * @return list<array<string, mixed>>
     */
    public function getMovieIndividualRatings(int $movieId) : array
    {
        $ratings = $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                u.name AS user_name,
                mur.rating_popcorn,
                mur.comment,
                mur.watched_year,
                mur.watched_month,
                mur.watched_day,
                mur.location_id,
                l.name AS location_name,
                COALESCE(mur.updated_at, mur.created_at) AS updated_at
            FROM movie_user_rating mur
            JOIN user u ON u.id = mur.user_id
            LEFT JOIN location l ON l.id = mur.location_id
            WHERE mur.movie_id = ? AND (mur.rating_popcorn IS NOT NULL OR mur.comment IS NOT NULL OR mur.watched_year IS NOT NULL OR mur.location_id IS NOT NULL)
            SQL,
            [$movieId],
        );

        shuffle($ratings);

        return $ratings;
    }

    /**
     * Get all movies in the library with sorting and filtering.
     * Library = all movies with at least one watch/history entry.
     *
     * @param int $userId Current user ID for own_rating sort
     * @param string $sortBy Sort field: 'added', 'title', 'release_date', 'global_rating', 'own_rating'
     * @param string $sortOrder Sort direction: 'asc' or 'desc'
     * @param ?int $ratingMin Minimum global avg rating (1-7)
     * @param ?int $ratingMax Maximum global avg rating (1-7)
     * @param ?string $genre Genre name filter
     * @param ?int $yearMin Minimum release year
     * @param ?int $yearMax Maximum release year
     * @param ?float $tmdbMin Minimum TMDB rating (0-10)
     * @param ?float $tmdbMax Maximum TMDB rating (0-10)
     * @param ?float $imdbMin Minimum IMDB rating (0-10)
     * @param ?float $imdbMax Maximum IMDB rating (0-10)
     * @param ?int $rtMin Minimum Rotten Tomatoes rating (0-100)
     * @param ?int $rtMax Maximum Rotten Tomatoes rating (0-100)
     * @return array
     */
    public function getAllMovies(
        int $userId,
        string $sortBy = 'added',
        string $sortOrder = 'desc',
        ?int $ratingMin = null,
        ?int $ratingMax = null,
        ?string $genre = null,
        ?int $yearMin = null,
        ?int $yearMax = null,
        ?float $tmdbMin = null,
        ?float $tmdbMax = null,
        ?float $imdbMin = null,
        ?float $imdbMax = null,
        ?int $rtMin = null,
        ?int $rtMax = null,
    ) : array {
        $params = [];
        $whereConditions = [];

        // Genre filter join
        $genreJoin = '';
        if ($genre !== null && $genre !== '') {
            $genreJoin = 'JOIN movie_genre mg ON mg.movie_id = m.id JOIN genre g ON g.id = mg.genre_id';
            $whereConditions[] = 'g.name = ?';
            $params[] = $genre;
        }

        // Release year filter
        if ($yearMin !== null) {
            if ($this->dbConnection->getDatabasePlatform() instanceof SqlitePlatform) {
                $whereConditions[] = "CAST(strftime('%Y', m.release_date) AS INTEGER) >= ?";
            } else {
                $whereConditions[] = 'YEAR(m.release_date) >= ?';
            }
            $params[] = $yearMin;
        }
        if ($yearMax !== null) {
            if ($this->dbConnection->getDatabasePlatform() instanceof SqlitePlatform) {
                $whereConditions[] = "CAST(strftime('%Y', m.release_date) AS INTEGER) <= ?";
            } else {
                $whereConditions[] = 'YEAR(m.release_date) <= ?';
            }
            $params[] = $yearMax;
        }

        // TMDB rating filter
        if ($tmdbMin !== null) {
            $whereConditions[] = '(m.tmdb_vote_average IS NULL OR m.tmdb_vote_average >= ?)';
            $params[] = $tmdbMin;
        }
        if ($tmdbMax !== null) {
            $whereConditions[] = '(m.tmdb_vote_average IS NULL OR m.tmdb_vote_average <= ?)';
            $params[] = $tmdbMax;
        }

        // IMDB rating filter
        if ($imdbMin !== null) {
            $whereConditions[] = '(m.imdb_rating_average IS NULL OR m.imdb_rating_average >= ?)';
            $params[] = $imdbMin;
        }
        if ($imdbMax !== null) {
            $whereConditions[] = '(m.imdb_rating_average IS NULL OR m.imdb_rating_average <= ?)';
            $params[] = $imdbMax;
        }

        // Rotten Tomatoes rating filter
        if ($rtMin !== null) {
            $whereConditions[] = '(m.rt_rating_average IS NULL OR m.rt_rating_average >= ?)';
            $params[] = $rtMin;
        }
        if ($rtMax !== null) {
            $whereConditions[] = '(m.rt_rating_average IS NULL OR m.rt_rating_average <= ?)';
            $params[] = $rtMax;
        }

        // Rating filter (on avg_popcorn, applied via HAVING)
        $havingConditions = [];
        if ($ratingMin !== null) {
            $havingConditions[] = '(avg_popcorn IS NULL OR avg_popcorn >= ?)';
            $params[] = $ratingMin;
        }
        if ($ratingMax !== null) {
            $havingConditions[] = '(avg_popcorn IS NULL OR avg_popcorn <= ?)';
            $params[] = $ratingMax;
        }

        $whereClause = count($whereConditions) > 0 ? 'AND ' . implode(' AND ', $whereConditions) : '';
        $havingClause = count($havingConditions) > 0 ? 'HAVING ' . implode(' AND ', $havingConditions) : '';

        // Sort order validation
        $sortOrderSql = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Sort field mapping with NULLS LAST emulation
        // Neither MySQL nor SQLite supports NULLS LAST natively, so we use "column IS NULL" trick
        // This puts NULL values at the end regardless of sort direction
        $orderByClause = match ($sortBy) {
            'title' => "LOWER(m.title) $sortOrderSql",
            'release_date' => "m.release_date IS NULL, m.release_date $sortOrderSql, LOWER(m.title) ASC",
            'global_rating' => "avg_popcorn IS NULL, avg_popcorn $sortOrderSql, LOWER(m.title) ASC",
            'own_rating' => "own_rating IS NULL, own_rating $sortOrderSql, LOWER(m.title) ASC",
            default => "last_added_at $sortOrderSql, LOWER(m.title) ASC", // 'added'
        };

        // Add userId param for own_rating subquery - use prepared statement parameter
        array_unshift($params, $userId);

        $movies = $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                m.id AS movie_id,
                m.title,
                m.release_date,
                m.tmdb_poster_path,
                m.poster_path,
                MAX(muwd.watched_at) AS last_added_at,
                (
                    SELECT AVG(mur.rating_popcorn)
                    FROM movie_user_rating mur
                    WHERE mur.movie_id = m.id AND mur.rating_popcorn IS NOT NULL
                ) AS avg_popcorn,
                (
                    SELECT mur2.rating_popcorn
                    FROM movie_user_rating mur2
                    WHERE mur2.movie_id = m.id AND mur2.user_id = ?
                ) AS own_rating
            FROM movie_user_watch_dates muwd
            JOIN movie m ON m.id = muwd.movie_id
            $genreJoin
            WHERE muwd.watched_at IS NOT NULL $whereClause
            GROUP BY m.id
            $havingClause
            ORDER BY $orderByClause
            SQL,
            $params,
        );

        return $this->imageUrlService->replacePosterPathWithImageSrcUrl($movies);
    }

    /**
     * Get all unique genres from the library.
     *
     * @return array<string>
     */
    public function getAllGenres() : array
    {
        return $this->dbConnection->fetchFirstColumn(
            <<<SQL
            SELECT DISTINCT g.name
            FROM genre g
            JOIN movie_genre mg ON mg.genre_id = g.id
            JOIN movie_user_watch_dates muwd ON muwd.movie_id = mg.movie_id
            WHERE muwd.watched_at IS NOT NULL
            ORDER BY g.name ASC
            SQL,
        );
    }

    /**
     * Get min and max release years from the library.
     *
     * @return array{min: ?int, max: ?int}
     */
    public function getReleaseYearRange() : array
    {
        if ($this->dbConnection->getDatabasePlatform() instanceof SqlitePlatform) {
            $result = $this->dbConnection->fetchAssociative(
                <<<SQL
                SELECT
                    MIN(CAST(strftime('%Y', m.release_date) AS INTEGER)) AS min_year,
                    MAX(CAST(strftime('%Y', m.release_date) AS INTEGER)) AS max_year
                FROM movie m
                JOIN movie_user_watch_dates muwd ON muwd.movie_id = m.id
                WHERE muwd.watched_at IS NOT NULL AND m.release_date IS NOT NULL
                SQL,
            );
        } else {
            $result = $this->dbConnection->fetchAssociative(
                <<<SQL
                SELECT
                    MIN(YEAR(m.release_date)) AS min_year,
                    MAX(YEAR(m.release_date)) AS max_year
                FROM movie m
                JOIN movie_user_watch_dates muwd ON muwd.movie_id = m.id
                WHERE muwd.watched_at IS NOT NULL AND m.release_date IS NOT NULL
                SQL,
            );
        }

        return [
            'min' => $result['min_year'] !== null ? (int)$result['min_year'] : null,
            'max' => $result['max_year'] !== null ? (int)$result['max_year'] : null,
        ];
    }

    /**
     * Get total count of unique movies in the library.
     */
    public function getTotalMoviesCount() : int
    {
        return (int)$this->dbConnection->fetchOne(
            <<<SQL
            SELECT COUNT(DISTINCT m.id)
            FROM movie m
            JOIN movie_user_watch_dates muwd ON muwd.movie_id = m.id
            WHERE muwd.watched_at IS NOT NULL
            SQL,
        );
    }

    /**
     * Get total count of all ratings across all movies.
     */
    public function getTotalRatingsCount() : int
    {
        return (int)$this->dbConnection->fetchOne(
            <<<SQL
            SELECT COUNT(*)
            FROM movie_user_rating
            WHERE rating_popcorn IS NOT NULL
            SQL,
        );
    }

    /**
     * Get highest rated movies (by average popcorn rating).
     *
     * @return array<int, array{
     *     movie_id: int,
     *     title: string,
     *     release_date: ?string,
     *     tmdb_poster_path: ?string,
     *     poster_path: ?string,
     *     avg_popcorn: float,
     *     rating_count: int
     * }>
     */
    public function getHighestRatedMovies(int $limit = 3) : array
    {
        $movies = $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                m.id AS movie_id,
                m.title,
                m.release_date,
                m.tmdb_poster_path,
                m.poster_path,
                AVG(mur.rating_popcorn) AS avg_popcorn,
                COUNT(mur.rating_popcorn) AS rating_count
            FROM movie m
            JOIN movie_user_rating mur ON mur.movie_id = m.id
            WHERE mur.rating_popcorn IS NOT NULL
            GROUP BY m.id
            HAVING rating_count >= 1
            ORDER BY avg_popcorn DESC, rating_count DESC, LOWER(m.title) ASC
            LIMIT ?
            SQL,
            [$limit],
            [ParameterType::INTEGER],
        );

        return $this->imageUrlService->replacePosterPathWithImageSrcUrl($movies);
    }

    /**
     * Get lowest rated movies (by average popcorn rating).
     *
     * @return array<int, array{
     *     movie_id: int,
     *     title: string,
     *     release_date: ?string,
     *     tmdb_poster_path: ?string,
     *     poster_path: ?string,
     *     avg_popcorn: float,
     *     rating_count: int
     * }>
     */
    public function getLowestRatedMovies(int $limit = 3) : array
    {
        $movies = $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                m.id AS movie_id,
                m.title,
                m.release_date,
                m.tmdb_poster_path,
                m.poster_path,
                AVG(mur.rating_popcorn) AS avg_popcorn,
                COUNT(mur.rating_popcorn) AS rating_count
            FROM movie m
            JOIN movie_user_rating mur ON mur.movie_id = m.id
            WHERE mur.rating_popcorn IS NOT NULL
            GROUP BY m.id
            HAVING rating_count >= 1
            ORDER BY avg_popcorn ASC, rating_count DESC, LOWER(m.title) ASC
            LIMIT ?
            SQL,
            [$limit],
            [ParameterType::INTEGER],
        );

        return $this->imageUrlService->replacePosterPathWithImageSrcUrl($movies);
    }

    /**
     * Get random comments from users.
     *
     * @return array<int, array{
     *     user_name: string,
     *     movie_title: string,
     *     movie_id: int,
     *     comment: string,
     *     rating_popcorn: int
     * }>
     */
    public function getRandomComments(int $limit = 5) : array
    {
        $randomFunction = $this->dbConnection->getDatabasePlatform() instanceof SqlitePlatform
            ? 'RANDOM()'
            : 'RAND()';

        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                u.name AS user_name,
                m.title AS movie_title,
                m.id AS movie_id,
                mur.comment,
                mur.rating_popcorn
            FROM movie_user_rating mur
            JOIN user u ON u.id = mur.user_id
            JOIN movie m ON m.id = mur.movie_id
            WHERE mur.comment IS NOT NULL AND mur.comment != ''
            ORDER BY $randomFunction
            LIMIT ?
            SQL,
            [$limit],
            [ParameterType::INTEGER],
        );
    }

    /**
     * Get global statistics for the entire library.
     *
     * @return array{
     *     total_movies: int,
     *     total_ratings: int,
     *     avg_rating: ?float,
     *     highest_rated: array,
     *     lowest_rated: array,
     *     random_comments: array
     * }
     */
    public function getGlobalStats() : array
    {
        $totalMovies = $this->getTotalMoviesCount();
        $totalRatings = $this->getTotalRatingsCount();

        $avgRating = null;
        if ($totalRatings > 0) {
            $result = $this->dbConnection->fetchOne(
                <<<SQL
                SELECT AVG(rating_popcorn)
                FROM movie_user_rating
                WHERE rating_popcorn IS NOT NULL
                SQL,
            );
            if ($result !== false && $result !== null) {
                $avgRating = round((float)$result, 1);
            }
        }

        return [
            'total_movies' => $totalMovies,
            'total_ratings' => $totalRatings,
            'avg_rating' => $avgRating,
            'highest_rated' => $this->getHighestRatedMovies(3),
            'lowest_rated' => $this->getLowestRatedMovies(3),
            'random_comments' => $this->getRandomComments(6),
        ];
    }

    private function getLatestTimestamp(?string $timestamp1, ?string $timestamp2) : ?string
    {
        if ($timestamp1 === null && $timestamp2 === null) {
            return null;
        }

        if ($timestamp1 === null) {
            return $timestamp2;
        }

        if ($timestamp2 === null) {
            return $timestamp1;
        }

        return $timestamp1 > $timestamp2 ? $timestamp1 : $timestamp2;
    }
}
