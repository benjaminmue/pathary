# Getting Started

This guide covers how to set up and run Pathary locally.

## Requirements

- **Docker** and **Docker Compose** (recommended)
- **TMDB API Key** - Required for movie search ([Get one here](https://www.themoviedb.org/settings/api))

For manual installation:
- PHP 8.2+
- MySQL 8.0+ or SQLite 3
- Composer

## Quick Start with Docker

### 1. Clone the Repository

```bash
git clone https://github.com/benjaminmue/pathary.git
cd pathary
```

### 2. Create Local Environment File

**IMPORTANT**: For local development, use `.env.local` instead of modifying `.env`:

```bash
cat > .env.local << 'EOF'
TMDB_API_KEY=your_actual_key_here
HTTP_PORT=80
APPLICATION_URL=http://localhost
EOF
```

**Why `.env.local`?**
- `.env.local` is in `.gitignore` and will never be committed
- Keeps your API keys and local config safe from accidental commits
- `.env` remains as the example/template file

### 3. Start the Application

For local development with MySQL:

```bash
docker compose --env-file .env.local \
  -f docker-compose.yml \
  -f docker-compose.development.yml \
  -f docker-compose.mysql.yml \
  up -d
```

For production deployment, use your deployment-specific env file or environment variables.

The application will be available at **`http://localhost/`** (port 80).

**Port Note**: The app must be reachable on port 80 for proper routing. The docker-compose configuration maps host port 80 to container port 8080.

### 4. First User Creation

On first launch with no users in the database:
1. Navigate to `http://localhost`
2. You'll be redirected to `/landing` (first-run page)
3. Click to create your first admin user
4. Fill in username, email, and password

**Code path**: `src/HttpController/Web/LandingPageController.php`

## Environment Variables

### Required

| Variable | Description |
|----------|-------------|
| `TMDB_API_KEY` | Your TMDB API key for movie data |

### Optional - General

| Variable | Default | Description |
|----------|---------|-------------|
| `APPLICATION_URL` | - | Public URL (e.g., `https://pathary.example.com`) |
| `APPLICATION_NAME` | `Pathary` | Displayed in navbar and emails |
| `TIMEZONE` | `UTC` | PHP timezone ([list](https://www.php.net/manual/en/timezones.php)) |

### Optional - Database

| Variable | Default | Description |
|----------|---------|-------------|
| `DATABASE_MODE` | `sqlite` | `sqlite` or `mysql` |
| `DATABASE_SQLITE` | `storage/movary.sqlite` | SQLite file path |
| `DATABASE_MYSQL_HOST` | - | MySQL host |
| `DATABASE_MYSQL_PORT` | `3306` | MySQL port |
| `DATABASE_MYSQL_NAME` | - | Database name |
| `DATABASE_MYSQL_USER` | - | Database user |
| `DATABASE_MYSQL_PASSWORD` | - | Database password |
| `DATABASE_MYSQL_CHARSET` | `utf8mb4` | Character set |

### Optional - Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_LEVEL` | `warning` | RFC 5424 level (debug, info, warning, error) |
| `LOG_ENABLE_STACKTRACE` | `0` | Include stack traces in logs |
| `LOG_ENABLE_FILE_LOGGING` | `1` | Write logs to `storage/logs/` |

### Optional - Docker

| Variable | Default | Description |
|----------|---------|-------------|
| `HTTP_PORT` | `80` | Web server port |
| `USER_ID` | `3000` | Container user ID |
| `GROUP_ID` | `3000` | Container group ID |

## Docker Compose Examples

### SQLite (Simple Setup)

```yaml
services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    ports:
      - "80:80"
    environment:
      - TMDB_API_KEY=your_key_here
    volumes:
      - pathary-storage:/app/storage

volumes:
  pathary-storage:
```

### MySQL (Production Setup)

```yaml
services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    ports:
      - "80:80"
    environment:
      - TMDB_API_KEY=your_key_here
      - DATABASE_MODE=mysql
      - DATABASE_MYSQL_HOST=mysql
      - DATABASE_MYSQL_NAME=pathary
      - DATABASE_MYSQL_USER=pathary
      - DATABASE_MYSQL_PASSWORD=secret
    volumes:
      - pathary-storage:/app/storage
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    environment:
      - MYSQL_DATABASE=pathary
      - MYSQL_USER=pathary
      - MYSQL_PASSWORD=secret
      - MYSQL_ROOT_PASSWORD=rootsecret
    volumes:
      - mysql-data:/var/lib/mysql

volumes:
  pathary-storage:
  mysql-data:
```

## Common Startup Issues

### "TMDB_API_KEY not set"

Set the `TMDB_API_KEY` environment variable:
```bash
export TMDB_API_KEY=your_key
docker compose up -d
```

### Database Migration Fails

Check database connectivity. For MySQL, ensure the database server is ready:
```bash
docker compose logs pathary
```

Migrations retry 5 times with 5-second delays (see `build/scripts/entrypoint.sh`).

### Permission Denied on Storage

Ensure the storage volume is writable:
```bash
docker exec pathary-app chmod -R 777 /app/storage
```

Or set matching `USER_ID`/`GROUP_ID` in your compose file.

### Port Already in Use

If port 80 is already in use on your host, you can map to a different host port:

```yaml
ports:
  - "8080:8080"  # Use port 8080 on host
environment:
  - HTTP_PORT=8080
  - APPLICATION_URL=http://localhost:8080
```

**Important**: Update `APPLICATION_URL` to match your chosen port to ensure internal redirects work correctly.

## Next Steps

- [Architecture](Architecture)] - Understand the codebase structure
- [Authentication](Authentication-and-Sessions)] - Learn about login and sessions
- [Deployment](Deployment)] - Production deployment guide

---

[← Back to Wiki Home](Home)
