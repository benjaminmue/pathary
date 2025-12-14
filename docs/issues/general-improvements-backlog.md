# General Improvements Backlog

## Summary

| Priority | Count |
|----------|-------|
| High | 0 |
| Medium | 1 |
| Low | 4 |

---

## Medium Priority

### IMP-001: Inconsistent Naming (Movary vs Pathary)

**Priority:** Medium
**Effort:** High (if full rename) or Low (if documented)

**Affected Files:**
- `composer.json:2` - Package name is `leepe/movary`
- All `src/` files - Namespace is `Movary\`
- `docs/openapi.json` - References "Movary" throughout
- `.env.example` - References movary.org docs
- GitHub workflow files
- Various configuration files

**Description:**
The project was forked from Movary and rebranded as Pathary, but the internal PHP namespace and many references still use "Movary". This causes confusion for contributors and users.

**Metrics:**
- 1,654 occurrences of "Movary" across 409 PHP/Twig files
- All PHP classes use `namespace Movary\...`

**Options:**

**Option A: Full Rename (High Effort)**
1. Update composer.json package name
2. Rename all namespaces from `Movary\` to `Pathary\`
3. Update autoloader configuration
4. Update all `use` statements
5. Update OpenAPI spec
6. Update tests

**Option B: Document Current State (Low Effort)**
1. Add README section explaining naming history
2. Keep internal namespace as `Movary` for backwards compatibility
3. Only use "Pathary" for user-facing branding
4. Update documentation to clarify

**Recommended:** Option B initially, with Option A as future milestone

---

## Low Priority

### IMP-002: Remove or Implement CsrfTokenService

**Priority:** Low
**Effort:** Low

**Affected Files:**
- `src/Service/CsrfTokenService.php`

**Description:**
The CSRF token service is fully implemented but never used anywhere in the codebase. This is dead code that either should be:
1. Implemented throughout the application (see Security Findings)
2. Removed to reduce confusion

**Recommended:** Implement CSRF protection (links to Security audit SEC-001)

---

### IMP-003: Improve Default Credentials Warning

**Priority:** Low
**Effort:** Low

**Affected Files:**
- `docker-compose.yml:23-25, 37-39`
- `README.md`

**Description:**
Default database credentials are `movary`/`movary` which might be overlooked in production deployments.

**Current:**
```yaml
DATABASE_MYSQL_USER: "${MYSQL_USER:-movary}"
DATABASE_MYSQL_PASSWORD: "${MYSQL_PASSWORD:-movary}"
```

**Recommended Fix:**
1. Add warning comment in docker-compose.yml:
```yaml
# WARNING: Change these default credentials in production!
DATABASE_MYSQL_PASSWORD: "${MYSQL_PASSWORD:-CHANGE_ME_IN_PRODUCTION}"
```

2. Add security note to README installation section

---

### IMP-004: Update OpenAPI Specification

**Priority:** Low
**Effort:** Medium

**Affected Files:**
- `docs/openapi.json`

**Description:**
The OpenAPI specification:
1. Still references "Movary" branding
2. May not include newer API endpoints
3. Missing documentation for rating system changes (popcorn scale, watched_date, location)

**Recommended Fix:**
1. Update title and descriptions to "Pathary"
2. Audit all routes in `settings/routes.php` against OpenAPI spec
3. Add schemas for new rating fields:
   - `watched_year`, `watched_month`, `watched_day`
   - `location_id` with enum values
   - `rating_popcorn` (1-7 scale)

---

### IMP-005: Document New Features

**Priority:** Low
**Effort:** Low

**Affected Files:**
- `docs/` directory

**Description:**
Recent features lack user documentation:
- Popcorn rating system (1-7 scale)
- Partial date support for "watched date"
- Location tracking (Cinema, At Home, Other)
- Profile image upload
- Delete rating functionality

**Recommended Fix:**
Create or update documentation pages:
1. `docs/features/ratings.md` - Rating system explanation
2. `docs/features/watch-history.md` - Date and location tracking
3. `docs/user-guide/profile.md` - Profile management

---

## Future Considerations

### Consider: TypeScript Migration for Frontend JS

**Files:** `public/js/*.js`

The project uses vanilla JavaScript with some inline scripts in templates. As the frontend grows, consider:
- Extracting inline scripts to separate files
- Adding TypeScript for type safety
- Using a build system (Vite, esbuild) for bundling

### Consider: API Versioning

**Files:** `settings/routes.php`

Current API routes are unversioned (`/api/authentication/login`). Consider:
- Adding version prefix (`/api/v1/...`)
- Planning deprecation strategy for breaking changes

### Consider: Automated Testing for UI

**Files:** `tests/`

Current tests focus on unit tests. Consider adding:
- Integration tests for API endpoints
- Browser tests for critical user flows (login, rating)

---

## Prioritized Backlog

| ID | Item | Priority | Effort | Dependencies |
|----|------|----------|--------|--------------|
| IMP-001 | Document Movary/Pathary naming | Medium | Low | None |
| IMP-002 | Implement CSRF protection | Low | Medium | SEC-001 |
| IMP-003 | Default credentials warning | Low | Low | None |
| IMP-004 | Update OpenAPI spec | Low | Medium | None |
| IMP-005 | Document new features | Low | Low | None |
