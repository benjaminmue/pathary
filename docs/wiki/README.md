# Pathary Wiki

Pathary is a self-hosted movie tracking application for friend groups. Rate movies on a 1-7 popcorn scale, see what others thought, and discover new films via TMDB integration.

> **Attribution**: Pathary is a fork of [Movary](https://github.com/leepeuker/movary) by Lee Peuker.

## Who Is This For?

- Friend groups who watch movies together
- Film clubs wanting a shared rating system
- Anyone who wants a self-hosted alternative to Letterboxd with group features

## Feature Overview

| Feature | Description |
|---------|-------------|
| **Popcorn Ratings** | Rate movies 1-7 on a unique popcorn scale |
| **Group View** | See aggregate ratings from all users |
| **Watch History** | Track when and where you watched movies |
| **TMDB Integration** | Search and add movies from The Movie Database |
| **Watchlist** | Keep track of movies you want to watch |
| **Import/Export** | Sync with Trakt, Letterboxd, Plex, Jellyfin |
| **Profile Images** | Customize your user profile |
| **Dark Mode** | Built-in dark theme support |

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Reverse Proxy                           │
│                  (Nginx/Traefik/Caddy)                      │
└─────────────────────────┬───────────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────────┐
│                    Pathary Container                         │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              public/index.php                        │    │
│  │                 (Entry Point)                        │    │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                     │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │            FastRoute Dispatcher                      │    │
│  │           settings/routes.php                        │    │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                     │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │              Middleware Chain                        │    │
│  │    (Auth, Session, Authorization checks)             │    │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                     │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │           Controllers (Web & API)                    │    │
│  │      src/HttpController/{Web,Api}/*.php              │    │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                     │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │         Services & Domain Logic                      │    │
│  │   src/Service/*.php  src/Domain/*/*.php              │    │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                     │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │              Twig Templates                          │    │
│  │             templates/*.twig                         │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                 MySQL or SQLite Database                    │
└─────────────────────────────────────────────────────────────┘
```

## Wiki Navigation

### Getting Started
- [Getting Started](Getting-Started.md) - Installation and first run

### Architecture & Code
- [Architecture](Architecture.md) - System design and folder structure
- [Routing and Controllers](Routing-and-Controllers.md) - Request handling
- [Authentication and Sessions](Authentication-and-Sessions.md) - Login and security

### Data Layer
- [Database](Database.md) - Tables, relationships, and queries
- [Migrations](Migrations.md) - Schema management with Phinx

### Features
- [Movies and TMDB](Movies-and-TMDB.md) - Movie search and data sync
- [Ratings and Comments](Ratings-and-Comments.md) - The popcorn rating system

### Frontend
- [Frontend and UI](Frontend-and-UI.md) - Templates, CSS, and JavaScript

### Operations
- [Deployment](Deployment.md) - Docker, reverse proxy, and production setup
- [Logging and Troubleshooting](Logging-and-Troubleshooting.md) - Debugging issues

---

## Quick Links

| Resource | Location |
|----------|----------|
| Source Code | `src/` |
| Templates | `templates/` |
| Migrations | `db/migrations/` |
| Configuration | `.env.example` |
| Docker Setup | `docker-compose.yml` |
| API Docs | `/docs/api` (when running) |
