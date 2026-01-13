# Production Deployment Guide

This guide covers deploying Pathary in production environments with HTTPS, reverse proxies, monitoring, and security best practices.

!!! info "Prerequisites"
    Before deploying to production:

    1. Complete basic installation using the [Quickstart Guide](quickstart.md) or [Docker Installation](install/docker.md)
    2. Test functionality locally
    3. Prepare production domain and SSL certificates

## Production Checklist

Before going live, ensure you have:

- [ ] **Domain configured** with DNS pointing to your server
- [ ] **SSL/TLS certificates** obtained (Let's Encrypt, commercial CA, or self-signed for testing)
- [ ] **Reverse proxy** configured (Nginx, Traefik, or Caddy)
- [ ] **DATABASE_MODE=mysql** for production (SQLite OK for small deployments)
- [ ] **Strong passwords** for database and admin accounts
- [ ] **TMDB_API_KEY** configured
- [ ] **APPLICATION_URL** set to your production domain (with `https://`)
- [ ] **Backup strategy** in place
- [ ] **Monitoring** configured (optional but recommended)

## Production Docker Compose

### Basic Production Setup

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    container_name: pathary
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:80"  # Only expose to localhost (reverse proxy handles external)
    environment:
      # Required
      TMDB_API_KEY: ${TMDB_API_KEY}
      APPLICATION_URL: "https://pathary.example.com"

      # Database
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary"
      DATABASE_MYSQL_PASSWORD: ${DATABASE_MYSQL_PASSWORD}

      # Production logging
      LOG_LEVEL: "warning"
      LOG_ENABLE_FILE_LOGGING: "1"

      # Optional performance
      TMDB_ENABLE_IMAGE_CACHING: "1"
    volumes:
      - pathary-storage:/app/storage
    depends_on:
      mysql:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  mysql:
    image: mysql:8.0
    container_name: pathary-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary"
      MYSQL_PASSWORD: ${DATABASE_MYSQL_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  pathary-storage:
  mysql-data:
```

**Environment file** (`.env` - keep this secure):
```env
TMDB_API_KEY=your_actual_tmdb_api_key
DATABASE_MYSQL_PASSWORD=strong_random_password_here
MYSQL_ROOT_PASSWORD=different_strong_password
```

!!! warning "Security"
    - Never commit `.env` files to version control
    - Use strong random passwords (min 32 characters)
    - Restrict file permissions: `chmod 600 .env`

### With Docker Secrets (Recommended)

For enhanced security, use Docker secrets:

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    container_name: pathary
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:80"
    environment:
      TMDB_API_KEY_FILE: /run/secrets/tmdb_api_key
      APPLICATION_URL: "https://pathary.example.com"
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary"
      DATABASE_MYSQL_PASSWORD_FILE: /run/secrets/database_password
      LOG_LEVEL: "warning"
    volumes:
      - pathary-storage:/app/storage
    secrets:
      - tmdb_api_key
      - database_password
    depends_on:
      mysql:
        condition: service_healthy

  mysql:
    image: mysql:8.0
    container_name: pathary-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary"
      MYSQL_PASSWORD_FILE: /run/secrets/database_password
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/mysql_root_password
    volumes:
      - mysql-data:/var/lib/mysql
    secrets:
      - database_password
      - mysql_root_password

secrets:
  tmdb_api_key:
    file: ./secrets/tmdb_api_key.txt
  database_password:
    file: ./secrets/database_password.txt
  mysql_root_password:
    file: ./secrets/mysql_root_password.txt

volumes:
  pathary-storage:
  mysql-data:
```

## Reverse Proxy Configuration

Pathary should run behind a reverse proxy that handles HTTPS/SSL termination.

### Required Proxy Headers

Your reverse proxy **must** forward these headers for Pathary to work correctly:

| Header | Purpose | Example |
|--------|---------|---------|
| `Host` | Original hostname | `pathary.example.com` |
| `X-Real-IP` | Client IP address | `203.0.113.1` |
| `X-Forwarded-For` | Proxy chain IPs | `203.0.113.1, 198.51.100.1` |
| `X-Forwarded-Proto` | Original protocol | `https` |
| `X-Forwarded-Host` | Original host | `pathary.example.com` |

!!! warning "HTTPS Detection"
    Pathary detects HTTPS via `X-Forwarded-Proto: https` header. Without this header, redirects and cookie security flags will fail.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name pathary.example.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/pathary.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pathary.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    # Proxy Configuration
    location / {
        proxy_pass http://127.0.0.1:8080;

        # Required proxy headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;

        # Buffering
        proxy_buffering off;
        proxy_request_buffering off;
    }

    # Optional: Increase max body size for image uploads
    client_max_body_size 10M;
}

# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name pathary.example.com;

    return 301 https://$server_name$request_uri;
}
```

### Traefik (Docker Labels)

```yaml
services:
  pathary:
    image: ghcr.io/benjaminmue/pathary:latest
    restart: unless-stopped
    environment:
      APPLICATION_URL: "https://pathary.example.com"
      # ... other environment variables
    networks:
      - traefik-public
    labels:
      - "traefik.enable=true"

      # HTTP router (redirects to HTTPS)
      - "traefik.http.routers.pathary-http.rule=Host(`pathary.example.com`)"
      - "traefik.http.routers.pathary-http.entrypoints=web"
      - "traefik.http.routers.pathary-http.middlewares=pathary-https-redirect"

      # HTTPS router
      - "traefik.http.routers.pathary.rule=Host(`pathary.example.com`)"
      - "traefik.http.routers.pathary.entrypoints=websecure"
      - "traefik.http.routers.pathary.tls=true"
      - "traefik.http.routers.pathary.tls.certresolver=letsencrypt"

      # Service
      - "traefik.http.services.pathary.loadbalancer.server.port=80"

      # Middlewares
      - "traefik.http.middlewares.pathary-https-redirect.redirectscheme.scheme=https"
      - "traefik.http.middlewares.pathary-https-redirect.redirectscheme.permanent=true"

