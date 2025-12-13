#!/bin/bash
# Stop local Pathary development environment
set -e

# Change to repo root
cd "$(dirname "$0")/.."

echo "Stopping Pathary local environment..."

docker compose -f docker-compose.local.yml down

echo "Containers stopped. Data volumes preserved."
echo "Run ./bin/local-up.sh to start again."
