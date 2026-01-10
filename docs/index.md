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
| **Two-Factor Authentication** | Secure your account with TOTP, recovery codes, and trusted devices |
| **Password Policy** | Enforced strong password requirements for security |
| **Security Audit Log** | Track all security events and login activity |

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Reverse Proxy                           │
│                  (Nginx/Traefik/Caddy)                      │
└─────────────────────────┬───────────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────────┐
│                    Pathary Container                        │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              public/index.php                        │   │
│  │                 (Entry Point)                        │   │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                    │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │            FastRoute Dispatcher                      │   │
│  │           settings/routes.php                        │   │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                    │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │              Middleware Chain                        │   │
│  │    (Auth, Session, Authorization checks)             │   │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                    │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │           Controllers (Web & API)                    │   │
│  │      src/HttpController/{Web,Api}/*.php              │   │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                    │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │         Services & Domain Logic                      │   │
│  │   src/Service/*.php  src/Domain/*/*.php              │   │
│  └─────────────────────┬───────────────────────────────┘    │
│                        │                                    │
│  ┌─────────────────────▼───────────────────────────────┐    │
│  │              Twig Templates                          │   │
│  │             templates/*.twig                         │   │
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
- [Getting Started](Getting-Started) - Installation and first run

### Architecture & Code
- [Architecture](Architecture) - System design and folder structure
- [Routing and Controllers](Routing-and-Controllers) - Request handling

### Security
- [Authentication and Sessions](Authentication-and-Sessions) - Login and session management
- [Two-Factor Authentication](Two-Factor-Authentication) - TOTP, recovery codes, and trusted devices
- [Password Policy and Security](Password-Policy-and-Security) - Password requirements and best practices

### Data Layer
- [Database](Database) - Tables, relationships, and queries
- [Migrations](Migrations) - Schema management with Phinx

### Features
- [Movies and TMDB](Movies-and-TMDB) - Movie search and data sync
- [Ratings and Comments](Ratings-and-Comments) - The popcorn rating system

### Frontend
- [Frontend and UI](Frontend-and-UI) - Templates, CSS, and JavaScript

### Operations
- [Deployment](Deployment) - Docker, reverse proxy, and production setup
- [Logging and Troubleshooting](Logging-and-Troubleshooting) - Debugging issues

### Contributing
- [Issue Labels](Issue-Labels) - Label taxonomy and triage guidelines

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
