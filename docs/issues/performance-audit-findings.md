# Performance Audit Findings

## Summary

| Priority | Count |
|----------|-------|
| Quick wins | 1 |
| Medium effort | 2 |
| Database/Index | 1 |

---

## Quick Wins

### PERF-003: Add Pagination to Rating Lists

**Severity:** Low
**Effort:** Low

**Affected Files:**
- `src/Service/GroupMovieService.php:113-136`

**Description:**
The `getMovieIndividualRatings()` method returns ALL ratings for a movie without any pagination or limit. For popular movies with many ratings, this could cause slow page loads.

**Current Code:**
```php
public function getMovieIndividualRatings(int $movieId): array
{
    $ratings = $this->dbConnection->fetchAllAssociative(
        <<<SQL
        SELECT u.name AS user_name, mur.rating_popcorn, mur.comment, ...
        FROM movie_user_rating mur
        JOIN user u ON u.id = mur.user_id
        WHERE mur.movie_id = ?
        SQL,
        [$movieId],
    );
    shuffle($ratings);
    return $ratings;
}
```

**Recommended Fix:**
Add a reasonable default limit:
```php
public function getMovieIndividualRatings(int $movieId, int $limit = 50): array
{
    // Add LIMIT ? to query
    // Add pagination UI if needed
}
```

---

## Medium Effort Items

### PERF-001: Refactor Subqueries to JOINs in Movie List

**Severity:** Medium
**Effort:** Medium

**Affected Files:**
- `src/Service/GroupMovieService.php:222-250`

**Description:**
The `getAllMovies()` method uses correlated subqueries for calculating `avg_popcorn` and `own_rating` per movie. These subqueries execute once per row in the result set, which is inefficient for large movie libraries.

**Current Query Pattern:**
```sql
SELECT m.id, m.title,
    (SELECT AVG(mur.rating_popcorn)
     FROM movie_user_rating mur
     WHERE mur.movie_id = m.id AND mur.rating_popcorn IS NOT NULL) AS avg_popcorn,
    (SELECT mur2.rating_popcorn
     FROM movie_user_rating mur2
     WHERE mur2.movie_id = m.id AND mur2.user_id = ?) AS own_rating
FROM movie m
JOIN movie_user_watch_dates muwd ON muwd.movie_id = m.id
...
```

**Recommended Fix:**
Refactor to use LEFT JOINs with GROUP BY:
```sql
SELECT m.id, m.title,
    AVG(mur.rating_popcorn) AS avg_popcorn,
    MAX(CASE WHEN mur.user_id = ? THEN mur.rating_popcorn END) AS own_rating
FROM movie m
JOIN movie_user_watch_dates muwd ON muwd.movie_id = m.id
LEFT JOIN movie_user_rating mur ON mur.movie_id = m.id
GROUP BY m.id
...
```

---

### PERF-004: Add Caching for Aggregate Statistics

**Severity:** Low
**Effort:** Medium

**Affected Files:**
- `src/Service/GroupMovieService.php:65-95`

**Description:**
`getMovieGroupStats()` recalculates average ratings and counts on every page view. For frequently accessed movies (e.g., popular films), this creates unnecessary database load.

**Current Implementation:**
```php
public function getMovieGroupStats(int $movieId): array
{
    $ratingStats = $this->dbConnection->fetchAssociative(
        'SELECT AVG(rating_popcorn), COUNT(rating_popcorn), MAX(updated_at)
         FROM movie_user_rating WHERE movie_id = ?',
        [$movieId],
    );
    // ... returns stats
}
```

**Recommended Fix Options:**

1. **In-memory cache with TTL:**
```php
// Using a simple cache service
$cacheKey = "movie_stats_{$movieId}";
if ($cached = $this->cache->get($cacheKey)) {
    return $cached;
}
$stats = $this->calculateStats($movieId);
$this->cache->set($cacheKey, $stats, 300); // 5 min TTL
return $stats;
```

2. **Denormalized columns:**
Add `avg_rating_cached` and `rating_count_cached` columns to the `movie` table, updated via triggers or on rating save.

---

## Database/Index Suggestions

### PERF-002: Add Index on movie_user_rating.movie_id

**Severity:** Medium
**Effort:** Low (migration only)

**Affected Tables:**
- `movie_user_rating`

**Description:**
The `movie_user_rating` table has a composite primary key `(movie_id, user_id)`, but many queries filter or aggregate by `movie_id` alone. While MySQL can use a composite index for prefix lookups, an explicit single-column index may improve query planning.

**Queries that would benefit:**
```sql
-- Average rating calculation
SELECT AVG(rating_popcorn) FROM movie_user_rating WHERE movie_id = ?

-- Individual ratings list
SELECT * FROM movie_user_rating WHERE movie_id = ?

-- Rating count
SELECT COUNT(*) FROM movie_user_rating WHERE movie_id = ?
```

**Recommended Fix:**
Create a migration to add the index:
```php
// db/migrations/mysql/20251215000000_AddMovieIdIndexToRatings.php
public function up(): void
{
    $this->execute(
        'CREATE INDEX idx_movie_user_rating_movie ON movie_user_rating(movie_id)'
    );
}

public function down(): void
{
    $this->execute(
        'DROP INDEX idx_movie_user_rating_movie ON movie_user_rating'
    );
}
```

**Expected Impact:**
- Faster movie detail page loads (rating aggregation)
- Faster movie list sorting by rating
- Minimal storage overhead

---

## Summary Table

| ID | Finding | Effort | Impact | Priority |
|----|---------|--------|--------|----------|
| PERF-002 | Add movie_id index | Low | High | 1 |
| PERF-001 | Refactor subqueries | Medium | High | 2 |
| PERF-003 | Add pagination | Low | Medium | 3 |
| PERF-004 | Add caching | Medium | Medium | 4 |

---

## Monitoring Recommendations

Consider adding query logging in development to identify slow queries:
```php
// In bootstrap.php or Factory.php
$config->setSQLLogger(new \Doctrine\DBAL\Logging\DebugStack());
```

Or use MySQL's slow query log:
```ini
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
```
