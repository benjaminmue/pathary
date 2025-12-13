<h1 align="center">
  <br>
  Pathary
  <br>
</h1>

<h4 align="center">Self-hosted group movie tracking (fork of Movary)</h4>

<p align="center">
<a href="https://github.com/benjaminkomen/pathary/pkgs/container/pathary" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/badge/GHCR-pathary-blue?logo=github" ></a>
<a href="https://github.com/leepeuker/movary" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/github/stars/leepeuker/movary?color=yellow&label=upstream%20stars" ></a>
<a href="https://github.com/leepeuker/movary/blob/main/LICENSE" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/github/license/leepeuker/movary" ></a>
</p>

Pathary is a fork of [Movary](https://github.com/leepeuker/movary) focused on group movie tracking.
Track movies with friends, import metadata from TMDB, and rate films using a 1-7 popcorn scale.
Self-hosted via Docker with MySQL or SQLite.

## Features

- **Public home page** - Poster grid showing the 20 most recently added movies
- **Movie details** - View global average rating, individual user ratings, and comments
- **Popcorn rating** - Rate movies on a 1-7 scale with optional comments
- **Persistent login** - Stay logged in until cookies are cleared
- **Movie search** - Search local library first, fallback to TMDB, add new movies
- **All movies list** - Browse library with sorting (title, year, rating) and filtering (genre, year, rating)
- **Profile management** - Update name, email, and profile picture

## Upstream Movary Features

Pathary inherits all features from Movary:

- Movie watch history with ratings
- Statistics (most watched actors/directors/genres/languages/years)
- Third party integrations (Trakt, Letterboxd, Netflix import/export)
- Scrobbler support (Plex, Jellyfin, Emby, Kodi)
- PWA support for smartphone installation
- User management

## Attribution

This project is a fork of [Movary](https://github.com/leepeuker/movary) by [@leepeuker](https://github.com/leepeuker).
See the upstream repository for the original project and contributors.
