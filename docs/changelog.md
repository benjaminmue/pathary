# Wiki Changelog

This page tracks major updates to the Pathary Wiki documentation.

---

## 2025-12-22 - Security and UI Update

**Covers main repository commits**: `ca155ec4` through `d8bd95c3` (2025-12-14 to 2025-12-22)

### New Pages Added

- **[Two-Factor Authentication](Two-Factor-Authentication)** - Comprehensive guide to TOTP, recovery codes, and trusted devices
- **[Password Policy and Security](Password-Policy-and-Security)** - Password requirements and security best practices
- **Changelog** (this page) - Track Wiki updates

### Major Updates

#### Security Features

- **Authentication-and-Sessions.md**: Updated 2FA section with recovery codes and trusted device support
- Documented new security routes (`/profile/security/*`)
- Added security audit log functionality
- Updated login flow to include device trust and recovery code options

#### Frontend and UI

- **Frontend-and-UI.md**:
  - Added Footer Navigation section documenting the new sticky footer with GitHub links
  - Expanded Bootstrap Icons section with security and profile page icons
  - Updated Base Layout with flexbox sticky footer pattern and conditional display
  - Added bottom spacing documentation (`pb-4` class)

#### Getting Started

- **Getting-Started.md**:
  - Updated to use `.env.local` for local development (never committed to git)
  - Emphasized port 80 requirement for proper routing
  - Added proper docker-compose command for local development
  - Updated port configuration guidance with `APPLICATION_URL` sync

#### Home Page

- **Home.md**:
  - Added 3 new features to Feature Overview (2FA, Password Policy, Security Audit Log)
  - Reorganized Wiki Navigation with new "Security" section
  - Fixed Wiki link syntax (removed extra `]` brackets)

### Main Repository Changes Documented

#### Security/Authentication (Commits: fd5084e8, 10ee888f, 7d242b3a, 3d08f8fa, d8bd95c3)

- Complete 2FA system with TOTP, QR codes, and authenticator app support
- Recovery codes system (10 single-use backup codes, bcrypt hashed)
- Trusted devices feature (30-day trust via secure cookies, 10 device limit)
- Recovery code confirmation flow with progressive UI
- Password policy enforcement (10+ chars, uppercase, lowercase, number, special char)
- Security audit log for tracking security events
- Security configuration improvements (removed hardcoded credentials)
- Secret scanning documentation and tools

#### UI/UX Improvements (Commits: 2b880b4d, f09647d3, 19c70415, e0c9f94d, e99f38f8)

- Sticky footer navigation with GitHub, Wiki, and Issue links
- Footer hidden on login page
- Consistent bottom spacing across all pages (pb-4)
- Login page centered on all screen sizes
- Bootstrap Icons added to Profile and Security settings
- Improved icon coverage for security features

#### Configuration (Commits: 50aed95c, d8bd95c3)

- `.env.local` approach for local development
- Expanded `.gitignore` patterns for security
- Removed `docker-compose.local.yml` with hardcoded credentials
- Converted `http-client.env.json` to `.example` template

#### Routes (Commit: 3382249e)

- Fixed `/old/*` routes redirect to use correct APPLICATION_URL
- Added 9 new security-related routes for 2FA, recovery codes, and trusted devices

### Files Not Changed (Still Accurate)

The following pages were reviewed and remain accurate:
- Architecture.md
- Database.md
- Deployment.md
- Logging-and-Troubleshooting.md
- Migrations.md
- Movies-and-TMDB.md
- Ratings-and-Comments.md
- Routing-and-Controllers.md

---

## 2025-12-14 - Initial Wiki Creation

**Initial comprehensive documentation** created with the following pages:

- Home.md
- Architecture.md
- Authentication-and-Sessions.md
- Database.md
- Deployment.md
- Frontend-and-UI.md
- Getting-Started.md
- Logging-and-Troubleshooting.md
- Migrations.md
- Movies-and-TMDB.md
- Ratings-and-Comments.md
- Routing-and-Controllers.md

---

[← Back to Wiki Home](Home)