networks:
  traefik-public:
    external: true
```

### Caddy

Caddy automatically handles HTTPS with Let's Encrypt:

```
pathary.example.com {
    reverse_proxy pathary:80
}
```

That's it! Caddy handles:
- Automatic HTTPS with Let's Encrypt
- HTTP to HTTPS redirect
- Required proxy headers
- Certificate renewal

## SSL/TLS Certificates

### Let's Encrypt (Free, Recommended)

**With Certbot (Nginx/Apache)**:
```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d pathary.example.com

# Auto-renewal is configured automatically
```

**With Traefik** (automatic):
```yaml
# Traefik handles Let's Encrypt automatically when using tls.certresolver
labels:
  - "traefik.http.routers.pathary.tls.certresolver=letsencrypt"
```

**With Caddy** (automatic):
```
# Caddy obtains and renews certificates automatically
pathary.example.com {
    reverse_proxy pathary:80
}
```

### Commercial CA

Upload your certificate files and configure your reverse proxy to use them (see Nginx example above).

### Self-Signed (Testing Only)

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/pathary-selfsigned.key \
  -out /etc/ssl/certs/pathary-selfsigned.crt \
  -subj "/CN=pathary.example.com"
```

!!! danger "Self-Signed Certificates"
    Only use self-signed certificates for testing. Browsers will show security warnings.

## Monitoring and Health Checks

### Built-in Health Endpoint

Pathary exposes a health check endpoint at `/health`:

```bash
curl -f https://pathary.example.com/health
```

### Uptime Monitoring

Use external monitoring services:
- **Uptime Robot** (free tier available)
- **Pingdom**
- **StatusCake**
- **UptimeRobot**

Configure them to check `https://pathary.example.com/health` every 5 minutes.

### Docker Health Checks

Add to your `docker-compose.yml`:

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

Check health status:
```bash
docker ps  # Shows (healthy) or (unhealthy)
docker inspect pathary | grep -A 10 Health
```

### Log Monitoring

Monitor application logs for errors:

```bash
# View recent logs
docker compose logs --tail=100 -f pathary

# Monitor error patterns
docker compose logs pathary | grep -i error

# Inside container
docker exec pathary tail -f /app/storage/logs/pathary.log
```

## Backup Strategy

### Database Backups (MySQL)

**Automated daily backup**:
```bash
#!/bin/bash
# backup-pathary-db.sh

BACKUP_DIR="/backups/pathary"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

docker exec pathary-mysql mysqldump \
  -u pathary \
  -p"$DATABASE_PASSWORD" \
  pathary \
  | gzip > "$BACKUP_DIR/pathary-db-$DATE.sql.gz"

# Keep only last 30 days
find "$BACKUP_DIR" -name "pathary-db-*.sql.gz" -mtime +30 -delete
```

**Schedule with cron**:
```bash
# Run daily at 2 AM
0 2 * * * /path/to/backup-pathary-db.sh
```

### Storage Volume Backup

Backup the entire storage volume (includes SQLite DB if used, logs, images):

