# Scheduled Sync

## Description

Pathary can automatically sync TMDB metadata and OMDb ratings (IMDb + Rotten Tomatoes) on a daily schedule to keep your movie data fresh.

**Default sync behavior:**
- Runs daily (via cron)
- Only syncs movies not updated in **7+ days**
- Maximum **1,000 movies per day**
- Syncs both TMDB and OMDb in one command

## How It Works

The `sync:scheduled` command orchestrates two sync operations:

1. **TMDB Metadata Sync** - Movie details, posters, genres, cast, crew
2. **OMDb Ratings Sync** - IMDb ratings + Rotten Tomatoes scores

Movies are prioritized by how long since their last sync (oldest first).

## Manual Execution

You can manually run the scheduled sync anytime:

```bash
docker compose exec app php bin/console.php sync:scheduled
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

## Setting Up Automated Daily Sync

### Using Cron (Recommended)

Add to your crontab to run daily at 2 AM:

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 2 AM)
0 2 * * * cd /path/to/pathary && docker compose exec -T app php bin/console.php sync:scheduled >> /var/log/pathary-sync.log 2>&1
```

**Note:** Replace `/path/to/pathary` with your actual Pathary installation path.

### Using Docker Compose (Alternative)

You can add a cron container to your `docker-compose.yml`:

```yaml
services:
  app:
    # ... existing app config ...

  cron:
    image: alpine:latest
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./docker/cron/crontab:/etc/crontabs/root:ro
    command: crond -f -l 2
    restart: unless-stopped
```

Create `docker/cron/crontab`:

```
# Run sync daily at 2 AM
0 2 * * * docker compose -f /path/to/pathary/docker-compose.yml exec -T app php bin/console.php sync:scheduled
```

### Using systemd Timer (Linux)

Create `/etc/systemd/system/pathary-sync.service`:

```ini
[Unit]
Description=Pathary Scheduled Sync
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
WorkingDirectory=/path/to/pathary
ExecStart=/usr/bin/docker compose exec -T app php bin/console.php sync:scheduled
User=your-username
```

Create `/etc/systemd/system/pathary-sync.timer`:

```ini
[Unit]
Description=Daily Pathary Sync Timer
Requires=pathary-sync.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
```

Enable and start the timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable pathary-sync.timer
sudo systemctl start pathary-sync.timer

# Check timer status
sudo systemctl list-timers pathary-sync.timer
```

## Customizing Sync Parameters

If you need different sync behavior, you can run the individual commands instead:

### Custom TMDB Sync

```bash
# Sync movies not updated in 14 days (max 500)
docker compose exec app php bin/console.php tmdb:movie:sync --hours 336 --threshold 500

# Sync specific movies
docker compose exec app php bin/console.php tmdb:movie:sync --movieIds 1,2,3
```

### Custom OMDb Sync

```bash
# Sync movies not updated in 30 days (max 200)
docker compose exec app php bin/console.php omdb:sync --hours 720 --threshold 200

# Sync only never-synced movies
docker compose exec app php bin/console.php omdb:sync --never-synced
```

## Monitoring Sync Status

### Check Last Sync Times

Via **System Health** page in admin panel:
- TMDB sync status and timestamp
- OMDb sync status and timestamp
- API quota usage

### Check Logs

```bash
# View sync output
docker compose logs app | grep -i sync

# Follow live logs during sync
docker compose logs -f app
```

### Database Queries

Check when movies were last synced:

```sql
-- TMDB sync timestamps
SELECT id, title, updated_at FROM movie ORDER BY updated_at DESC LIMIT 10;

-- OMDb sync timestamps
SELECT id, title, updated_at_omdb FROM movie WHERE updated_at_omdb IS NOT NULL ORDER BY updated_at_omdb DESC LIMIT 10;

-- Movies needing sync (7+ days old)
SELECT COUNT(*) FROM movie WHERE updated_at < datetime('now', '-7 days');
```

## Troubleshooting

### Cron Job Not Running

**Check cron service:**
```bash
# On systemd systems
sudo systemctl status cron

# Or
sudo systemctl status crond
```

**Check crontab syntax:**
```bash
crontab -l  # List current crontab
```

**Check cron logs:**
```bash
# On Debian/Ubuntu
sudo tail -f /var/log/syslog | grep CRON

# On RHEL/CentOS
sudo tail -f /var/log/cron
```

### Sync Failures

**Check API keys are configured:**
- TMDB API key must be set (required)
- OMDb API key must be set (required for ratings)

**Check rate limits:**
- OMDb free tier: 1,000 requests/day
- Reduce `--threshold` if hitting limits

**Check container is running:**
```bash
docker compose ps
```

### Partial Sync Success

If one sync succeeds but the other fails:
- Check which API key is missing/invalid
- Review logs for specific error messages
- Each sync operates independently

### Slow Sync Performance

**For large libraries (1000+ movies):**
- Consider splitting into smaller batches via `--threshold`
- Run TMDB and OMDb syncs at different times
- Adjust cron schedule to run less frequently

```bash
# Example: Split sync into two batches
0 2 * * * ... sync:scheduled --threshold 500
0 14 * * * ... sync:scheduled --threshold 500
```

## Performance Considerations

### Sync Duration

**Estimated time for 1000 movies:**
- **TMDB sync**: ~10-15 minutes (TMDB rate limit: ~40 requests/10s)
- **OMDb sync**: ~4-5 minutes (250ms delay between requests)
- **Total**: ~15-20 minutes for full sync

### Rate Limits

**TMDB:**
- 40 requests per 10 seconds
- Pathary automatically respects this limit

**OMDb:**
- Free tier: 1,000 requests/day
- Pathary uses 250ms delay (~3,450 requests/day max)
- Paid tier: Higher limits available

### Database Impact

- Minimal impact during sync
- Indexes on `updated_at` and `updated_at_omdb` ensure fast queries
- No table locks during sync operations

## Best Practices

1. **Run during off-peak hours** - 2-4 AM recommended
2. **Monitor initially** - Check logs first few days to ensure success
3. **Adjust threshold** - Start with 500, increase if needed
4. **Log rotation** - Set up log rotation for sync output files
5. **Alert on failures** - Monitor exit codes and set up notifications

### Example Production Cron Setup

```bash
# Pathary scheduled sync with logging and error notification
0 2 * * * cd /opt/pathary && \
  docker compose exec -T app php bin/console.php sync:scheduled \
  >> /var/log/pathary-sync.log 2>&1 || \
  echo "Pathary sync failed on $(date)" | mail -s "Pathary Sync Error" admin@example.com
```

## See Also

- [TMDB Movie Sync](tmdb-movie-sync.md)
- [OMDb Ratings](omdb-rating.md)
- [System Health](system-health.md)
- [Admin Panel](admin-panel.md)
