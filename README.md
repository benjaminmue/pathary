<p align="center">
  <img src="public/images/pathary-logo-384x384.png" alt="Pathary Logo" width="128" height="128">
</p>

<h1 align="center">Pathary</h1>

<p align="center">
  Self-hosted group movie tracking with popcorn ratings
</p>

<p align="center">
  <span style="background-color: #f97316; color: #ffffff; padding: 8px 16px; border-radius: 6px; font-weight: 500;">
    ⚠️ This repository is in heavy development and will be updated frequently.
  </span>
</p>

---

## What is Pathary?

Pathary is a self-hosted movie tracking app for friend groups. Rate movies on a 1-7 popcorn (🍿) scale, see what others thought, and discover new films via TMDB integration. Fork of [Movary](https://github.com/leepeuker/movary).

## Features

- **Public home page** — Poster grid of recently added movies
- **Movie details** — Global average rating, individual user ratings, and comments
- **🍿 Popcorn ratings** — Rate movies 1-7 with optional comments
- **TMDB search** — Search local library first, then TMDB; add new movies instantly
- **All Movies browser** — Sort by title, year, rating; filter by genre, year range
- **Dark mode** — Toggle between light and dark themes
- **Responsive UI** — Works on desktop and mobile (Bootstrap 5)

## 🐳 Quick Start

### Option 1: Local development script

```bash
# Clone the repo
git clone https://github.com/leepeuker/pathary.git
cd pathary

# Set your TMDB API key
export TMDB_API_KEY=your-api-key

# Start everything
./bin/local-up.sh
```

Open http://localhost:8080 — first startup takes 30-60 seconds for MySQL init and migrations.

### Option 2: Docker run

```bash
docker run -d \
  --name pathary \
  -p 8080:80 \
  -e TMDB_API_KEY=your-api-key \
  -e DATABASE_MODE=sqlite \
  -v pathary_storage:/app/storage \
  ghcr.io/benjaminmue/pathary:latest
```

### Option 3: Docker Compose

```yaml
services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    ports:
      - "8080:80"
    environment:
      TMDB_API_KEY: "your-api-key"
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary"
      DATABASE_MYSQL_PASSWORD: "secret"
    volumes:
      - pathary_storage:/app/storage
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary"
      MYSQL_PASSWORD: "secret"
      MYSQL_ROOT_PASSWORD: "rootsecret"
    volumes:
      - pathary_db:/var/lib/mysql

volumes:
  pathary_storage:
  pathary_db:
```

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `TMDB_API_KEY` | Yes | — | TMDB API key ([get one here](https://www.themoviedb.org/settings/api)) |
| `DATABASE_MODE` | No | `sqlite` | `sqlite` or `mysql` |
| `DATABASE_MYSQL_HOST` | If mysql | — | MySQL hostname |
| `DATABASE_MYSQL_NAME` | If mysql | — | Database name |
| `DATABASE_MYSQL_USER` | If mysql | — | Database user |
| `DATABASE_MYSQL_PASSWORD` | If mysql | — | Database password |
| `APPLICATION_URL` | No | — | Public URL (e.g., `https://movies.example.com`) |
| `MIGRATIONS_AUTO_RUN` | No | `1` | Set to `0` to disable auto-migrations |

See [.env.example](.env.example) for all options.

## Migrations

Database migrations run automatically when the container starts. To disable:

```bash
docker run -e MIGRATIONS_AUTO_RUN=0 ...
```

Manual migration commands:

```bash
# Run migrations
docker exec pathary-app php /app/vendor/bin/phinx migrate -c /app/settings/phinx.php

# Check status
docker exec pathary-app php /app/vendor/bin/phinx status -c /app/settings/phinx.php
```

See [docs/migrations.md](docs/migrations.md) for details.

## Reverse Proxy

When running behind Nginx, Traefik, or Caddy:

1. Set `APPLICATION_URL` to your public URL
2. Forward headers: `X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`

See [docs/proxy.md](docs/proxy.md) for full configuration examples.

## Development

```bash
# Install PHP dependencies
composer install

# Run all checks (code style, static analysis, unit tests)
composer test

# Individual checks
composer test-cs       # PHP CodeSniffer
composer test-phpstan  # PHPStan
composer test-psalm    # Psalm
composer test-unit     # PHPUnit
```

## Image Tags

| Tag | Description |
|-----|-------------|
| `latest` | Latest stable release |
| `main` | Latest build from main branch |
| `vX.Y.Z` | Specific version |
| `sha-XXXXXXX` | Specific commit |

```bash
docker pull ghcr.io/benjaminmue/pathary:latest
```

## License

MIT License. See [LICENSE](LICENSE).

**Original project:** [Movary](https://github.com/leepeuker/movary) by Lee Peuker
**Fork:** Pathary by Benjamin Müller

---

## Notes

⚠️ Most of the code was reviewed with Claude Code and ChatGPT to support development.
