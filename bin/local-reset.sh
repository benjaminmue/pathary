#!/bin/bash
# Reset local Pathary environment - DESTROYS ALL DATA
set -e

# Change to repo root
cd "$(dirname "$0")/.."

echo "=========================================="
echo "WARNING: This will DELETE all local data!"
echo "=========================================="
echo ""
echo "The following will be removed:"
echo "  - MySQL database (./docker/mysql-data)"
echo "  - Application storage (./storage)"
echo "  - Docker volumes"
echo ""
read -p "Are you sure? Type 'yes' to confirm: " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo "Stopping containers and removing volumes..."
docker compose -f docker-compose.local.yml down -v 2>/dev/null || true

echo "Removing MySQL data..."
rm -rf ./docker/mysql-data

echo "Removing application storage..."
rm -rf ./storage

echo ""
echo "=========================================="
echo "Reset complete. Run ./bin/local-up.sh to start fresh."
echo "=========================================="
