#!/bin/bash

if [ "$DATABASE_DISABLE_AUTO_MIGRATION" != "true" ] && [ "$DATABASE_DISABLE_AUTO_MIGRATION" != "0" ]; then
  RETRY_COUNT=0
  MAX_RETRIES=5

  echo "INFO: Automatic database migration is enabled"

  while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    /usr/bin/php /app/bin/console.php database:migration:migrate

    if [ $? -eq 0 ]; then
      echo "SUCCESS: Automatic database migration succeeded"
      break
    else
      RETRY_COUNT=$((RETRY_COUNT + 1))
      echo "ERROR: Automatic database migration failed, attempt $RETRY_COUNT of $MAX_RETRIES"
      if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
        echo "ALERT: Automatic database migration failed after $MAX_RETRIES attempts, exiting..."
      fi
      echo "INFO: Retrying database migration in 5 seconds..."
      sleep 5
    fi
  done
else
  echo "INFO: Automatic database migration is disabled";
fi

echo "INFO: Generating symbolic link for storage"
/usr/bin/php /app/bin/console.php storage:link

echo "INFO: Cron daemon will be started by supervisord (daily sync at 2:00 AM)"

# Snapshot the container environment so the cron scheduled sync can restore it.
# cron scrubs the environment before running a job; without this the sync would
# fall back to SQLite and never touch the real database (see run-scheduled-sync.sh).
if [ -f /app/build/scripts/write-cron-env.sh ]; then
  /bin/bash /app/build/scripts/write-cron-env.sh /app/storage/.cron-env || echo "WARNING: could not persist cron environment"
  # dev cron runs as the 'application' user, so make the snapshot readable by it.
  chown application:application /app/storage/.cron-env 2>/dev/null || true
fi

exec "$@"
