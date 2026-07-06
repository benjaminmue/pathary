<?php declare(strict_types=1);

namespace Movary\Domain\Movie\History\Location;

use Doctrine\DBAL\Connection;
use Movary\ValueObject\DateTime;

class MovieHistoryLocationRepository
{
    public function __construct(private readonly Connection $dbConnection)
    {
    }

    public function createLocation(?int $userId, string $name, bool $isCinema) : void
    {
        $timestamp = DateTime::create();

        $this->dbConnection->insert(
            'location',
            [
                'user_id' => $userId,
                'name' => $name,
                'is_cinema' => (int)$isCinema,
                'created_at' => (string)$timestamp,
                'updated_at' => (string)$timestamp,
            ],
        );
    }

    public function deleteLocation(int $locationId) : void
    {
        $this->dbConnection->delete('location', ['id' => $locationId]);
    }

    public function findLocationById(int $locationId) : ?MovieHistoryLocationEntity
    {
        $data = $this->dbConnection->fetchAssociative('SELECT * FROM `location` WHERE `id` = ?', [$locationId]);

        if (empty($data) === true) {
            return null;
        }

        return MovieHistoryLocationEntity::createFromArray($data);
    }

    public function findLocationByName(int $userId, string $locationName) : ?MovieHistoryLocationEntity
    {
        $data = $this->dbConnection->fetchAssociative(
            'SELECT *
            FROM `location` 
            WHERE user_id = ? AND name = ?',
            [$userId, $locationName],
        );

        return $data === false ? null : MovieHistoryLocationEntity::createFromArray($data);
    }

    public function findLocationsByUserId(?int $userId) : MovieHistoryLocationEntityList
    {
        // For system-wide locations (userId = NULL), use IS NULL query
        if ($userId === null) {
            $data = $this->dbConnection->fetchAllAssociative(
                'SELECT *
                FROM `location`
                WHERE user_id IS NULL
                ORDER BY name',
            );
        } else {
            $data = $this->dbConnection->fetchAllAssociative(
                'SELECT *
                FROM `location`
                WHERE user_id = ?
                ORDER BY name',
                [$userId],
            );
        }

        return MovieHistoryLocationEntityList::createFromArray($data);
    }

    /**
     * @return array<int, int>
     */
    public function fetchUsageCountsByLocationId() : array
    {
        $rows = $this->dbConnection->fetchAllAssociative(
            'SELECT location_id, COUNT(*) AS cnt
            FROM movie_user_watch_dates
            WHERE location_id IS NOT NULL
            GROUP BY location_id',
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['location_id']] = (int)$row['cnt'];
        }

        return $counts;
    }

    public function updateLocation(int $locationId, string $name, bool $isCinema) : void
    {
        $this->dbConnection->update(
            'location',
            [
                'name' => $name,
                'is_cinema' => (int)$isCinema,
                'updated_at' => (string)DateTime::create(),
            ],
            [
                'id' => $locationId,
            ],
        );
    }
}
