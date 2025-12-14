# Database

This page documents Pathary's database schema, connection modes, and data access patterns.

## Database Modes

Pathary supports two database backends:

| Mode | Use Case | Configuration |
|------|----------|---------------|
| **SQLite** | Development, single-user | `DATABASE_MODE=sqlite` |
| **MySQL** | Production, multi-user | `DATABASE_MODE=mysql` |

### SQLite Configuration

```env
DATABASE_MODE=sqlite
DATABASE_SQLITE=storage/movary.sqlite
```

### MySQL Configuration

```env
DATABASE_MODE=mysql
DATABASE_MYSQL_HOST=localhost
DATABASE_MYSQL_PORT=3306
DATABASE_MYSQL_NAME=pathary
DATABASE_MYSQL_USER=pathary
DATABASE_MYSQL_PASSWORD=secret
DATABASE_MYSQL_CHARSET=utf8mb4
```

## Connection Setup

**File**: `src/Factory.php`

```php
public static function createDbConnection(ContainerInterface $container) : Connection
{
    $config = $container->get(Config::class);
    $databaseMode = $config->getAsString('DATABASE_MODE', 'sqlite');

    if ($databaseMode === 'mysql') {
        return DBAL\DriverManager::getConnection([
            'dbname' => $config->getAsString('DATABASE_MYSQL_NAME'),
            'user' => $config->getAsString('DATABASE_MYSQL_USER'),
            'password' => $config->getAsString('DATABASE_MYSQL_PASSWORD'),
            'host' => $config->getAsString('DATABASE_MYSQL_HOST'),
            'port' => $config->getAsInt('DATABASE_MYSQL_PORT', 3306),
            'driver' => 'pdo_mysql',
            'charset' => $config->getAsString('DATABASE_MYSQL_CHARSET', 'utf8mb4'),
        ]);
    }

    // SQLite
    return DBAL\DriverManager::getConnection([
        'path' => $config->getAsString('DATABASE_SQLITE', 'storage/movary.sqlite'),
        'driver' => 'pdo_sqlite',
    ]);
}
```

## Table Overview

### Core Tables

| Table | Description |
|-------|-------------|
| `movie` | Movie metadata from TMDB |
| `user` | User accounts |
| `movie_user_rating` | Per-user movie ratings |
| `movie_user_watch_dates` | Watch history entries |
| `movie_history` | Legacy history (deprecated) |

### Reference Tables

| Table | Description |
|-------|-------------|
| `genre` | Movie genres |
| `movie_genre` | Movie-genre associations |
| `person` | Actors, directors |
| `movie_cast` | Movie-actor associations |
| `movie_crew` | Movie-crew associations |
| `company` | Production companies |
| `movie_production_company` | Movie-company associations |

### User Tables

| Table | Description |
|-------|-------------|
| `user_auth_token` | Authentication tokens |
| `user_api_token` | API access tokens |
| `user_person_settings` | Hidden actors/directors |

### Cache Tables

| Table | Description |
|-------|-------------|
| `cache_tmdb_languages` | TMDB language cache |
| `cache_trakt_user_movie_watched` | Trakt sync cache |
| `cache_trakt_user_movie_rating` | Trakt rating cache |
| `cache_jellyfin` | Jellyfin sync cache |

### System Tables

| Table | Description |
|-------|-------------|
| `job_queue` | Background job queue |
| `phinxlog` | Migration history |

## Key Table Schemas

### movie

