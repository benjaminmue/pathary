#!/bin/bash
# Start local Pathary development environment
set -e

# Change to repo root
cd "$(dirname "$0")/.."

echo "=========================================="
echo "Starting Pathary local environment..."
echo "=========================================="

# Create required directories
mkdir -p ./docker/mysql-data
mkdir -p ./storage

# Build and start containers
docker compose -f docker-compose.local.yml up -d --build

echo ""
echo "=========================================="
echo "Container status:"
echo "=========================================="
docker compose -f docker-compose.local.yml ps

echo ""
echo "=========================================="
echo "Pathary is starting up!"
echo ""
echo "Open: http://localhost:8080"
echo ""
echo "First startup may take 30-60 seconds while"
echo "MySQL initializes and migrations run."
echo ""
echo "View logs: docker compose -f docker-compose.local.yml logs -f"
echo "=========================================="
