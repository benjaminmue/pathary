# Pathary

Self-hosted group movie tracking with popcorn ratings on a 1-7 scale. A fork of Movary,
detached from upstream, with TMDB providing movie data.

## Stack
PHP with Composer, MySQL or SQLite, Bootstrap 5 in dark mode. Runs in Docker on port 80.

## What a review needs to know
- Authentication carries 2FA over TOTP, recovery codes and trusted devices. Changes in
  that area deserve the closest reading in the whole codebase.
- `TMDB_API_KEY` belongs in `.env.local` and must never be committed.
- `vendor/` is committed. Findings inside it come from upstream packages and are not this
  project's code; report them only if a dependency needs bumping.
- The git history starts with Lee Peuker's Movary commits from 2021, including a
  `settings/config.ini` with his Trakt credentials that he removed two commits later.
  Those keys are inherited, long public upstream and not rotatable from here. The
  gitleaks config excuses that one commit by fingerprint and nothing else.
- This repository is `benjaminmue/pathary`. Never open issues or PRs against
  `leepeuker/movary`.

## Conventions
Code, comments and commit messages in English. Public repository.
