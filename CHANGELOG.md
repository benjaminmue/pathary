# Changelog

## 0.7.0 (2026-07-06)

### Features

* **Security:** two-factor authentication is now mandatory for every account and cannot be disabled; accounts without 2FA are forced to set it up at login, and 2FA can be re-initialised on a new device.
* **Security:** the API token endpoint enforces 2FA (no token for accounts without 2FA), login has persistent IP-based brute-force rate limiting (HTTP 429), and the SMTP password is encrypted at rest.
* **i18n:** the whole UI is available in German and English with a per-user language setting in the profile (default German).
* **Design:** card-based redesign of the profile settings and the All Movies page; refreshed home landing; neutral/gold palette with a sun/moon theme toggle.
* **Movies:** live client-side filtering and sorting on the All Movies page (no reload), uniform cropped poster tiles, and a staggered reveal animation on load and page navigation.

### Bug Fixes

* **Movies:** partial OMDb responses no longer overwrite stored IMDb/Rotten Tomatoes ratings with null.
* **Build:** the release version is now baked into the image (`APPLICATION_VERSION`) instead of showing "unknown".

### Chores

* Cleared the pre-existing static-analysis debt (phpcs/phpstan/psalm) to green the CI gate.
* Added automated release management (release-please + versioned GHCR builds on tag).
