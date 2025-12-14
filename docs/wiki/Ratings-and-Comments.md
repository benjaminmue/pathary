# Ratings and Comments

This page covers Pathary's unique popcorn rating system and how ratings are stored and displayed.

## Popcorn Rating Scale

Pathary uses a 1-7 popcorn scale instead of traditional 5 or 10-star ratings:

| Rating | Meaning |
|--------|---------|
| 1 | Terrible |
| 2 | Bad |
| 3 | Below average |
| 4 | Average |
| 5 | Good |
| 6 | Great |
| 7 | Masterpiece |

Visual representation: 🍿🍿🍿🍿🍿🍿🍿

## Rating Data Model

### Table: movie_user_rating

**File**: `db/migrations/mysql/20251214000000_AddWatchedDateAndLocationToMovieUserRating.php`

```sql
CREATE TABLE movie_user_rating (
    movie_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT,                    -- Legacy 1-10 rating
    rating_popcorn TINYINT UNSIGNED,   -- Popcorn 1-7 rating
    comment TEXT,                      -- User's review
    watched_year SMALLINT UNSIGNED,    -- When watched (year)
    watched_month TINYINT UNSIGNED,    -- When watched (month, optional)
    watched_day TINYINT UNSIGNED,      -- When watched (day, optional)
    location_id TINYINT UNSIGNED,      -- Where watched
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    PRIMARY KEY (movie_id, user_id)
);
```

### Location Options

| ID | Label |
|----|-------|
| 1 | Cinema |
| 2 | At Home |
| 3 | Other |

**File**: `src/HttpController/Web/RateMovieController.php`

```php
public const int LOCATION_CINEMA = 1;
public const int LOCATION_AT_HOME = 2;
public const int LOCATION_OTHER = 3;

public const array LOCATION_LABELS = [
    self::LOCATION_CINEMA => 'Cinema',
    self::LOCATION_AT_HOME => 'At Home',
    self::LOCATION_OTHER => 'Other',
];
```

## Rating Submission

### Submit Rating Flow

```
User submits rating form
        ↓
POST /movie/{id}/rate
        ↓
RateMovieController::rate()
        ↓
MovieRepository::upsertUserRatingWithComment()
        ↓
Redirect to movie page
```

### Controller Implementation

**File**: `src/HttpController/Web/RateMovieController.php`

```php
public function rate(Request $request) : Response
{
    $movieId = (int)$request->getRouteParameters()['id'];
    $userId = $this->authenticationService->getCurrentUserId();
    $postData = $request->getPostParameters();

    // Parse rating (0 means unrated)
    $ratingValue = isset($postData['rating_popcorn']) ? (int)$postData['rating_popcorn'] : 0;
    $ratingPopcorn = ($ratingValue >= 1 && $ratingValue <= 7)
        ? PopcornRating::create($ratingValue)
        : null;

    // Parse comment
    $comment = isset($postData['comment']) && trim($postData['comment']) !== ''
        ? trim($postData['comment'])
        : null;

    // Parse watched date (partial date support)
    $watchedYear = $this->parseIntOrNull($postData['watched_year'] ?? null);
    $watchedMonth = $this->parseIntOrNull($postData['watched_month'] ?? null);
    $watchedDay = $this->parseIntOrNull($postData['watched_day'] ?? null);

    // Validate date hierarchy
    if ($watchedDay !== null && $watchedMonth === null) {
        $watchedDay = null;
    }
    if ($watchedMonth !== null && $watchedYear === null) {
        $watchedMonth = null;
        $watchedDay = null;
    }

    // Parse location
    $locationId = $this->parseIntOrNull($postData['location_id'] ?? null);

    // Save rating
    $this->movieRepository->upsertUserRatingWithComment(
        $movieId, $userId, $ratingPopcorn, $comment,
        $watchedYear, $watchedMonth, $watchedDay, $locationId,
    );

    return Response::createSeeOther('/movie/' . $movieId . '#ratings');
}
```

## Partial Date Support

Users can record when they watched a movie with varying precision:

| Precision | Example Display |
|-----------|-----------------|
| Full date | 14.12.2024 |
| Month + Year | 12.2024 |
| Year only | 2024 |

**Template logic** (`templates/public/movie_detail.twig`):

```twig
{% if rating.watched_day and rating.watched_month %}
    {{ '%02d'|format(rating.watched_day) }}.{{ '%02d'|format(rating.watched_month) }}.{{ rating.watched_year }}
{% elseif rating.watched_month %}
    {{ '%02d'|format(rating.watched_month) }}.{{ rating.watched_year }}
{% else %}
    {{ rating.watched_year }}
{% endif %}
```

## Delete Rating

### Delete Flow

```
User clicks delete button
        ↓
POST /movie/{id}/rate/delete
        ↓
RateMovieController::deleteRating()
        ↓
MovieRepository::deleteUserRating()
        ↓
Redirect to movie page
```

**File**: `src/HttpController/Web/RateMovieController.php`

