<p align="center">
  <img src="public/images/pathary-logo-384x384.png" alt="Pathary Logo" width="128" height="128">
</p>

<h1 align="center">Pathary</h1>

<p align="center">
  Self-hosted group movie tracking with popcorn ratings 🍿
</p>

<p align="center">
  <span style="background-color: #f97316; color: #ffffff; padding: 8px 16px; border-radius: 6px; font-weight: 500;">
    ⚠️ This repository is in heavy development and will be updated frequently.
  </span>
</p>

---

## What is Pathary?

Pathary is a self-hosted movie tracking app for friend groups. Rate movies on a **1-7 popcorn scale**, see what others thought, and discover new films via TMDB integration. Fork of [Movary](https://github.com/leepeuker/movary).

## Quick Start

```bash
# Clone the repo
git clone https://github.com/benjaminmue/pathary.git
cd pathary

# Set your TMDB API key (required)
export TMDB_API_KEY=your-api-key

# Start everything
./bin/local-up.sh
```

Open **http://localhost:8080** — first startup takes 30-60 seconds for MySQL init and migrations.

> **Note:** Get a free TMDB API key at [themoviedb.org/settings/api](https://www.themoviedb.org/settings/api)

## Documentation

📖 **[Wiki Home](https://github.com/benjaminmue/pathary/wiki)** — Full documentation

| Topic | Description |
|-------|-------------|
| [Getting Started](https://github.com/benjaminmue/pathary/wiki/Getting-Started) | Installation and first user setup |
| [Deployment](https://github.com/benjaminmue/pathary/wiki/Deployment) | Docker, reverse proxy, production setup |
| [Database](https://github.com/benjaminmue/pathary/wiki/Database) | Schema and migrations |
| [Troubleshooting](https://github.com/benjaminmue/pathary/wiki/Logging-and-Troubleshooting) | Logs and common issues |

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `TMDB_API_KEY` | Yes | — | TMDB API key |
| `DATABASE_MODE` | No | `sqlite` | `sqlite` or `mysql` |
| `APPLICATION_URL` | No | — | Public URL for reverse proxy setups |
| `DATABASE_DISABLE_AUTO_MIGRATION` | No | `false` | Set to `true` to disable auto-migrations |

See [.env.example](.env.example) for all options.

## Development

```bash
composer install      # Install dependencies
composer test         # Run all checks (CS, PHPStan, Psalm, PHPUnit)
```

## License

MIT License — see [LICENSE](LICENSE).

**Original:** [Movary](https://github.com/leepeuker/movary) by Lee Peuker
**Fork:** Pathary by Benjamin Müller
