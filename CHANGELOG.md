# Changelog

## [0.7.3](https://github.com/benjaminmue/pathary/compare/v0.7.2...v0.7.3) (2026-07-07)


### Bug Fixes

* **sync:** scheduled cron sync ran against an empty SQLite DB ([5b2514f](https://github.com/benjaminmue/pathary/commit/5b2514f5721249e69351c83ce4ec2c98fbc47a51))
* **sync:** scheduled cron sync ran against an empty SQLite DB ([2bf4851](https://github.com/benjaminmue/pathary/commit/2bf4851943abcb90fd75212d8e582a59223df204))

## [0.7.2](https://github.com/benjaminmue/pathary/compare/v0.7.1...v0.7.2) (2026-07-07)


### Features

* **admin:** export security events as JSON for a chosen time range ([fa56b8f](https://github.com/benjaminmue/pathary/commit/fa56b8f6a6cc60c228acfd52bc514056907df656))
* **admin:** real rating counts per user and view counts per location ([a8302d2](https://github.com/benjaminmue/pathary/commit/a8302d2328b0f2c1910e740901c1632d66af1b54))
* **admin:** redesign the admin panel (shell + all 5 tabs) ([230333f](https://github.com/benjaminmue/pathary/commit/230333ffad09b83f1420d581a9502e31b3581437))
* **admin:** redesign the admin panel (shell + all 5 tabs) to the pds design ([2ae271a](https://github.com/benjaminmue/pathary/commit/2ae271a3eef0923fe3db2f96381da1b9d897663d))


### Bug Fixes

* **admin:** real DOM+JS rebuild of the server tab to the compact design ([4f8a04a](https://github.com/benjaminmue/pathary/commit/4f8a04ac2a3758e0231cdc5f3215c79708413669))
* **admin:** short tab labels via i18n, no tab icons, 'Admin' header ([c0d10b3](https://github.com/benjaminmue/pathary/commit/c0d10b3be35f85d3b734c0e1176323feda9b8048))
* **admin:** stop the events 'Details' button being clipped ([ad621b5](https://github.com/benjaminmue/pathary/commit/ad621b5f7526723467bdfdd6b6f2e6472a883132))
* **admin:** stop the right bulk-op card being offset by the sibling margin ([f41c664](https://github.com/benjaminmue/pathary/commit/f41c6643271a11f3c705df9c18292fd9e8861e40))
* **release:** tag releases as v* instead of pathary-v* ([f4a7a1e](https://github.com/benjaminmue/pathary/commit/f4a7a1e56d225a308653dfed222998a632576c86))
* **release:** tag releases as v* so the versioned Docker build triggers ([202d124](https://github.com/benjaminmue/pathary/commit/202d124d9882f03fb71089277036fbdcb60e00c4))
* **ui:** dark-default login page + slimmer admin event rows ([e4b7273](https://github.com/benjaminmue/pathary/commit/e4b7273a06f6bac680b819af2da81dcb86843347))
* **ui:** stale theme.css cache (toggle), i18n-broken security tab loader, profile spacing ([c96ce85](https://github.com/benjaminmue/pathary/commit/c96ce85ac7d8ab78880bea26de2e107f10386941))
* **ui:** stale theme.css cache, i18n-broken security tab loader, profile top spacing ([06c1c24](https://github.com/benjaminmue/pathary/commit/06c1c24d85c4bc0ac282ffbe19451cf6bdc1cba1))

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
