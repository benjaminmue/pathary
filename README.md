# Movies

A fork of [Movary](https://github.com/leepeuker/movary) for group movie tracking. Track movies with friends, import metadata from TMDB, and rate films using a 1-7 popcorn scale. Self-hosted via Docker with MySQL or SQLite.

## Features

- **Public home page** - Poster grid showing the 20 most recently added movies
- **Movie details** - View global average rating, individual user ratings, and comments
- **Popcorn rating** - Rate movies on a 1-7 scale with optional comments
- **Persistent login** - Stay logged in until cookies are cleared
- **Movie search** - Search local library first, fallback to TMDB, add new movies
- **All movies list** - Browse library with sorting (title, year, rating) and filtering (genre, year, rating)
- **Profile management** - Update name, email, and profile picture

## Quick Start with Docker

```bash
docker run -d \
  --name movies \
  -p 8080:80 \
  -e TMDB_API_KEY=your-api-key \
  -e DATABASE_MODE=mysql \
  -e DATABASE_MYSQL_HOST=your-mysql-host \
  -e DATABASE_MYSQL_NAME=movies \
  -e DATABASE_MYSQL_USER=movies \
  -e DATABASE_MYSQL_PASSWORD=your-password \
  -v movies_storage:/app/storage \
  ghcr.io/benjaminmue/movies:latest
```

Database migrations run automatically on container start.

### Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `TMDB_API_KEY` | Yes | [Get one here](https://www.themoviedb.org/settings/api) |
| `DATABASE_MODE` | No | `mysql` or `sqlite` (default: sqlite) |
| `DATABASE_MYSQL_HOST` | If mysql | MySQL hostname |
| `DATABASE_MYSQL_NAME` | If mysql | Database name |
| `DATABASE_MYSQL_USER` | If mysql | Database user |
| `DATABASE_MYSQL_PASSWORD` | If mysql | Database password |

## Docker Compose

```yaml
services:
  movies:
    image: ghcr.io/benjaminmue/movies:latest
    ports:
      - "8080:80"
    environment:
      TMDB_API_KEY: "your-api-key"
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "movies"
      DATABASE_MYSQL_USER: "movies"
      DATABASE_MYSQL_PASSWORD: "your-password"
    volumes:
      - movies_storage:/app/storage
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: "movies"
      MYSQL_USER: "movies"
      MYSQL_PASSWORD: "your-password"
      MYSQL_ROOT_PASSWORD: "root-password"
    volumes:
      - movies_db:/var/lib/mysql

volumes:
  movies_storage:
  movies_db:
```

## Image Tags

Pull from GitHub Container Registry:

```bash
# Latest stable
docker pull ghcr.io/benjaminmue/movies:latest

# Specific version
docker pull ghcr.io/benjaminmue/movies:v0.1.0-alpha.1

# Specific commit
docker pull ghcr.io/benjaminmue/movies:sha-abc1234
```

| Tag | Description |
|-----|-------------|
| `latest` | Latest stable release (recommended) |
| `main` | Latest build from main branch |
| `vX.Y.Z` | Specific version tag |
| `sha-XXXXXXX` | Specific commit |

> **Note:** GHCR package must be set to "Public" in repository settings for anonymous pulls.

## Development

```bash
# Install dependencies
composer install

# Start development environment
docker compose -f docker-compose.yml -f docker-compose.development.yml up -d

# Run migrations
docker compose exec app php bin/console.php database:migration:migrate

# Run tests
composer test
```

## Security

- Never commit secrets to the repository
- Configure all credentials via environment variables
- Rotate API keys immediately if leaked
- Use Docker secrets for sensitive values in production

## License

See [LICENSE](LICENSE) file.
