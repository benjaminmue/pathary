# Security Audit Findings

## Summary

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 0 |
| Medium | 1 |
| Low | 3 |

## Findings

### SEC-001: Missing CSRF Protection on State-Changing Forms

**Severity:** Medium

**Affected Files:**
- `templates/public/movie_detail.twig:473-536` (rating form)
- `templates/page/settings-*.html.twig` (all settings forms)
- `src/HttpController/Web/RateMovieController.php:30-96`
- `src/HttpController/Web/ProfileController.php` (profile update)

**Description:**
The application has a `CsrfTokenService` (`src/Service/CsrfTokenService.php`) that generates and validates CSRF tokens using `hash_equals()` for timing-safe comparison. However, this service is **not injected or used in any controller**. All POST forms lack CSRF token validation, making them vulnerable to Cross-Site Request Forgery attacks.

An attacker could craft a malicious page that submits forms on behalf of authenticated users (e.g., changing ratings, updating profiles, deleting data).

**Proof:**
```bash
grep -r "CsrfTokenService\|validateToken" src/HttpController/
# Returns no matches - service exists but is unused
```

**Recommended Fix:**

1. Inject `CsrfTokenService` into controllers that handle POST/PUT/DELETE requests
2. Generate token in templates:
```twig
<input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
```
3. Validate in controllers:
```php
public function __construct(
    private readonly CsrfTokenService $csrfTokenService,
    // ...
) {}

public function rate(Request $request): Response
{
    $postData = $request->getPostParameters();
    if (!$this->csrfTokenService->validateToken($postData['_csrf_token'] ?? null)) {
        return Response::createForbidden('Invalid CSRF token');
    }
    // ... rest of logic
}
```
4. Create Twig extension to expose `csrf_token()` function

---

### SEC-002: Dynamic ORDER BY Clauses

**Severity:** Low

**Affected Files:**
- `src/Service/GroupMovieService.php:211-217`
- `src/Domain/Movie/MovieRepository.php:132, 272, 418, 1012`
- `src/Domain/Movie/Watchlist/MovieWatchlistRepository.php:248`

**Description:**
Several database queries construct ORDER BY clauses dynamically. While currently protected by `match` statements and whitelisting, this pattern requires vigilance to prevent SQL injection if modified incorrectly.

**Current Protection (GroupMovieService.php:206-217):**
```php
// Sort order is binary validated
$sortOrderSql = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Sort field uses safe match statement
$orderByClause = match ($sortBy) {
    'title' => "LOWER(m.title) $sortOrderSql",
    'release_date' => "m.release_date IS NULL, m.release_date $sortOrderSql",
    'global_rating' => "avg_popcorn IS NULL, avg_popcorn $sortOrderSql",
    'own_rating' => "own_rating IS NULL, own_rating $sortOrderSql",
    default => "last_added_at $sortOrderSql",
};
```

**Recommended Fix:**
- Continue using `match`/`switch` statements with explicit whitelisted values
- Add code comments explaining security implications
- Consider creating a dedicated `SortBuilder` class that enforces whitelist validation

---

### SEC-003: Auth Token in Cookie

**Severity:** Low

**Affected Files:**
- `src/Domain/User/Service/Authentication.php:247-257`

**Description:**
Authentication tokens are stored directly in cookies. While proper security flags are set (HttpOnly, Secure, SameSite=Lax), the raw token is transmitted rather than a secondary identifier.

**Mitigations Already in Place:**
- `token_hash` column exists for server-side comparison
- Session regeneration on login prevents session fixation
- Proper cookie security flags

**Recommended Fix:**
Consider using a session ID that maps to the token hash server-side, rather than transmitting tokens in cookies. This adds defense in depth if cookies are ever exposed.

---

### SEC-004: Error Logging May Contain Sensitive Paths

**Severity:** Low

**Affected Files:**
- `public/index.php:67-73`

**Description:**
Exception handlers log full stack traces which may contain sensitive file paths. Users see generic error pages, but log files could leak information if accessed.

**Recommended Fix:**
- Ensure log files have restricted access permissions (0640 or stricter)
- Consider sanitizing or truncating stack traces in production
- Avoid logging request bodies that may contain credentials

---

## Positive Security Findings

The following security measures are already implemented:

- **Security Headers**: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- **Session Security**: Regeneration on login, HttpOnly/Secure/SameSite cookies
- **SQL Injection Protection**: Parameterized queries throughout
- **File Upload Security**: MIME validation, size limits, random filenames, path traversal protection
- **Password Security**: Proper hashing with `password_verify()`
- **Dependency Security**: `composer audit` shows no vulnerabilities

---

## Suggested Fixes Priority

1. **High**: Implement CSRF protection using existing `CsrfTokenService`
2. **Medium**: Document ORDER BY whitelist pattern for future developers
3. **Low**: Review log file permissions and access controls
