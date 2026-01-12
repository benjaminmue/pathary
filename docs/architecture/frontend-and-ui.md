# Frontend and UI

This page covers Pathary's frontend architecture, templates, and styling.

## Technology Stack

| Component | Technology |
|-----------|------------|
| Templating | Twig |
| CSS Framework | Bootstrap 5 |
| Icons | Bootstrap Icons |
| JavaScript | Vanilla JS |
| Date Picker | Datepicker.js |

## Template Structure

```
templates/
├── base.html.twig              # Root layout
├── component/                  # Reusable components
│   ├── navbar.html.twig        # Authenticated navbar
│   ├── navbar_public.twig      # Public navbar
│   ├── modal_log_play.twig     # Log play modal
│   └── ...
├── components/
│   └── popcorn_rating.twig     # Rating widget
├── layouts/
│   ├── layout_public.twig      # Public page layout
│   └── layout_app.twig         # App page layout
├── page/                       # Full pages
│   ├── login.html.twig
│   ├── settings-*.html.twig
│   └── ...
├── partials/                   # Page sections
│   └── movie_grid.twig         # Movie poster grid
└── public/                     # Public-facing pages
    ├── home.twig               # Home page
    └── movie_detail.twig       # Movie detail page
```

## Base Layout

**File**: `templates/base.html.twig`

The base template provides the foundational HTML structure for all pages, implementing a flexbox sticky footer pattern with consistent bottom spacing. It includes comprehensive meta tags for SEO and PWA support (CSRF token, canonical link, OpenGraph tags, favicon, manifest, theme color), CSS resources (Bootstrap, Bootstrap Icons, datepicker, global styles), hidden input fields for user preferences (date formats, current user data), JavaScript initialization (APPLICATION_URL window variable, jQuery, Bootstrap bundle, datepicker), and the footer partial. The layout uses `data-bs-theme` attribute for dark mode support, `min-vh-100` + `flex-column` + `flex-grow-1` for sticky footer behavior, and allows pages to hide the footer via `showFooter` variable. Note: The template does NOT include a navbar block - pages must include the navbar component separately if needed.

### Layout Features

- **Sticky Footer**: Uses `min-vh-100` + `flex-column` + `flex-grow-1` pattern
- **Bottom Spacing**: `pb-4` class on main content wrapper (24px padding)
- **Conditional Footer**: Footer can be hidden via `showFooter` variable (e.g., on login page)
- **Dynamic Theme**: Theme is controlled via `{{ theme }}` variable

## Footer Navigation

**File**: `templates/partials/footer.twig`

The footer component includes an embedded style block that forces dark styling in light mode (ensuring footer matches the dark navbar), followed by a sticky footer with centered flexbox links to project resources (GitHub repository, Wiki documentation, Issue reporting). Each link uses Bootstrap Icons, external link safety attributes (`rel="noopener noreferrer"`), responsive wrapping on mobile (`flex-wrap`), and muted text styling (`text-body-secondary`). The footer is conditionally hidden on the login page via the `showFooter` variable.

### Footer Features

- **Sticky Positioning**: Uses `mt-auto` with flexbox container
- **Responsive Design**: `flex-wrap` ensures links stack on mobile
- **External Link Safety**: `rel="noopener noreferrer"` for security
- **Bootstrap Icons**: Prefixes each link with an icon
- **Conditional Display**: Hidden on login page via `showFooter` variable

## Dark Mode

Pathary uses Bootstrap 5's built-in dark mode with `data-bs-theme` attribute:

```html
<html data-bs-theme="dark">
```

The theme can be dynamically set per-user or globally.

### Custom Colors

**File**: `templates/public/movie_detail.twig` (and other pages)

```css
:root {
    --pathe-yellow: #f5c518;
    --pathe-dark: #1a1a1a;
    --accent-purple: #6f2dbd;
}
```

## Navbar

### Authenticated Navbar

**File**: `templates/component/navbar.html.twig`

The authenticated navbar uses a simple dark design without responsive expansion/collapse. It displays the application logo and name on the left, and on the right shows an "Add movie" button (for logged-in users) followed by a 3-dots dropdown menu. The dropdown contains navigation links with active state highlighting using regex pattern matching (Dashboard, History, Watchlist, All Movies, Top Actors, Top Directors), a dark mode toggle switch with sun/moon icons, and logout functionality. The navbar uses inline border styles (`style="border-color: #9d9d9d"`) on certain elements and includes the logo styling CSS in `public/css/global.css` (32px height, auto width, object-fit contain).

## Login Page

**File**: `templates/page/login.html.twig`

Features:
- Centered logo with shadow border
- Animated falling popcorn background
- Dark theme

### Logo Container

```css
.login-logo-container {
    position: relative;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
}

.login-logo-container::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    padding: 4px;
    background: linear-gradient(135deg, var(--pathe-yellow), var(--accent-purple));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    box-shadow: 0 0 20px rgba(245, 197, 24, 0.3);
}
```

### Popcorn Animation

**File**: `public/js/login-bg.js`

Creates falling popcorn emoji animation on the login page.

## Movie Grid

**File**: `templates/partials/movie_grid.twig`

Displays movies in a responsive grid:

