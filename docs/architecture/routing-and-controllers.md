# Routing and Controllers

This page documents how HTTP requests are routed to controllers in Pathary.

## Route Configuration

Routes are defined in `settings/routes.php` using FastRoute with a RouteList pattern. The RouterService coordinates route registration by grouping routes into web routes (HTML pages) and API routes (JSON). Routes are added to a RouteList, then registered with the RouteCollector via `RouterService::addRoutesToRouteCollector()`.

## Route Table

### Public Routes (No Authentication)

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/` | `PublicHomeController::index` | Home page with movie grid |
| GET | `/movie/{id}` | `PublicMovieController::detail` | Movie detail page |
| GET | `/login` | `AuthenticationController::renderLoginPage` | Login form |
| GET | `/landing` | `LandingPageController::render` | First-run setup |
| GET | `/create-user` | `CreateUserController::renderPage` | Registration form |
| GET | `/docs/api` | `OpenApiController::renderPage` | API documentation |
| GET | `/profile-images/{filename}` | `ProfileController::serveImage` | Serve profile images |

### Authenticated Routes (Login Required)

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| POST | `/movie/{id}/rate` | `RateMovieController::rate` | Submit rating |
| POST | `/movie/{id}/rate/delete` | `RateMovieController::deleteRating` | Delete rating |
| GET | `/search` | `SearchController::search` | Search movies |
| GET | `/movies` | `AllMoviesController::index` | All movies page |
| GET | `/profile` | `ProfileController::show` | User profile |
| POST | `/profile` | `ProfileController::update` | Update profile |
| GET | `/tmdb/movie/{tmdbId}` | `TmdbMovieController::detail` | TMDB movie preview |
| POST | `/tmdb/movie/{tmdbId}/add` | `TmdbMovieController::add` | Add movie from TMDB |
| GET | `/jobs` | `JobController::getJobs` | Job queue status |
| POST | `/log-movie` | `HistoryController::logMovie` | Log a movie watch |

### Settings Routes

> **Note**: Settings routes currently use the `/old/` prefix (e.g., `/old/settings/account/general`). This prefix is being phased out. See CLAUDE.md for migration plan. Documentation shows clean paths for clarity, but actual routes require the `/old/` prefix.

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/old/settings/account/general` | `SettingsController::renderGeneralAccountPage` | Account settings |
| GET | `/old/settings/account/security` | `SettingsController::renderSecurityAccountPage` | Security settings |
| GET | `/old/settings/integrations/trakt` | `SettingsController::renderTraktPage` | Trakt integration |
| GET | `/old/settings/integrations/plex` | `SettingsController::renderPlexPage` | Plex integration |
| GET | `/old/settings/integrations/jellyfin` | `SettingsController::renderJellyfinPage` | Jellyfin integration |
| GET | `/old/settings/server/general` | `SettingsController::renderServerGeneralPage` | Server settings |

### User Media Routes

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | `/users/{username}` | `DashboardController::render` | User dashboard |
| GET | `/users/{username}/history` | `HistoryController::renderHistory` | Watch history |
| GET | `/users/{username}/watchlist` | `WatchlistController::renderWatchlist` | User watchlist |
| GET | `/users/{username}/movies` | `MoviesController::renderPage` | User's movies |
| GET | `/users/{username}/movies/{id}` | `MovieController::renderPage` | Movie detail (user view) |

### API Routes

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| POST | `/api/authentication/token` | `AuthenticationController::createToken` | Login (get token) |
| DELETE | `/api/authentication/token` | `AuthenticationController::destroyToken` | Logout |
| GET | `/api/users/{username}/history/movies` | `HistoryController::getHistory` | Get watch history |
| POST | `/api/users/{username}/history/movies` | `HistoryController::addToHistory` | Add to history |
| GET | `/api/movies/search` | `MovieSearchController::search` | Search TMDB |
| POST | `/api/movies/add` | `MovieAddController::addMovie` | Add movie |

### Webhook Routes

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| POST | `/api/webhook/plex/{id}` | `PlexController::handlePlexWebhook` | Plex scrobble |
| POST | `/api/webhook/jellyfin/{id}` | `JellyfinController::handleJellyfinWebhook` | Jellyfin scrobble |
| POST | `/api/webhook/emby/{id}` | `EmbyController::handleEmbyWebhook` | Emby scrobble |
| POST | `/api/webhook/kodi/{id}` | `KodiController::handleKodiWebhook` | Kodi scrobble |

