# Deployment

This page covers deploying Pathary in production environments.

## Docker Deployment

### Official Image

```bash
docker pull ghcr.io/benjaminmue/pathary:latest
```

### Basic Docker Run

```bash
docker run -d \
  --name pathary \
  -p 80:80 \
  -e TMDB_API_KEY=your_key \
  -v pathary-storage:/app/storage \
  ghcr.io/benjaminmue/pathary:latest
```

### Docker Compose (Production)

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      - APPLICATION_URL=https://pathary.example.com
      - TMDB_API_KEY=${TMDB_API_KEY}
      - DATABASE_MODE=mysql
      - DATABASE_MYSQL_HOST=mysql
      - DATABASE_MYSQL_NAME=pathary
      - DATABASE_MYSQL_USER=pathary
      - DATABASE_MYSQL_PASSWORD=${MYSQL_PASSWORD}
      - LOG_LEVEL=warning
    volumes:
      - pathary-storage:/app/storage
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      - MYSQL_DATABASE=pathary
      - MYSQL_USER=pathary
      - MYSQL_PASSWORD=${MYSQL_PASSWORD}
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql

volumes:
  pathary-storage:
  mysql-data:
```

## Building Locally

### Build Docker Image

```bash
# From repository root
docker build -t pathary:local -f Dockerfile .
```

### Development Build

```bash
docker build --target development -t pathary:dev -f build/Dockerfile .
```

## Container Startup

**File**: `build/scripts/entrypoint.sh`

On container start:
1. Runs database migrations (unless disabled)
2. Creates storage symlink
3. Starts PHP-FPM + Nginx

### Disable Auto-Migration

```env
DATABASE_DISABLE_AUTO_MIGRATION=true
```

## Reverse Proxy Configuration

### Required Headers

Your reverse proxy must forward these headers:

| Header | Purpose |
|--------|---------|
| `Host` | Original host |
| `X-Real-IP` | Client IP |
| `X-Forwarded-For` | Proxy chain |
| `X-Forwarded-Proto` | Original protocol (http/https) |
| `X-Forwarded-Host` | Original host |

### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name pathary.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://pathary:80;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;

        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

### Traefik Configuration

```yaml
services:
  pathary:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.pathary.rule=Host(`pathary.example.com`)"
      - "traefik.http.routers.pathary.entrypoints=websecure"
      - "traefik.http.routers.pathary.tls=true"
      - "traefik.http.services.pathary.loadbalancer.server.port=80"
```

### Caddy Configuration

```
pathary.example.com {
    reverse_proxy pathary:80
}
```

Caddy automatically handles headers and SSL.

## Environment Variables

### Production Essentials

```env
# Required
TMDB_API_KEY=your_key

# Recommended
APPLICATION_URL=https://pathary.example.com
DATABASE_MODE=mysql
DATABASE_MYSQL_HOST=mysql
DATABASE_MYSQL_NAME=pathary
DATABASE_MYSQL_USER=pathary
DATABASE_MYSQL_PASSWORD=strong_password

# Logging
LOG_LEVEL=warning
LOG_ENABLE_FILE_LOGGING=1
```

### Security Notes

- Never commit `.env` files to git
- Use Docker secrets or environment variables for passwords
- Change default database credentials

## Volume Mounts

| Path | Purpose |
|------|---------|
| `/app/storage` | SQLite database, logs, cached images |
| `/app/storage/logs` | Application logs |
| `/app/storage/images` | Cached TMDB images |

### Backup Storage

```bash
docker run --rm \
  -v pathary-storage:/source:ro \
  -v $(pwd):/backup \
  alpine tar czf /backup/pathary-storage.tar.gz -C /source .
```

## Health Checks

### Basic Health Check

```bash
curl -f http://localhost:8080/ || exit 1
```

### Docker Compose Health Check

```yaml
services:
  pathary:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## Logs

### View Logs

```bash
# Docker logs
docker logs pathary

# Application logs (inside container)
docker exec pathary cat /app/storage/logs/movary.log
```

### Log Rotation

Application logs are written to `/app/storage/logs/`. Implement log rotation:

```bash
# Logrotate config
/app/storage/logs/*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```

## Unraid Deployment

### Community Applications

Search for "Pathary" in Unraid Community Applications (if available).

### Manual Template

```xml
<?xml version="1.0"?>
<Container version="2">
  <Name>pathary</Name>
  <Repository>ghcr.io/benjaminmue/pathary:latest</Repository>
  <Network>bridge</Network>
  <Privileged>false</Privileged>
  <Config Name="Web UI" Target="80" Default="8080" Mode="tcp" Description="Web interface" Type="Port" Display="always" Required="true" Mask="false">8080</Config>
  <Config Name="TMDB API Key" Target="TMDB_API_KEY" Default="" Mode="" Description="TMDB API key" Type="Variable" Display="always" Required="true" Mask="true"/>
  <Config Name="Storage" Target="/app/storage" Default="/mnt/user/appdata/pathary" Mode="rw" Description="Application data" Type="Path" Display="always" Required="true" Mask="false"/>
</Container>
```

## Updates

### Update Docker Image

```bash
docker compose pull
docker compose up -d
```

### Check Version

The application version is displayed in Settings → App.

## Troubleshooting Deployment

### Container Won't Start

```bash
# Check logs
docker logs pathary

# Common issues:
# - Missing TMDB_API_KEY
# - Database connection failed
# - Migration error
```

### 502 Bad Gateway

- Check if container is running: `docker ps`
- Check proxy headers are correct
- Verify network connectivity between proxy and container

### HTTPS Issues

Ensure `APPLICATION_URL` includes `https://` and your proxy forwards `X-Forwarded-Proto: https`.

### Permission Errors

```bash
# Fix storage permissions
docker exec pathary chown -R www-data:www-data /app/storage
docker exec pathary chmod -R 755 /app/storage
```

## Related Pages

- [Getting Started](Getting-Started)] - Initial setup
- [Logging and Troubleshooting](Logging-and-Troubleshooting)] - Debugging
- [Migrations](Migrations)] - Database updates

---

[← Back to Wiki Home](Home)