```twig
<div class="movie-grid">
    {% for movie in movies %}
        <a href="/movie/{{ movie.movie_id }}" class="movie-card">
            <div class="movie-poster">
                {% if movie.poster_src %}
                    <img src="{{ movie.poster_src }}" alt="{{ movie.title }}">
                {% else %}
                    <div class="poster-placeholder">{{ movie.title }}</div>
                {% endif %}
            </div>
            <div class="movie-title">{{ movie.title }}</div>
            {% if movie.avg_popcorn %}
                <div class="movie-rating">
                    {{ movie.avg_popcorn|number_format(1) }} 🍿
                </div>
            {% endif %}
        </a>
    {% endfor %}
</div>
```

## Movie Detail Page

**File**: `templates/public/movie_detail.twig`

Sections:
1. **Hero**: Poster + basic info
2. **Group Rating**: Average popcorn rating
3. **Your Rating**: Rating form (authenticated users)
4. **Individual Ratings**: All user ratings
5. **Cast & Crew**: Actor/director list

### Inline Styles

The movie detail page includes extensive inline CSS for:
- Hero layout
- Rating cards
- Form styling
- Responsive design

## Rating Widget

**File**: `templates/components/popcorn_rating.twig`

Modes:
- `display`: Read-only popcorn display
- `input`: Interactive rating selector

```twig
{% if mode == 'input' %}
    <div class="popcorn-rating popcorn-rating--input">
        <input type="hidden" name="{{ name }}" value="{{ valueInt }}">
        {% for i in 1..7 %}
            <button type="button"
                    class="popcorn-rating__item {{ i <= valueInt ? 'popcorn-on' : 'popcorn-off' }}"
                    data-value="{{ i }}">
                🍿
            </button>
        {% endfor %}
    </div>
{% else %}
    <div class="popcorn-rating" aria-label="Rating: {{ valueInt }} out of 7">
        {% for i in 1..7 %}
            <span class="popcorn-rating__item {{ i <= valueInt ? 'popcorn-on' : 'popcorn-off' }}">🍿</span>
        {% endfor %}
    </div>
{% endif %}
```

## JavaScript Files

| File | Purpose |
|------|---------|
| `public/js/app.js` | Main application JS |
| `public/js/login.js` | Login form handling |
| `public/js/login-bg.js` | Popcorn animation |
| `public/js/movie.js` | Movie page interactions |
| `public/js/settings-*.js` | Settings page logic |

### Login Form

**File**: `public/js/login.js`

The login form handler uses a `submitCredentials()` function that validates redirect URLs with `getSafeRedirect()` (comparing against `APPLICATION_URL`), makes an API call to `/api/authentication/token` with the `Pathary Web` client identifier, and handles multiple response scenarios. Success (200) redirects to the safe redirect URL. Authentication failures (400) trigger complex 2FA/recovery code logic including TOTP verification, recovery code consumption, trusted device handling, and multiple error states (invalid credentials, device token, TOTP code). Server errors (500+) display generic error messages. The implementation includes separate functions for credential submission, login requests, error display, and safe redirect validation.

## CSS Files

| File | Purpose |
|------|---------|
| `public/css/bootstrap.min.css` | Bootstrap framework |
| `public/css/bootstrap-icons-1.10.2.css` | Icon font |
| `public/css/global.css` | Global custom styles |
| `public/css/login.css` | Login page styles |
| `public/css/movie.css` | Movie page styles |
| `public/css/settings.css` | Settings page styles |

## Responsive Design

Bootstrap breakpoints are used throughout:

```css
@media (max-width: 768px) {
    .rating-form-row {
        flex-direction: column;
        gap: 1rem;
    }

    .rating-form-divider {
        display: none;
    }
}
```

## Icons

Bootstrap Icons (version 1.10.2) are used throughout the application via CSS classes.

**File**: `public/css/bootstrap-icons-1.10.2.css`

### Common Icons

| Icon Class | Unicode | Usage |
|------------|---------|-------|
| `bi-calendar-event` | \f1d5 | Date picker, watch dates |
| `bi-geo-alt` | \f3f2 | Location picker |
| `bi-trash` | \f5de | Delete actions |
| `bi-search` | \f52a | Search functionality |
| `bi-github` | \f3ed | GitHub links in footer |
| `bi-book` | \f2ff | Wiki documentation links |
| `bi-bug` | \f337 | Issue reporting |

### Security Page Icons

| Icon Class | Unicode | Usage |
|------------|---------|-------|
| `bi-key` | \f474 | Password management |
| `bi-lock-fill` | \f4aa | 2FA enabled |
| `bi-unlock-fill` | \f686 | 2FA disabled |
| `bi-life-preserver` | \f49b | Recovery codes |
| `bi-laptop` | \f484 | Trusted devices |
| `bi-activity` | \f200 | Security audit log |

### Profile Page Icons

| Icon Class | Unicode | Usage |
|------------|---------|-------|
| `bi-person-circle` | \f4da | Profile photo |
| `bi-person-badge` | \f4cc | Display name |
| `bi-envelope` | \f32f | Email address |
| `bi-shield-lock` | \f52f | Security settings tab |
| `bi-box-arrow-right` | \f2e5 | Sign out |

### Usage Example

```html
<!-- Basic icon -->
<i class="bi bi-calendar-event"></i>

<!-- Icon with margin -->
<i class="bi bi-github me-1"></i>GitHub

<!-- Icon in button -->
<button class="btn btn-primary">
    <i class="bi bi-search me-2"></i>Search Movies
</button>

<!-- Icon with color -->
<i class="bi bi-lock-fill text-success"></i> 2FA Enabled
```

## Related Pages

- [Ratings and Comments](../features/ratings-and-comments.md) - Rating UI components
- [Routing and Controllers](routing-and-controllers.md) - Template rendering
- [Architecture](architecture.md) - Twig integration