> **Note**: Additional routes not listed above include Admin routes (`/admin/*`), Person routes (`/person/{id}`), Watchlist route (`/watchlist`), API Admin routes (`/api/admin/*`), API Played routes (`/api/users/{username}/played/movies`), Radarr feed (`/api/feed/radarr/{id}`), and Dev routes (`/dev/*`). See `settings/routes.php` for the complete list of 100+ routes.

## Middleware

Middleware runs before controllers to perform checks.

### Available Middleware

#### Web Middleware (`src/HttpController/Web/Middleware/`)

| Middleware | Purpose |
|------------|---------|
| `UserIsAuthenticated` | Requires login |
| `UserIsUnauthenticated` | Must NOT be logged in |
| `UserIsAdmin` | Requires admin role |
| `ServerHasNoUsers` | First-run check |
| `ServerHasUsers` | Has existing users |
| `ServerHasRegistrationEnabled` | Registration allowed |
| `IsAuthorizedToReadUserData` | Can view user profile |
| `UserHasJellyfinToken` | Has Jellyfin configured |
| `StartSession` | Initialize PHP session |
| `CsrfProtection` | CSRF token validation (POST/PUT/DELETE) |
| `RateLimited` | Rate limiting for sensitive operations |
| `MovieSlugRedirector` | Redirects to correct movie slug URL |
| `PersonSlugRedirector` | Redirects to correct person slug URL |
| `OAuthLazyMonitoring` | OAuth connection monitoring |

#### API Middleware (`src/HttpController/Api/Middleware/`)

| Middleware | Purpose |
|------------|---------|
| `IsAuthenticated` | API token authentication |
| `IsAdmin` | API admin check |
| `IsAuthorizedToReadUserData` | Read permission check |
| `IsAuthorizedToWriteUserData` | Write permission check |

### Middleware Chain Example

```php
$routes->add('POST', '/movie/{id}/rate',
    [RateMovieController::class, 'rate'],
    [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\CsrfProtection::class  // Required for all POST/PUT/DELETE
    ]
);
```

> **Note**: All POST, PUT, and DELETE routes should include `CsrfProtection` middleware to prevent Cross-Site Request Forgery attacks.

## Controller Locations

### Web Controllers (`src/HttpController/Web/`)

| Controller | Purpose |
|------------|---------|
| `PublicHomeController` | Home page |
| `PublicMovieController` | Public movie detail |
| `RateMovieController` | Rating submission |
| `AuthenticationController` | Login/logout pages |
| `SettingsController` | All settings pages |
| `ProfileController` | User profile management |
| `DashboardController` | User dashboard |
| `HistoryController` | Watch history |
| `SearchController` | Movie search |
| `TmdbMovieController` | TMDB movie preview/add |

### API Controllers (`src/HttpController/Api/`)

| Controller | Purpose |
|------------|---------|
| `AuthenticationController` | Token management |
| `HistoryController` | History API |
| `WatchlistController` | Watchlist API |
| `MovieSearchController` | Search API |
| `PlexController` | Plex webhooks |
| `JellyfinController` | Jellyfin webhooks |

## Request/Response Flow

```php
// public/index.php
$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1]['handler'];

        // Run middleware chain
        foreach ($routeInfo[1]['middleware'] as $middleware) {
            $middlewareResponse = $container->call($middleware, [$httpRequest]);
            if ($middlewareResponse instanceof Response) {
                return $middlewareResponse; // Short-circuit
            }
        }

        // Call controller
        $response = $container->call($handler, [$httpRequest]);
        break;
}
```

## Route Parameters

Routes support regex-validated parameters:

```php
// Numeric ID only
'/movie/{id:[0-9]+}'

// Alphanumeric username
'/users/{username:[a-zA-Z0-9]+}'

// Any characters
'/profile-images/{filename:.+}'

// Optional slug suffix
'/users/{username}/movies/{id:\d+}[-{nameSlugSuffix:[^/]*}]'
```

## Related Pages

- [Architecture](architecture.md) - System overview
- [Authentication and Sessions](../security/authentication-and-sessions.md) - Login flow
- [Frontend and UI](frontend-and-ui.md) - Template rendering
