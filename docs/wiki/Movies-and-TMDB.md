# Movies and TMDB

This page covers how Pathary manages movie data and integrates with The Movie Database (TMDB).

## Overview

Pathary uses TMDB as its primary source for movie metadata:
- Movie titles, descriptions, and release dates
- Poster images
- Cast and crew information
- Genre classifications

## TMDB API Key

A TMDB API key is required. Get one at: https://www.themoviedb.org/settings/api

```env
TMDB_API_KEY=your_api_key_here
```

## Movie Entity

**File**: `src/Domain/Movie/MovieEntity.php`

Key fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Internal Pathary ID |
| `tmdb_id` | int | TMDB movie ID |
| `imdb_id` | string | IMDb ID (e.g., `tt1234567`) |
| `title` | string | Display title |
| `original_title` | string | Original language title |
| `overview` | string | Plot summary |
| `release_date` | Date | Release date |
| `runtime` | int | Duration in minutes |
| `tmdb_poster_path` | string | TMDB poster URL path |
| `poster_path` | string | Cached local poster path |

## TMDB Search

### Search Flow

```
User enters search query
        ↓
GET /search?query=...
        ↓
SearchController::search()
        ↓
TmdbApi::searchMovies($query)
        ↓
TMDB API: /search/movie
        ↓
Results displayed in UI
```

### Search Implementation

**File**: `src/Api/Tmdb/TmdbApi.php`

```php
public function searchMovies(string $query, int $page = 1) : array
{
    $response = $this->client->get('/search/movie', [
        'query' => [
            'query' => $query,
            'page' => $page,
            'language' => $this->language,
        ],
    ]);

    return json_decode($response->getBody()->getContents(), true);
}
```

### API Search Endpoint

```
GET /api/movies/search?query=inception
```

**File**: `src/HttpController/Api/MovieSearchController.php`

## Adding Movies

### Add From Search Results

```
User clicks "Add" on search result
        ↓
POST /tmdb/movie/{tmdbId}/add
        ↓
TmdbMovieController::add()
        ↓
TmdbApi::getMovie($tmdbId)
        ↓
MovieRepository::create($movieData)
        ↓
Redirect to movie page
```

### Add Implementation

**File**: `src/HttpController/Web/TmdbMovieController.php`

```php
public function add(Request $request) : Response
{
    $tmdbId = (int)$request->getRouteParameters()['tmdbId'];
    $userId = $this->authenticationService->getCurrentUserId();

    // Fetch from TMDB
    $tmdbMovie = $this->tmdbApi->getMovie($tmdbId);

    // Create local movie record
    $movie = $this->movieApi->createFromTmdb($tmdbMovie);

    // Add to user's watch history
    $this->movieHistoryApi->create($movie->getId(), $userId, Date::createNow());

    return Response::createSeeOther('/movie/' . $movie->getId());
}
```

## TMDB Data Sync

### Movie Data Refresh

```
GET /movies/{id}/refresh-tmdb
```

**File**: `src/HttpController/Web/Movie/MovieController.php`

```php
public function refreshTmdbData(Request $request) : Response
{
    $movieId = (int)$request->getRouteParameters()['id'];

    $this->syncMovieService->syncMovie($movieId);

    return Response::createSeeOther('/movie/' . $movieId);
}
```

### Sync Service

**File**: `src/Service/Tmdb/SyncMovie.php`

Updates:
- Basic metadata (title, overview, runtime)
- Cast and crew
- Genres
- Production companies
- Poster images

## Image Handling

### Image URLs

TMDB images are served from their CDN:
```
https://image.tmdb.org/t/p/{size}/{path}
```

Sizes: `w92`, `w154`, `w185`, `w342`, `w500`, `w780`, `original`

### Image Caching

**File**: `src/Service/ImageCacheService.php`

Enable caching:
```env
TMDB_ENABLE_IMAGE_CACHING=1
```

When enabled, images are downloaded to `storage/images/` and served locally.

### Image URL Service

**File**: `src/Service/ImageUrlService.php`

```php
public function replacePosterPathWithImageSrcUrl(array $movies) : array
{
    foreach ($movies as &$movie) {
        if ($movie['tmdb_poster_path'] !== null) {
            $movie['poster_src'] = $this->generatePosterUrl($movie['tmdb_poster_path']);
        }
    }
    return $movies;
}
```

## TMDB Client

**File**: `src/Api/Tmdb/TmdbClient.php`

```php
class TmdbClient
{
    private const string BASE_URL = 'https://api.themoviedb.org/3';

    public function get(string $endpoint, array $options = []) : ResponseInterface
    {
        $options['query']['api_key'] = $this->apiKey;

        return $this->httpClient->request('GET', self::BASE_URL . $endpoint, $options);
    }
}
```

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                          TMDB API                                │
│                  api.themoviedb.org/3                            │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      TmdbClient                                  │
│              src/Api/Tmdb/TmdbClient.php                         │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                       TmdbApi                                    │
│               src/Api/Tmdb/TmdbApi.php                           │
│                                                                  │
│   searchMovies()  getMovie()  getPerson()  getCredits()         │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Service Layer                                  │
│                                                                  │
│   SyncMovie    MovieApi    GroupMovieService                     │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                  MovieRepository                                 │
│          src/Domain/Movie/MovieRepository.php                    │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Database                                    │
│              movie, movie_genre, movie_cast, etc.                │
└─────────────────────────────────────────────────────────────────┘
```

## Related Tables

| Table | Content |
|-------|---------|
| `movie` | Core movie data |
| `movie_genre` | Genre associations |
| `movie_cast` | Actor associations |
| `movie_crew` | Director/crew associations |
| `genre` | Genre definitions |
| `person` | Actor/director data |

## Error Handling

### API Errors

**File**: `src/Api/Tmdb/Exception/`

| Exception | Cause |
|-----------|-------|
| `TmdbAuthorizationError` | Invalid API key |
| `TmdbResourceNotFound` | Movie not found on TMDB |

### Rate Limiting

TMDB has rate limits (~50 requests/second). The application doesn't currently implement rate limiting, but high-volume operations (bulk sync) should be throttled.

## CLI Commands

### Sync All Movies

```bash
docker exec pathary-app php bin/console.php tmdb:movie:sync
```

### Sync Person Data

```bash
docker exec pathary-app php bin/console.php tmdb:person:sync
```

## Related Pages

- [Ratings and Comments](Ratings-and-Comments.md) - User ratings for movies
- [Database](Database.md) - Movie table schema
- [Frontend and UI](Frontend-and-UI.md) - Movie display templates

---

[← Back to Wiki Home](README.md)
