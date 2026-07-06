# Changelog

## [0.7.1](https://github.com/benjaminmue/pathary/compare/pathary-v0.7.0...pathary-v0.7.1) (2026-07-06)


### Features

* **faq:** add an FAQ page with the popcorn rating scale, footer link and help icon ([1aab116](https://github.com/benjaminmue/pathary/commit/1aab11669efee27dc0ae1b6a06d8e5c90e59b187))
* **i18n:** add DE/EN translation foundation and language switcher ([#54](https://github.com/benjaminmue/pathary/issues/54)) ([879fd11](https://github.com/benjaminmue/pathary/commit/879fd114d47102829206636560e9017d39f02dfc))
* **i18n:** move the DE/EN language switcher into profile settings ([#58](https://github.com/benjaminmue/pathary/issues/58)) ([3230af8](https://github.com/benjaminmue/pathary/commit/3230af84cf53cab3509c4e4a578a68acfbc277fc))
* **i18n:** persist language preference per user ([#55](https://github.com/benjaminmue/pathary/issues/55)) ([bceb5d7](https://github.com/benjaminmue/pathary/commit/bceb5d71e9055694e1b70c5bfee1e2f8fb2c6702))
* **i18n:** translate browse, core and account-settings pages (wave 1) ([#56](https://github.com/benjaminmue/pathary/issues/56)) ([9d1dd30](https://github.com/benjaminmue/pathary/commit/9d1dd30d75ddf673415fdb7e9f04ecc8db5d9b23))
* **i18n:** translate home, admin, integrations, server & auth pages (wave 2) ([#57](https://github.com/benjaminmue/pathary/issues/57)) ([53acf30](https://github.com/benjaminmue/pathary/commit/53acf30c4f5548dce53627ab3e9aa3023167a33f))
* movie detail redesign + TMDB facts + FAQ page ([b2f4b4d](https://github.com/benjaminmue/pathary/commit/b2f4b4d404167fcdecfaefb6f56125f64dbda43e))
* **movie:** redesign the detail page and add TMDB facts (budget, revenue, status, original title) ([adcc9ce](https://github.com/benjaminmue/pathary/commit/adcc9ce9d73243aa7b6af034774b541023e3522a))
* **movies:** card-sidebar redesign of the All Movies page ([#62](https://github.com/benjaminmue/pathary/issues/62)) ([84d3a47](https://github.com/benjaminmue/pathary/commit/84d3a470b4bde3e85fa7c3cafe9ee93c248226e9))
* **movies:** staggered "Pop" reveal on load and page navigation ([#63](https://github.com/benjaminmue/pathary/issues/63)) ([96e0e14](https://github.com/benjaminmue/pathary/commit/96e0e14e895ecd4f842c75bbe9abc3f668c16e78))
* **profile:** card-based profile settings redesign ([#60](https://github.com/benjaminmue/pathary/issues/60)) ([c5c1858](https://github.com/benjaminmue/pathary/commit/c5c18587b95da8e73ac16fe0493ab4e6e8523423))
* **search:** redesign the search page with a unified library + TMDB view ([1ed286d](https://github.com/benjaminmue/pathary/commit/1ed286da24c56adc1c7a915f32b4b105cb0391b2))
* **search:** unified library + TMDB search page redesign ([6f5b00d](https://github.com/benjaminmue/pathary/commit/6f5b00d4c361fb100416055b296aea61369997db))
* showcase landing + app-wide design alignment + mandatory 2FA ([#49](https://github.com/benjaminmue/pathary/issues/49)) ([c6de31e](https://github.com/benjaminmue/pathary/commit/c6de31e391e5a929fa958dca0ea42c3d1969a1ce))
* **ui:** sun/moon icon inside the theme toggle knob ([#61](https://github.com/benjaminmue/pathary/issues/61)) ([39fe8c5](https://github.com/benjaminmue/pathary/commit/39fe8c5e76b9870cc3f0076dd13f848eb4ec7f75))


### Bug Fixes

* **api:** enforce mandatory 2FA on API token creation ([#51](https://github.com/benjaminmue/pathary/issues/51)) ([1ea6929](https://github.com/benjaminmue/pathary/commit/1ea692956abf5919b851ae7a27e62cacf475a503))
* **auth:** add persistent brute-force rate limiting to login (GH [#44](https://github.com/benjaminmue/pathary/issues/44)) ([#52](https://github.com/benjaminmue/pathary/issues/52)) ([f04ddaf](https://github.com/benjaminmue/pathary/commit/f04ddaf3aa3cf2c377ab1b82c75edd96f1bdfefa))
* **email:** encrypt SMTP password at rest (GH [#15](https://github.com/benjaminmue/pathary/issues/15)) ([#53](https://github.com/benjaminmue/pathary/issues/53)) ([c570980](https://github.com/benjaminmue/pathary/commit/c570980bfccb76968fca5aa51b65bdebc0952d59))
* **movie:** full-width facts grid + always-shown facts with 'Nicht verfügbar' + translated status ([f041e22](https://github.com/benjaminmue/pathary/commit/f041e22316d62ecce429ab6b08dc8e2e2945d9e8))
* **movie:** full-width TMDB facts grid, always show every fact, translate status ([1113b31](https://github.com/benjaminmue/pathary/commit/1113b3166c7a7e2e9aaf24eaabc684934da6b2aa))
* **omdb:** stop partial OMDb responses from wiping stored IMDb/RT ratings ([#50](https://github.com/benjaminmue/pathary/issues/50)) ([054033c](https://github.com/benjaminmue/pathary/commit/054033c0fedeefd044fa299419afc8cebd322402))
* **profile:** give the language setting its own separated section ([#59](https://github.com/benjaminmue/pathary/issues/59)) ([9fe9a78](https://github.com/benjaminmue/pathary/commit/9fe9a7835314bdc5d2764e31d4e188106b1b79ed))

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
