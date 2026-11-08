# Automated Sync Setup

## Overview

Pathary automatically syncs TMDB metadata and OMDb ratings daily **inside the Docker container** - no external cron setup required!

**How it works:**
- Cron daemon runs inside the container, jobs are defined in `/etc/cron.d/pathary-sync` and run as `www-data`
- Syncs daily at 2 AM (container timezone)
- Also runs `maintenance:prune` daily at 3:30 AM and `oauth:monitor` every 6 hours
- Job output appears in `docker logs` and in `/app/storage/logs/cron.log`
- Only syncs movies not updated in 7+ days
- Maximum 1,000 movies per day
- Skips OMDb sync if no API key configured

## Zero Configuration Required

The automated sync is **enabled by default** when you run the Docker container. No additional setup needed!

When the container starts, you'll see:
```
[CRON] Starting cron daemon for scheduled syncs...
[CRON] Cron daemon started (jobs in /etc/cron.d/pathary-sync)
```

That's it! Your movies will automatically sync daily.

## How to Verify It's Working

### Check Sync Logs

View the sync log file:
```bash
docker compose exec app tail -f /app/storage/logs/cron.log
```

### Manually Trigger a Sync

Test the sync command manually:
```bash
docker compose exec -u www-data app php bin/console.php sync:scheduled
```

**Example output:**
```
=== Starting scheduled sync ===
Max movies: 1000, Min age: 168 hours (7 days)

--- Syncing TMDB metadata ---
✓ TMDB sync completed successfully

--- Syncing OMDb ratings (IMDb + RT) ---
✓ OMDb sync completed successfully

=== Scheduled sync finished ===
```

### Check System Health

Visit **Admin → System Health** to see:
- Last TMDB sync timestamp
- Last OMDb sync timestamp
- API quota status

## Changing the Sync Time

The default sync time is 2 AM (container timezone). To change it:

1. Change the schedule of the `sync:scheduled` line in `docker/cron/pathary-sync`
   (keep the `root setpriv --reuid=www-data ...` part: it runs the job as
   www-data and sends the output to the container log)
2. Rebuild the image

## Disabling Automated Sync

To disable the automated sync completely:

**Option 1: Remove cron from Dockerfile**

Comment out these lines in the Dockerfile:
```dockerfile
# COPY docker/cron/pathary-sync /etc/cron.d/pathary-sync
# RUN chmod 0644 /etc/cron.d/pathary-sync
```

This also disables `maintenance:prune` and `oauth:monitor`; schedule them
elsewhere.

**Option 2: Remove cron startup from entrypoint**

Comment out the `start_cron` call in `docker/entrypoint.sh`:
```bash
main() {
    validate_env
    setup_storage
    wait_for_mysql
    run_migrations
    setup_symlinks
    # start_cron  # Commented out

    echo "=========================================="
    echo "Pathary is ready!"
    echo "=========================================="

    exec "$@"
}
```

Then rebuild the container.

## Manual Sync Instead

If you prefer manual control, disable automated sync (above) and run syncs manually:

```bash
# Sync all (TMDB + OMDb)
docker compose exec app php bin/console.php sync:scheduled

# TMDB only
docker compose exec app php bin/console.php tmdb:movie:sync --hours 168 --threshold 1000

# OMDb only
docker compose exec app php bin/console.php omdb:sync --hours 168 --threshold 1000
```

## Troubleshooting

### Sync Not Running

**Check if cron is running:**
```bash
docker compose exec app ps aux | grep cron
```

You should see:
```
root  123  cron
```

**Check cron configuration:**
```bash
docker compose exec app cat /etc/cron.d/pathary-sync
```

### Sync Failing

**Check the log file:**
```bash
docker compose exec app cat /app/storage/logs/cron.log
```

**Common issues:**
- **TMDB API key not configured** - Set `TMDB_API_KEY` environment variable
- **OMDb API key expired** - Update key in Admin panel
- **Rate limit exceeded** - Wait 24 hours for quota reset
- **Database connection failed** - Check MySQL container is running

### No OMDb Sync Running

This is normal if you haven't configured an OMDb API key. The sync will automatically skip OMDb and only sync TMDB.

To enable OMDb sync:
1. Get API key from https://www.omdbapi.com/
2. Configure in **Admin → Server Management → API Keys**
3. Next scheduled sync will include OMDb

### Logs Not Appearing

**Check log file exists:**
```bash
docker compose exec app ls -la /app/storage/logs/cron.log
```

**Check permissions:**
```bash
docker compose exec app ls -ld /app/storage/logs
```

Should be owned by `www-data:www-data`.

## Advanced Configuration

### Custom Sync Parameters

If you need different sync behavior, create a custom cron file:

```bash
# docker/cron/custom-pathary-sync

# Sync every 12 hours (max 500 movies per run)
0 */12 * * * www-data cd /app && /usr/local/bin/php /app/bin/console.php tmdb:movie:sync --hours 336 --threshold 500 >> /app/storage/logs/tmdb-sync.log 2>&1

# Sync OMDb separately (max 200 movies)
30 2 * * * www-data cd /app && /usr/local/bin/php /app/bin/console.php omdb:sync --hours 336 --threshold 200 >> /app/storage/logs/omdb-sync.log 2>&1
```

### Multiple Daily Syncs

To spread the load throughout the day:

```bash
# Sync at 2 AM and 2 PM. run-cron-job.sh restores the container environment
# (database credentials), which cron does not pass on.
0 2 * * * root setpriv --reuid=www-data --regid=www-data --init-groups /bin/bash /app/build/scripts/run-cron-job.sh sync:scheduled > /proc/1/fd/1 2>&1
0 14 * * * root setpriv --reuid=www-data --regid=www-data --init-groups /bin/bash /app/build/scripts/run-cron-job.sh sync:scheduled > /proc/1/fd/1 2>&1
```

Note: You'll need to modify the command to accept `--threshold` parameter for this to work.

### Log Rotation

To prevent log files from growing too large, add logrotate:

```bash
# /etc/logrotate.d/pathary (on host)
/var/lib/docker/volumes/pathary_storage/_data/logs/cron.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

## See Also

- [Scheduled Sync Command](../features/scheduled-sync.md)
- [TMDB Movie Sync](../features/tmdb-movie-sync.md)
- [OMDb Ratings](../features/omdb-rating.md)
- [System Health Monitoring](../features/system-health.md)
