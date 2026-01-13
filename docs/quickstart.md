# Quickstart Guide

Get Pathary up and running in 5 minutes with Docker.

## Prerequisites

Before you begin, you'll need:

- **Docker** and **Docker Compose** installed ([Get Docker](https://docs.docker.com/get-docker/))
- **TMDB API Key** for movie data ([Get your free key here](https://www.themoviedb.org/settings/api))

!!! tip "Why TMDB?"
    Pathary uses The Movie Database (TMDB) API to fetch movie information, posters, and metadata. The API is free for non-commercial use.

## Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/benjaminmue/pathary.git
cd pathary
```

### Step 2: Configure Environment

Create a `.env.local` file with your TMDB API key:

```bash
cat > .env.local << 'EOF'
TMDB_API_KEY=<tmdb_api_key>
HTTP_PORT=80
APPLICATION_URL=http://localhost
EOF
```

!!! warning "Keep Your API Key Secret"
    Never commit `.env.local` to git - it's already in `.gitignore` to protect your API key.

### Step 3: Start Pathary

**For local development (SQLite):**

```bash
docker compose --env-file .env.local \
  -f docker-compose.yml \
  -f docker-compose.development.yml \
  up -d
```

**For local development (MySQL):**

```bash
docker compose --env-file .env.local \
  -f docker-compose.yml \
  -f docker-compose.development.yml \
  -f docker-compose.mysql.yml \
  up -d
```

The application will be available at **http://localhost/** (port 80).

!!! info "Port Mapping"
    Docker maps your host port 80 to the container's internal port 8080. Access the app at `http://localhost/` (no port number needed).

### Step 4: Complete First-Time Setup

On first launch, you'll be automatically redirected to the setup wizard at `/init`:

1. **Open your browser** and navigate to `http://localhost/`
2. **You'll be redirected** to `http://localhost/init`
3. **Follow the wizard** to create your admin account with 2FA

The wizard guides you through:

- Creating your admin account
- Setting up two-factor authentication (2FA)
- Generating recovery codes
- Completing the setup

For detailed screenshots and troubleshooting, see the [First-Time Setup Guide](first-time-setup.md).

## Quick Reference

### Check if Pathary is Running

```bash
# View logs
docker compose logs pathary

# Check container status
docker compose ps
```

### Stop Pathary

```bash
docker compose down
```

### Restart Pathary

```bash
docker compose restart pathary
```

### Access the Database (MySQL)

```bash
docker compose exec mysql mysql -u <database_user> -p<database_password> <database_name>
```

!!! tip "MySQL Password Syntax"
    Note: `-p<database_password>` has NO space between `-p` and the password. This is required MySQL syntax.

### Access the Application Container

```bash
docker compose exec app bash
```

## Common Issues

### Port 80 Already in Use

If port 80 is already in use on your machine, change the port mapping:

1. Edit `.env.local`:
   ```bash
   HTTP_PORT=8080
   APPLICATION_URL=http://localhost:8080
   ```

2. Update your docker compose command to expose the chosen port:
   ```bash
   docker compose --env-file .env.local \
     -f docker-compose.yml \
     -f docker-compose.development.yml \
     up -d
   ```

3. Access the app at `http://localhost:8080/`

### "TMDB_API_KEY not set" Warning

This warning appears if the API key isn't found. Ensure:

1. `.env.local` exists in the `pathary/` directory
2. The file contains `TMDB_API_KEY=<tmdb_api_key>`
3. You're using `--env-file .env.local` in the docker compose command

### Database Migration Errors

The Docker container automatically runs database migrations on startup. If migrations fail:

```bash
# Check logs for specific error
docker compose logs pathary

# Manually run migrations
docker compose exec app php vendor/bin/phinx migrate -c ./settings/phinx.php
```

### Storage Permission Errors

If you see permission errors related to `/app/storage`:

```bash
# Fix permissions
docker compose exec app chmod -R 777 /app/storage
```

Or set matching user/group IDs in `.env.local`:
```bash
USER_ID=1000
GROUP_ID=1000
```

## Next Steps

Now that Pathary is running:

- **[First-Time Setup Guide](first-time-setup.md)** - Detailed walkthrough of the setup wizard
- **[Configuration](configuration.md)** - Environment variables and settings reference
- **[Features](features/movies-and-tmdb.md)** - Explore what Pathary can do
- **[Production Deployment](deployment.md)** - Deploy for production use

## Need Help?

- **[FAQ](faq.md)** - Frequently asked questions
- **[GitHub Issues](https://github.com/benjaminmue/pathary/issues)** - Report bugs or request features
- **[GitHub Wiki](https://github.com/benjaminmue/pathary/wiki)** - Community guides and tips
