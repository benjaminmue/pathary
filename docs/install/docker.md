# Docker Installation Reference

This page provides detailed information about installing Pathary using the official Docker image. For a quick 5-minute setup, see the [Quickstart Guide](../quickstart.md).

## Official Docker Image

Pathary is distributed as an official Docker image via GitHub Container Registry:

```bash
docker pull ghcr.io/benjaminmue/pathary:latest
```

**Image Registry**: [GitHub Container Registry](https://github.com/benjaminmue/pathary/pkgs/container/pathary)

## Image Tags

| Tag | Description | Recommended For |
|-----|-------------|-----------------|
| `latest` | Latest stable release | Production use (recommended) |
| `main` | Latest build from main branch | Testing new features |
| `vX.Y.Z` | Specific version (e.g., `v1.2.3`) | Version pinning in production |
| `sha-XXXXXXX` | Specific commit build | Advanced users, debugging |

**Example using specific version:**
```bash
docker pull ghcr.io/benjaminmue/pathary:v1.0.0
```

## Database Migrations

!!! warning "Database Migrations Required"
    After **initial installation** and every update containing database changes, database migrations must be executed:

    ```bash
    php bin/console.php database:migration:migrate
    ```

    Missing database migrations can cause critical errors!

!!! info "Automatic Migrations"
    The Docker image automatically runs missing database migrations on startup. To disable this behavior, set:

    ```bash
    DATABASE_DISABLE_AUTO_MIGRATION=1
    ```

## Storage Directory

The `/app/storage` directory stores all persistent data:

- Application logs
- Cached images (if TMDB caching enabled)
- SQLite database (if using SQLite mode)
- Uploaded files

**Requirements:**
- Must be persisted outside the container
- Requires read/write access
- Should use Docker volumes (recommended) or bind mounts

!!! warning "Bind Mount Permissions"
    If using bind mounts instead of Docker volumes, ensure:

    1. The directory exists before starting the container
    2. Correct permissions/ownership are set
    3. Match container UID/GID (default: 3000:3000)

## Docker Secrets Support

All environment variables support Docker secrets by appending `_FILE` suffix:

```yaml
environment:
  TMDB_API_KEY_FILE: /run/secrets/tmdb_key
  DATABASE_MYSQL_PASSWORD_FILE: /run/secrets/mysql_password
```

**How it works:**
- Secrets are used as fallback when environment variable is not set
- Do NOT set both `VARIABLE` and `VARIABLE_FILE` (file takes precedence)
- See [official Docker secrets documentation](https://docs.docker.com/engine/swarm/secrets/)

## Installation Examples

### Basic Docker Run (SQLite)

Simplest setup for quick testing or small deployments:

```bash
docker volume create pathary-storage

docker run -d \
  --name pathary \
  -p 80:80 \
  -e TMDB_API_KEY="your_api_key_here" \
  -e DATABASE_MODE="sqlite" \
  -v pathary-storage:/app/storage \
  ghcr.io/benjaminmue/pathary:latest
```

Access at: `http://localhost/`

### Basic Docker Run (MySQL)

For production use with external MySQL:

```bash
docker volume create pathary-storage

docker run -d \
  --name pathary \
  -p 80:80 \
  -e TMDB_API_KEY="your_api_key_here" \
  -e DATABASE_MODE="mysql" \
  -e DATABASE_MYSQL_HOST="mysql.example.com" \
  -e DATABASE_MYSQL_NAME="pathary" \
  -e DATABASE_MYSQL_USER="pathary_user" \
  -e DATABASE_MYSQL_PASSWORD="secure_password" \
  -v pathary-storage:/app/storage \
  ghcr.io/benjaminmue/pathary:latest
```

### Docker Compose (SQLite)

Simple single-container setup:

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    container_name: pathary
    restart: unless-stopped
    ports:
      - "80:80"
    environment:
      TMDB_API_KEY: "your_api_key_here"
      APPLICATION_URL: "http://localhost"
      DATABASE_MODE: "sqlite"
    volumes:
      - pathary-storage:/app/storage

volumes:
  pathary-storage:
```

### Docker Compose (MySQL)

Multi-container setup with MySQL:

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    container_name: pathary
    restart: unless-stopped
    ports:
      - "80:80"
    environment:
      TMDB_API_KEY: "your_api_key_here"
      APPLICATION_URL: "http://localhost"
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary_user"
      DATABASE_MYSQL_PASSWORD: "pathary_password"
    volumes:
      - pathary-storage:/app/storage
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    container_name: pathary-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary_user"
      MYSQL_PASSWORD: "pathary_password"
      MYSQL_ROOT_PASSWORD: "mysql_root_password"
    volumes:
      - pathary-db:/var/lib/mysql

volumes:
  pathary-storage:
  pathary-db:
```

### Docker Compose (MySQL with Secrets)

Production setup using Docker secrets for sensitive data:

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    container_name: pathary
    restart: unless-stopped
    ports:
      - "80:80"
    environment:
      TMDB_API_KEY_FILE: /run/secrets/tmdb_key
      APPLICATION_URL: "https://pathary.example.com"
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary_user"
      DATABASE_MYSQL_PASSWORD_FILE: /run/secrets/mysql_password
    volumes:
      - pathary-storage:/app/storage
    secrets:
      - tmdb_key
      - mysql_password
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    container_name: pathary-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary_user"
      MYSQL_PASSWORD_FILE: /run/secrets/mysql_password
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/mysql_root_password
    volumes:
      - pathary-db:/var/lib/mysql
    secrets:
      - mysql_root_password
      - mysql_password

secrets:
  mysql_root_password:
    file: ./secrets/mysql_root_password.txt
  mysql_password:
    file: ./secrets/mysql_password.txt
  tmdb_key:
    file: ./secrets/tmdb_key.txt

volumes:
  pathary-storage:
  pathary-db:
```

**Secret files format** (plain text, no newline):
```bash
# Create secrets directory
mkdir -p secrets

# Create secret files (replace with actual values)
echo -n "your_tmdb_api_key" > secrets/tmdb_key.txt
echo -n "pathary_db_password" > secrets/mysql_password.txt
echo -n "mysql_root_password" > secrets/mysql_root_password.txt

# Secure the secrets
chmod 600 secrets/*.txt
```

## Advanced Configuration

### Custom Port Mapping

To run on a different port (e.g., 8080):

```yaml
services:
  pathary:
    ports:
      - "8080:80"  # Host port 8080 → Container port 80
    environment:
      APPLICATION_URL: "http://localhost:8080"
```

!!! warning "Update APPLICATION_URL"
    Always update `APPLICATION_URL` to match your port configuration, or redirects will fail.

### Custom User/Group ID

To match file ownership with your host system:

```yaml
services:
  pathary:
    environment:
      USER_ID: 1000
      GROUP_ID: 1000
```

Default is `3000:3000`. Useful when using bind mounts.

### Bind Mount Instead of Volume

```yaml
services:
  pathary:
    volumes:
      - ./pathary-storage:/app/storage  # Bind mount to local directory
```

!!! warning "Permissions Required"
    Ensure the local directory exists and is writable:

    ```bash
    mkdir -p pathary-storage
    chown -R 3000:3000 pathary-storage  # Match container UID/GID
    chmod -R 755 pathary-storage
    ```

### Health Checks

Add health check to your compose file:

```yaml
services:
  pathary:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

## After Installation

Once the container is running:

1. **Access the setup wizard**: Navigate to `http://localhost/` (or your configured URL)
2. **Complete first-time setup**: Follow the [First-Time Setup Guide](../first-time-setup.md)
3. **Configure settings**: See [Configuration Reference](../configuration.md)

## Updating Pathary

### Pull Latest Image

```bash
docker pull ghcr.io/benjaminmue/pathary:latest
```

### Using Docker Compose

```bash
# Pull new image
docker compose pull pathary

# Recreate container with new image
docker compose up -d pathary
```

!!! info "Migrations Run Automatically"
    Database migrations run automatically on container startup (unless disabled).

### Manual Migration

If auto-migration is disabled:

```bash
docker compose exec pathary php bin/console.php database:migration:migrate
```

## Troubleshooting

### Container Won't Start

Check logs:
```bash
docker logs pathary
# or with docker compose:
docker compose logs pathary
```

Common issues:
- Missing required environment variables
- Port already in use
- Storage volume permission errors

### Database Connection Errors

For MySQL:
```bash
# Verify MySQL is running
docker compose ps mysql

# Check MySQL logs
docker compose logs mysql

# Test connection from app container
docker compose exec pathary php bin/console.php database:migration:status
```

### Storage Permission Errors

```bash
# Fix permissions inside container
docker compose exec pathary chmod -R 777 /app/storage

# Or match host UID/GID
docker compose exec pathary chown -R 3000:3000 /app/storage
```

## Next Steps

- **[First-Time Setup](../first-time-setup.md)** - Complete the /init wizard
- **[Configuration](../configuration.md)** - Environment variables reference
- **[Production Deployment](../deployment.md)** - Reverse proxy, HTTPS, and production setup
- **[Manual Installation](manual.md)** - Install without Docker
