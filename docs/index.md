<div style="text-align: center; padding: 2rem 0;">
  <img src="images/pathary-logo.svg" alt="Pathary Logo" style="width: 120px; height: 120px; margin-bottom: 1.5rem;">
  <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Welcome to Pathary</h1>
  <h2 style="font-size: 2rem; color: var(--pathary-gold-dark); margin-bottom: 1rem; font-weight: 600;">
    Self-Hosted Movie Tracking for Friend Groups
  </h2>
  <p style="font-size: 1.2rem; color: var(--md-default-fg-color--light);">
    Rate movies on a 1-7 popcorn scale 🍿 • Share with friends • Track your watch history
  </p>
</div>

---

## What is Pathary?

Imagine **Letterboxd** meets **your own private server** with a **unique popcorn rating system**. That's Pathary!

Built for friend groups who love movies, Pathary lets you:

<div class="grid cards" markdown>

-   :popcorn: **[Popcorn Ratings](features/ratings-and-comments.md)**

    ---

    Rate movies from 1-7 popcorns instead of boring stars. More kernels = more fun!

-   :busts_in_silhouette: **[Group Experience](features/movies-and-tmdb.md)**

    ---

    See what your friends rated each movie. Build your shared cinema culture.

-   :lock: **[Privacy First](security/authentication-and-sessions.md)**

    ---

    Self-hosted on your infrastructure. No tracking, no ads, no corporate overlords.

-   :rocket: **[Easy Setup](getting-started.md)**

    ---

    Docker-based deployment with SQLite. Up and running in 5 minutes.

</div>

!!! tip "Fork Attribution"
    Pathary is a fork of [Movary](https://github.com/leepeuker/movary) by Lee Peuker, enhanced with enterprise security, OAuth email, and group features.

---

## Quick Start

Get Pathary running in 3 simple steps:

=== "Step 1: Clone & Configure"

    ```bash
    # Clone the repository
    git clone https://github.com/benjaminmue/pathary.git
    cd pathary

    # Create your config
    cp .env.example .env.local
    nano .env.local  # Add your TMDB API key
    ```

=== "Step 2: Launch"

    ```bash
    # Start with Docker
    docker compose up -d

    # Run database migrations
    make app_database_migrate
    ```

=== "Step 3: Create Admin"

    ```bash
    # Create your first user (admin)
    docker compose exec app php bin/console user:create
    ```

!!! success "You're Done!"
    Visit `http://localhost/` and start rating movies! 🎉

---

## Key Features

### For Movie Lovers

<div class="grid" markdown>

:material-star-four-points: **Unique Rating System**
{ .card }

Rate movies 1-7 on the popcorn scale. Because 5 stars just doesn't cut it when you need to express that a movie is "good but not great."

:material-history: **Watch History**
{ .card }

Track when and where you watched each movie. Perfect for settling "Did we watch this already?" arguments.

:material-account-group: **Group Ratings**
{ .card }

See aggregate ratings from all users. Find out if you're the only one who loved that weird art film.

:material-magnify: **Movie Discovery**
{ .card }

Browse and search 1M+ movies via TMDB integration. Find your next movie night pick.

</div>

### Security & Auth

<div class="grid" markdown>

:material-two-factor-authentication: **2FA Protection**
{ .card }

TOTP support with recovery codes and trusted device management. Keep your ratings safe!

:material-email-lock: **OAuth Email**
{ .card }

Gmail and Microsoft 365 OAuth 2.0. No more SMTP passwords lying around.

:material-shield-check: **Audit Logging**
{ .card }

Track all security events. Know who logged in, when, and from where.

:material-lock-reset: **Strong Passwords**
{ .card }

Enforced password policy with real-time validation. Security that doesn't compromise.

</div>

---

## Perfect For

!!! example "Friend Groups"
    You watch movies together and want to remember what everyone thought. Build your shared movie culture!

!!! example "Film Clubs"
    Need a private rating system for your club members? Pathary keeps everything in your control.

!!! example "Privacy Enthusiasts"
    Tired of corporate platforms tracking your every move? Host it yourself, own your data.

!!! example "Power Users"
    Want integrations with Plex, Jellyfin, Trakt, and Letterboxd? We've got you covered.

---

## Explore the Docs

<div class="grid cards" markdown>

-   :material-rocket-launch: **[Getting Started](getting-started.md)**

    ---

    Installation guide and initial setup

-   :material-security: **[Security](security/authentication-and-sessions.md)**

    ---

    2FA, OAuth, and password policies

-   :material-cog: **[Configuration](configuration.md)**

    ---

    Environment variables and settings

-   :material-database: **[Architecture](architecture/architecture.md)**

    ---

    How Pathary works under the hood

-   :material-server: **[Deployment](deployment.md)**

    ---

    Production setup and reverse proxy

-   :material-puzzle: **[Features](features/movies-and-tmdb.md)**

    ---

    Movies, ratings, and integrations

-   :material-alert-circle: **[Troubleshooting](operations/logging-and-troubleshooting.md)**

    ---

    Logs, debugging, and common issues

-   :material-update: **[Migrations](migrations.md)**

    ---

    Database migrations and upgrades

</div>

---

## Pathary vs Movary

| Feature | Movary | Pathary |
|:--------|:------:|:-------:|
| 🔐 **OAuth Email Auth** | ❌ | ✅ Gmail + Microsoft 365 |
| 🛡️ **Two-Factor Auth** | ❌ | ✅ TOTP + Recovery Codes |
| 🔑 **Password Policy** | ❌ | ✅ Enforced Requirements |
| 📊 **Health Dashboard** | ❌ | ✅ System Monitoring |
| 🔍 **Audit Logging** | ❌ | ✅ Security Events |
| 👥 **Group Focus** | Individual | Friend Groups |
| 🎨 **Modern UI** | Bootstrap 4 | Bootstrap 5 Dark Mode |

---

## Community & Support

!!! question "Need Help?"
    - 📖 **Documentation**: You're reading it! Use the search bar above.
    - 🐛 **Bug Reports**: [Create an issue](https://github.com/benjaminmue/pathary/issues/new)
    - 💡 **Feature Requests**: [Suggest an idea](https://github.com/benjaminmue/pathary/issues/new)
    - 📝 **Contributing**: Check out [Issue Labels](issue-labels.md)

!!! info "Project Status"
    Pathary is in **active development**. Expect frequent updates, new features, and improvements. Always back up your data before updating!

---

<div style="text-align: center; padding: 2rem 0; color: var(--md-default-fg-color--light);">
  <p style="font-size: 1.1rem;">
    Made with ❤️ for movie lovers everywhere
  </p>
  <p>
    <a href="https://github.com/benjaminmue/pathary">⭐ Star on GitHub</a>
  </p>
</div>