```bash
#!/bin/bash
# backup-pathary-storage.sh

BACKUP_DIR="/backups/pathary"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

docker run --rm \
  -v pathary-storage:/source:ro \
  -v "$BACKUP_DIR":/backup \
  alpine \
  tar czf "/backup/pathary-storage-$DATE.tar.gz" -C /source .

# Keep only last 30 days
find "$BACKUP_DIR" -name "pathary-storage-*.tar.gz" -mtime +30 -delete
```

### Restore from Backup

**Restore MySQL database**:
```bash
# Stop Pathary
docker compose stop pathary

# Restore database
gunzip < pathary-db-20260113.sql.gz | \
  docker exec -i pathary-mysql mysql -u pathary -p"$DATABASE_PASSWORD" pathary

# Start Pathary
docker compose start pathary
```

**Restore storage volume**:
```bash
# Stop Pathary
docker compose stop pathary

# Restore storage
docker run --rm \
  -v pathary-storage:/target \
  -v /backups/pathary:/backup \
  alpine \
  tar xzf /backup/pathary-storage-20260113.tar.gz -C /target

# Start Pathary
docker compose start pathary
```

## Updates and Maintenance

### Update Pathary

```bash
# Pull latest image
docker compose pull pathary

# Recreate container with new image
docker compose up -d pathary

# Migrations run automatically on startup
```

!!! info "Downtime During Updates"
    Expect 30-60 seconds of downtime during updates while the container restarts and runs migrations.

### Check Current Version

Visit Settings → About in the Pathary web interface to see the current version.

### Rollback to Previous Version

```bash
# Use a specific version tag
docker compose pull ghcr.io/benjaminmue/pathary:v1.0.0

# Update compose file to pin version
# image: ghcr.io/benjaminmue/pathary:v1.0.0

# Recreate container
docker compose up -d pathary
```

### Database Migrations

Migrations run automatically by default. To run manually:

```bash
# Check migration status
docker compose exec pathary php bin/console.php database:migration:status

# Run pending migrations
docker compose exec pathary php bin/console.php database:migration:migrate

# Rollback last migration (if needed)
docker compose exec pathary php bin/console.php database:migration:rollback
```

## Security Hardening

### Environment Variables

- ✅ Use strong random passwords (min 32 characters)
- ✅ Use Docker secrets for sensitive data
- ✅ Never commit `.env` files to git
- ✅ Restrict `.env` file permissions: `chmod 600 .env`

### Network Security

- ✅ Only expose Pathary to `127.0.0.1` (reverse proxy handles external traffic)
- ✅ Use firewall rules to restrict access
- ✅ Keep Docker and system packages updated

### Application Security

- ✅ Enable 2FA for all admin accounts
- ✅ Use strong passwords (enforced by password policy)
- ✅ Keep Pathary updated to latest version
- ✅ Monitor logs for suspicious activity

### Reverse Proxy Security Headers

Add to Nginx configuration:
```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

## Troubleshooting Production Issues

### 502 Bad Gateway

**Causes**:
- Pathary container is not running
- Reverse proxy can't reach Pathary
- Wrong port configuration

**Solutions**:
```bash
# Check if container is running
docker ps | grep pathary

# Check container logs
docker compose logs pathary

# Verify network connectivity
docker exec nginx ping pathary

# Check port binding
docker port pathary
```

### HTTPS Not Working

**Causes**:
- `X-Forwarded-Proto` header not set
- `APPLICATION_URL` doesn't include `https://`
- SSL certificate issues

**Solutions**:
```bash
# Verify APPLICATION_URL
docker exec pathary env | grep APPLICATION_URL

# Check proxy headers (from inside Pathary container)
docker exec pathary curl -I http://localhost/

# Test SSL certificate
openssl s_client -connect pathary.example.com:443
```

### Session/Login Issues

**Causes**:
- `APPLICATION_URL` mismatch
- Cookie domain/secure flag issues
- Missing proxy headers

**Solutions**:
- Ensure `APPLICATION_URL` matches your actual domain
- Verify `X-Forwarded-Proto: https` is being forwarded
- Clear browser cookies and try again

### Database Connection Errors

```bash
# Check MySQL is running
docker compose ps mysql

# Test connection from Pathary
docker compose exec pathary php bin/console.php database:migration:status

# Check MySQL logs
docker compose logs mysql
```

## Related Documentation

- **[Quickstart Guide](quickstart.md)** - Initial installation
- **[Docker Installation](install/docker.md)** - Detailed Docker setup
- **[Configuration Reference](configuration.md)** - All environment variables
- **[Operations Guide](operations/logging-and-troubleshooting.md)** - Logging and debugging