```php
public function deleteRating(Request $request) : Response
{
    $movieId = (int)$request->getRouteParameters()['id'];
    $userId = $this->authenticationService->getCurrentUserId();

    $this->movieRepository->deleteUserRating($movieId, $userId);

    return Response::createSeeOther('/movie/' . $movieId . '#ratings');
}
```

## Global Rating Calculation

Pathary calculates an average rating across all users.

### GroupMovieService

**File**: `src/Service/GroupMovieService.php`

```php
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

    return [
        'avg_popcorn' => round((float)$ratingStats['avg_popcorn'], 1),
        'rating_count' => (int)$ratingStats['rating_count'],
        'last_activity_at' => $ratingStats['last_rating_activity'],
    ];
}
```

### Individual Ratings

```php
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
            COALESCE(mur.updated_at, mur.created_at) AS updated_at
        FROM movie_user_rating mur
        JOIN user u ON u.id = mur.user_id
        WHERE mur.movie_id = ?
        SQL,
        [$movieId],
    );

    shuffle($ratings); // Randomize order
    return $ratings;
}
```

## UI Components

### Rating Form

**File**: `templates/public/movie_detail.twig`

The rating form uses a 3-column layout:
1. **Left**: Popcorn rating selector
2. **Middle**: Watched date (3 dropdowns)
3. **Right**: Location dropdown

```html
<div class="rating-form-row">
    <div class="rating-form-col">
        <label>Rating</label>
        {% include 'components/popcorn_rating.twig' %}
    </div>

    <div class="rating-form-divider"></div>

    <div class="rating-form-col">
        <label>Watched Date</label>
        <div class="date-dropdowns">
            <select name="watched_day">...</select>
            <select name="watched_month">...</select>
            <select name="watched_year">...</select>
        </div>
    </div>

    <div class="rating-form-divider"></div>

    <div class="rating-form-col">
        <label>Location</label>
        <select name="location_id">...</select>
    </div>
</div>
```

### Popcorn Rating Widget

**File**: `templates/components/popcorn_rating.twig`

Interactive rating selector using buttons:

```html
<div class="popcorn-rating popcorn-rating--input">
    <input type="hidden" name="rating_popcorn" value="{{ valueInt }}">
    {% for i in 1..7 %}
        <button type="button"
                class="popcorn-rating__item {{ i <= valueInt ? 'popcorn-on' : 'popcorn-off' }}"
                data-value="{{ i }}">
            🍿
        </button>
    {% endfor %}
</div>
```

### Rating Display

Individual ratings are shown in cards:

```html
<div class="rating-card">
    <div class="rating-card-header">
        <span class="rating-user-name">{{ rating.user_name }}</span>
        <div class="popcorn-rating popcorn-rating--small">
            {% for i in 1..7 %}
                <span class="{{ i <= rating.rating_popcorn ? 'popcorn-on' : 'popcorn-off' }}">🍿</span>
            {% endfor %}
        </div>
    </div>
    {% if rating.comment %}
        <p class="rating-comment">"{{ rating.comment }}"</p>
    {% endif %}
    <div class="rating-meta">
        {% if rating.watched_year %}
            <span><i class="bi bi-calendar-event"></i> {{ watchedDateDisplay }}</span>
        {% endif %}
        {% if rating.location_id %}
            <span><i class="bi bi-geo-alt"></i> {{ locationLabel }}</span>
        {% endif %}
    </div>
</div>
```

## JavaScript

### Date Dropdown Logic

**File**: `templates/public/movie_detail.twig` (inline script)

```javascript
function updateDateDropdowns() {
    const year = yearSelect.value;
    const month = monthSelect.value;

    // Month requires year
    if (!year) {
        monthSelect.disabled = true;
        monthSelect.value = '';
        daySelect.disabled = true;
        daySelect.value = '';
    } else {
        monthSelect.disabled = false;
        if (!month) {
            daySelect.disabled = true;
            daySelect.value = '';
        } else {
            daySelect.disabled = false;
            updateDayOptions(parseInt(year), parseInt(month));
        }
    }
}

function updateDayOptions(year, month) {
    const daysInMonth = new Date(year, month, 0).getDate();
    // Update day dropdown options...
}
```

## CSS Styling

**File**: `templates/public/movie_detail.twig` (style block)

```css
.rating-form-row {
    display: flex;
    gap: 0;
}

.rating-form-col {
    flex: 1;
    padding: 0 1rem;
}

.rating-form-divider {
    width: 2px;
    background: linear-gradient(180deg, transparent, var(--accent-purple), transparent);
    box-shadow: 0 0 8px rgba(111, 45, 189, 0.3);
}

.popcorn-rating__item.popcorn-on {
    opacity: 1;
}

.popcorn-rating__item.popcorn-off {
    opacity: 0.3;
}
```

## Related Pages

- [Database](Database.md) - Rating table schema
- [Movies and TMDB](Movies-and-TMDB.md) - Movie data
- [Frontend and UI](Frontend-and-UI.md) - UI components

---

[← Back to Wiki Home](README.md)
