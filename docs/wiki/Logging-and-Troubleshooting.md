# Logging and Troubleshooting

This page covers Pathary's logging system and common troubleshooting scenarios.

## Logging Architecture

Pathary uses [Monolog](https://github.com/Seldaek/monolog) for structured logging with multiple output handlers.

### Log Handlers

**File**: `src/Factory.php`

```php
public static function createLogger(ContainerInterface $container, Config $config) : LoggerInterface
{
    $logger = new Logger('movary');

    // Always log to stdout (for Docker)
    $logger->pushHandler(self::createLoggerStreamHandlerStdout($container, $config));

    // Optionally log to file
    $enableFileLogging = $config->getAsBool('LOG_ENABLE_FILE_LOGGING', self::DEFAULT_ENABLE_FILE_LOGGING);
    if ($enableFileLogging === true) {
        $logger->pushHandler(self::createLoggerStreamHandlerFile($container, $config));
    }

    return $logger;
}
```

Output destinations:
| Handler | Destination | Default |
|---------|-------------|---------|
| Stdout | `php://stdout` | Always enabled |
| File | `storage/logs/app.log` | Enabled by default |

## Log Configuration

### Environment Variables

```env
# Log level: debug, info, notice, warning, error, critical, alert, emergency
LOG_LEVEL=warning

# Include stack traces in logs (useful for debugging)
LOG_ENABLE_STACKTRACE=0

# Write logs to file (in addition to stdout)
LOG_ENABLE_FILE_LOGGING=1
```

### Log Levels

| Level | Use Case |
|-------|----------|
| `debug` | Detailed debugging (very verbose) |
| `info` | Informational messages |
| `notice` | Normal but significant events |
| `warning` | Warning conditions (default) |
| `error` | Error conditions |
| `critical` | Critical conditions |
| `alert` | Action must be taken immediately |
| `emergency` | System is unusable |

### Log Format

**File**: `src/Factory.php`

```php
public static function createLineFormatter(ContainerInterface $container, Config $config) : LineFormatter
{
    $enableStackTrace = $config->getAsBool('LOG_ENABLE_STACKTRACE', self::DEFAULT_LOG_ENABLE_STACKTRACE);

    $formatter = new LineFormatter(null, null, true, true);
    $formatter->includeStacktraces($enableStackTrace);

    return $formatter;
}
```

Log format example:
```
[2025-12-14T15:30:00.000000+00:00] movary.EMERGENCY: Error message {"exception":"..."} []
```

## Log Locations

### Container Paths

| Path | Content |
|------|---------|
| `/app/storage/logs/app.log` | Application log file |
| Docker stdout | Container log stream |

### Volume Mount

```yaml
volumes:
  - pathary-storage:/app/storage
```

Logs are persisted in the `pathary-storage` volume under `logs/`.

## Viewing Logs

### Docker Container Logs (stdout)

```bash
# View all logs
docker logs pathary

# Follow logs in real-time
docker logs -f pathary

# Show last 100 lines
docker logs --tail 100 pathary

# Show logs since timestamp
docker logs --since "2025-12-14T10:00:00" pathary
```

### Application Log File

```bash
# View log file (inside container)
docker exec pathary cat /app/storage/logs/app.log

# Tail log file in real-time
docker exec pathary tail -f /app/storage/logs/app.log

# Search logs for errors
docker exec pathary grep -i error /app/storage/logs/app.log

# View last 50 lines
docker exec pathary tail -n 50 /app/storage/logs/app.log
```

### Docker Compose

```bash
# View logs for all services
docker compose logs

# Follow logs
docker compose logs -f

# View specific service
docker compose logs pathary
```

## Error Handling

### Global Exception Handler

**File**: `public/index.php`

```php
try {
    // Route handling...
} catch (Throwable $t) {
    $container->get(LoggerInterface::class)->emergency($t->getMessage(), ['exception' => $t]);

    if (str_starts_with($uri, '/api') === false) {
        $response = $container->get(ErrorController::class)->renderInternalServerError();
    }
}
```

All uncaught exceptions are:
1. Logged at EMERGENCY level with full exception context
2. Displayed as a 500 error page (web) or empty response (API)

### Error Pages

**File**: `src/HttpController/Web/ErrorController.php`

| Status | Template | Controller Method |
|--------|----------|-------------------|
| 404 | `templates/page/404.html.twig` | `renderNotFound()` |
| 500 | `templates/page/500.html.twig` | `renderInternalServerError()` |

## Common 500 Error Causes

### 1. Database Connection Failed

**Symptoms**: 500 error on all pages

**Log message**:
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solutions**:
```bash
# Check database container is running
docker ps | grep mysql

# Check database connection settings
docker exec pathary env | grep DATABASE

# Test MySQL connection
docker exec pathary-mysql mysql -u pathary -p -e "SELECT 1"
```

### 2. Migration Failed

**Symptoms**: 500 error after update

**Log message**:
```
Migration failed: Table 'x' doesn't exist
```

**Solutions**:
```bash
# Check migration status
docker exec pathary php bin/console.php database:migration:status

# Re-run migrations
docker exec pathary php bin/console.php database:migration:migrate

# Check for SQL errors in logs
docker logs pathary | grep -i migration
```

### 3. Missing TMDB API Key

**Symptoms**: 500 error on search or movie pages

**Log message**:
```
TMDB API key not configured
```

**Solutions**:
```bash
# Verify environment variable
docker exec pathary env | grep TMDB

# Check .env file
cat .env | grep TMDB_API_KEY
```

### 4. Permission Errors

**Symptoms**: 500 error on file operations

**Log message**:
```
Permission denied: /app/storage/...
```

**Solutions**:
```bash
# Fix storage permissions
docker exec pathary chown -R www-data:www-data /app/storage
docker exec pathary chmod -R 755 /app/storage
```

### 5. Memory Exhausted

**Symptoms**: 500 error on large operations

**Log message**:
```
Allowed memory size of X bytes exhausted
```

**Solutions**:
```bash
# Increase PHP memory limit
docker exec pathary php -d memory_limit=512M bin/console.php ...
```

## Debugging Techniques

### Enable Debug Logging

```env
LOG_LEVEL=debug
LOG_ENABLE_STACKTRACE=1
```

Then restart the container:
```bash
docker compose restart pathary
```

### Check PHP Errors

```bash
# View PHP-FPM error log
docker exec pathary cat /var/log/php-fpm-error.log

# Check Nginx error log
docker exec pathary cat /var/log/nginx/error.log
```

### Interactive Shell

```bash
# Get shell access to container
docker exec -it pathary /bin/sh

# Run PHP commands
docker exec -it pathary php -a
```

### Test Database Query

```bash
# MySQL
docker exec pathary-mysql mysql -u pathary -p pathary -e "SELECT COUNT(*) FROM movie"

# SQLite
docker exec pathary sqlite3 /app/storage/movary.sqlite "SELECT COUNT(*) FROM movie"
```

## Troubleshooting Checklist

### Container Won't Start

1. Check logs: `docker logs pathary`
2. Verify environment variables in `.env`
3. Check volume permissions
4. Ensure port 80 is available

### Blank White Page

1. Check `LOG_LEVEL=debug` is set
2. View logs: `docker logs pathary`
3. Check PHP-FPM is running: `docker exec pathary ps aux | grep php`
4. Verify Nginx is running: `docker exec pathary ps aux | grep nginx`

### Authentication Issues

1. Clear browser cookies
2. Check `user_auth_token` table
3. Verify session cookie configuration
4. Check for HTTPS/HTTP mismatch with `APPLICATION_URL`

### Image Not Loading

1. Check TMDB API key is valid
2. Verify image caching setting: `TMDB_ENABLE_IMAGE_CACHING`
3. Check storage permissions on `/app/storage/images`

### Slow Performance

1. Enable MySQL (SQLite is slower for large datasets)
2. Check for N+1 queries in logs
3. Monitor container resources: `docker stats pathary`

## Health Check

### Basic Health Test

```bash
# Check if web server responds
curl -f http://localhost:8080/ || echo "FAILED"

# Check container health
docker inspect pathary --format='{{.State.Health.Status}}'
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

## Log Rotation

Application logs can grow large. Implement rotation:

### Logrotate Configuration

```bash
# /etc/logrotate.d/pathary
/app/storage/logs/*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
    copytruncate
}
```

### Manual Cleanup

```bash
# Truncate log file
docker exec pathary truncate -s 0 /app/storage/logs/app.log

# Remove old logs
docker exec pathary find /app/storage/logs -name "*.log" -mtime +7 -delete
```

## Related Pages

- [Deployment](Deployment.md) - Production setup
- [Getting Started](Getting-Started.md) - Initial configuration
- [Database](Database.md) - Database troubleshooting
- [Migrations](Migrations.md) - Migration issues

---

[← Back to Wiki Home](README.md)