```sql
CREATE TABLE movie (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(256) NOT NULL,
    original_title VARCHAR(256),
    tagline VARCHAR(512),
    overview TEXT,
    original_language VARCHAR(10),
    release_date DATE,
    runtime INT UNSIGNED,
    tmdb_id INT UNSIGNED UNIQUE,
    imdb_id VARCHAR(20),
    trakt_id INT UNSIGNED,
    tmdb_poster_path VARCHAR(256),
    poster_path VARCHAR(256),
    tmdb_vote_average DECIMAL(3,1),
    tmdb_vote_count INT UNSIGNED,
    imdb_rating DECIMAL(2,1),
    imdb_rating_vote_count INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### user

```sql
CREATE TABLE user (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(256) NOT NULL UNIQUE,
    email VARCHAR(256) NOT NULL UNIQUE,
    password_hash VARCHAR(256) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    privacy_level TINYINT DEFAULT 1,
    date_format_id TINYINT DEFAULT 0,
    totp_uri VARCHAR(256),
    profile_image VARCHAR(256),
    -- Integration tokens
    plex_access_token CHAR(128),
    jellyfin_access_token CHAR(128),
    jellyfin_user_id CHAR(128),
    jellyfin_server_url VARCHAR(256),
    -- Feature flags
    core_account_changes_disabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### movie_user_rating

```sql
CREATE TABLE movie_user_rating (
    movie_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT,                    -- Legacy 1-10 rating
    rating_popcorn TINYINT UNSIGNED,   -- Popcorn 1-7 rating
    comment TEXT,
    watched_year SMALLINT UNSIGNED,    -- Partial date: year
    watched_month TINYINT UNSIGNED,    -- Partial date: month
    watched_day TINYINT UNSIGNED,      -- Partial date: day
    location_id TINYINT UNSIGNED,      -- Where watched (1=Cinema, 2=Home, 3=Other)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (movie_id, user_id),
    FOREIGN KEY (movie_id) REFERENCES movie(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
);
```

### movie_user_watch_dates

```sql
CREATE TABLE movie_user_watch_dates (
    movie_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    watched_at DATETIME,
    plays INT DEFAULT 1,
    comment TEXT,
    position INT,
    location_id INT UNSIGNED,
    PRIMARY KEY (movie_id, user_id, watched_at),
    FOREIGN KEY (movie_id) REFERENCES movie(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
);
```

### user_auth_token

```sql
CREATE TABLE user_auth_token (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    token_hash VARCHAR(64),
    expiration_date DATETIME NOT NULL,
    device_name VARCHAR(256) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME,
    INDEX idx_token_hash (token_hash),
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
);
```

## Table Relationships

```
                    ┌──────────────┐
                    │    user      │
                    └──────┬───────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ user_auth_    │  │ movie_user_   │  │ movie_user_   │
│ token         │  │ rating        │  │ watch_dates   │
└───────────────┘  └───────┬───────┘  └───────┬───────┘
                           │                  │
                           └────────┬─────────┘
                                    ▼
                           ┌───────────────┐
                           │    movie      │
                           └───────┬───────┘
                                   │
        ┌──────────────────────────┼──────────────────────────┐
        │                          │                          │
        ▼                          ▼                          ▼
┌───────────────┐          ┌───────────────┐          ┌───────────────┐
│  movie_genre  │          │  movie_cast   │          │  movie_crew   │
└───────┬───────┘          └───────┬───────┘          └───────┬───────┘
        │                          │                          │
        ▼                          └────────────┬─────────────┘
┌───────────────┐                               ▼
│    genre      │                       ┌───────────────┐
└───────────────┘                       │    person     │
                                        └───────────────┘
```

## Query Patterns

### Repository Pattern

**File**: `src/Domain/Movie/MovieRepository.php`

```php
public function findById(int $id) : ?array
{
    return $this->dbConnection->fetchAssociative(
        'SELECT * FROM movie WHERE id = ?',
        [$id],
    ) ?: null;
}
```

### Group Statistics

**File**: `src/Service/GroupMovieService.php`

```php
public function getMovieGroupStats(int $movieId) : array
{
    return $this->dbConnection->fetchAssociative(
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
}
```

## Inspecting Data Locally

### Docker MySQL

```bash
# Connect to MySQL
docker exec -it pathary-mysql mysql -u pathary -pmovary pathary

# Run query
SELECT * FROM movie LIMIT 5;
```

### Docker SQLite

```bash
# Connect to SQLite
docker exec -it pathary-app sqlite3 /app/storage/movary.sqlite

# Run query
.tables
SELECT * FROM movie LIMIT 5;
```

### Show Table Structure

```sql
-- MySQL
DESCRIBE movie_user_rating;

-- SQLite
.schema movie_user_rating
```

## Backup and Restore

### MySQL Backup

```bash
docker exec pathary-mysql mysqldump -u pathary -pmovary pathary > backup.sql
```

### MySQL Restore

```bash
cat backup.sql | docker exec -i pathary-mysql mysql -u pathary -pmovary pathary
```

### SQLite Backup

```bash
docker cp pathary-app:/app/storage/movary.sqlite ./backup.sqlite
```

### SQLite Restore

```bash
docker cp ./backup.sqlite pathary-app:/app/storage/movary.sqlite
```

## Related Pages

- [Migrations](Migrations.md) - Schema changes
- [Ratings and Comments](Ratings-and-Comments.md) - Rating data model
- [Architecture](Architecture.md) - Data access layer

---

[← Back to Wiki Home](README.md)
