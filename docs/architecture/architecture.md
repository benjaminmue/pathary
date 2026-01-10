# Architecture

This page describes Pathary's system design, request flow, and codebase organization.

## Request Flow

```
HTTP Request
     │
     ▼
┌────────────────────────────────────┐
│     public/index.php               │  Entry point
│     (Loads bootstrap.php)          │
└────────────────┬───────────────────┘
                 │
                 ▼
┌────────────────────────────────────┐
│     DI Container (PHP-DI)          │  Dependency injection
│     (bootstrap.php)                │
└────────────────┬───────────────────┘
                 │
                 ▼
┌────────────────────────────────────┐
│     FastRoute Dispatcher           │  URL routing
│     (settings/routes.php)          │
└────────────────┬───────────────────┘
                 │
                 ▼
┌────────────────────────────────────┐
│     Middleware Chain               │  Auth checks, session start
│     (src/HttpController/*/         │
│      Middleware/*.php)             │
└────────────────┬───────────────────┘
                 │
                 ▼
┌────────────────────────────────────┐
│     Controller                     │  Business logic
│     (src/HttpController/{Web,Api}) │
└────────────────┬───────────────────┘
                 │
                 ▼
┌────────────────────────────────────┐
│     Services & Repositories        │  Data access, external APIs
│     (src/Service, src/Domain)      │
└────────────────┬───────────────────┘
                 │
                 ▼
┌────────────────────────────────────┐
│     Twig Template Rendering        │  HTML generation
│     (templates/*.twig)             │
└────────────────┬───────────────────┘
                 │
                 ▼
     HTTP Response
```

## Folder Structure

```
pathary/
├── bin/                        # CLI entry points
│   └── console.php             # Command-line application
├── bootstrap.php               # DI container setup
├── build/                      # Docker build files
│   └── scripts/
│       └── entrypoint.sh       # Container startup script
├── db/
│   └── migrations/
│       ├── mysql/              # MySQL migrations
│       └── sqlite/             # SQLite migrations
├── docs/                       # Documentation
├── public/                     # Web root
│   ├── index.php               # HTTP entry point
│   ├── css/                    # Stylesheets
│   ├── js/                     # JavaScript
│   └── images/                 # Static images
├── settings/
│   ├── routes.php              # Route definitions
│   └── phinx.php               # Migration config
├── src/
│   ├── Api/                    # External API clients
│   │   ├── Tmdb/               # TMDB API
│   │   ├── Trakt/              # Trakt API
│   │   ├── Plex/               # Plex API
│   │   └── Jellyfin/           # Jellyfin API
│   ├── Command/                # CLI commands
│   ├── Domain/                 # Business entities
│   │   ├── Movie/              # Movie, ratings, history
│   │   ├── User/               # User accounts
│   │   ├── Person/             # Actors, directors
│   │   └── Genre/              # Movie genres
│   ├── Factory.php             # DI factory methods
│   ├── HttpController/
│   │   ├── Api/                # REST API controllers
│   │   └── Web/                # Web page controllers
│   │       └── Middleware/     # Request middleware
│   ├── JobQueue/               # Background job system
│   ├── Service/                # Application services
│   │   ├── GroupMovieService   # Group ratings logic
│   │   └── ...                 # Integration services
│   ├── Util/                   # Utilities
│   └── ValueObject/            # Immutable value objects
├── storage/                    # Runtime data
│   ├── logs/                   # Application logs
│   └── images/                 # Cached images
└── templates/                  # Twig templates
    ├── base.html.twig          # Base layout
    ├── component/              # Reusable components
    ├── layouts/                # Page layouts
    ├── page/                   # Full pages
    └── public/                 # Public-facing pages
```

## Key Design Decisions

### 1. PHP-DI for Dependency Injection

The application uses PHP-DI with factory methods defined in `src/Factory.php`. Services are created lazily and injected automatically.

**Key file**: `bootstrap.php`
```php
$builder = new DI\ContainerBuilder();
$builder->addDefinitions([
    Config::class => DI\factory([Factory::class, 'createConfig']),
    Connection::class => DI\factory([Factory::class, 'createDbConnection']),
    // ...
]);
```

### 2. FastRoute for Routing

Routes are defined in `settings/routes.php` with support for:
- Route groups (web vs API)
- Middleware chains
- Route parameters with regex validation

### 3. Twig for Templating

HTML rendering uses Twig with:
- Template inheritance (`base.html.twig`)
- Component partials (`templates/component/`)
- Custom filters and functions

### 4. Doctrine DBAL for Database

Database access uses Doctrine DBAL (not ORM):
- Direct SQL queries with parameter binding
- Supports MySQL and SQLite
- Migrations via Phinx

### 5. Repository Pattern

Data access is organized by domain:
```
src/Domain/Movie/
├── MovieApi.php           # High-level API
├── MovieRepository.php    # Database queries
├── MovieEntity.php        # Data structure
└── MovieEntityList.php    # Collection
```

### 6. Group-Focused Design

Unlike single-user movie trackers, Pathary is designed for groups:
- `GroupMovieService` calculates aggregate ratings
- Home page shows movies watched by any user
- Individual ratings display all users' opinions

## Key Classes

| Class | Location | Purpose |
|-------|----------|---------|
| `Factory` | `src/Factory.php` | DI factory methods |
| `Authentication` | `src/Domain/User/Service/Authentication.php` | Login/session handling |
| `GroupMovieService` | `src/Service/GroupMovieService.php` | Group rating aggregation |
| `MovieRepository` | `src/Domain/Movie/MovieRepository.php` | Movie database queries |
| `TmdbApi` | `src/Api/Tmdb/TmdbApi.php` | TMDB data fetching |

## Related Pages

- [Routing and Controllers](Routing-and-Controllers)] - Detailed route documentation
- [Database](Database)] - Schema and queries
- [Authentication](Authentication-and-Sessions)] - Security implementation

---

[← Back to Wiki Home](Home)
