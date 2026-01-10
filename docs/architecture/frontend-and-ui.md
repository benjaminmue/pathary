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
│   ├── navbar_app.twig         # Authenticated navbar
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

All pages use a flexbox sticky footer pattern with consistent bottom spacing:

```twig
<!DOCTYPE html>
<html data-bs-theme="{{ theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}Pathary{% endblock %} | {{ applicationName }}</title>

    <!-- Bootstrap CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/bootstrap-icons-1.10.2.css" rel="stylesheet">

    {% block styles %}{% endblock %}
</head>
<body class="d-flex flex-column min-vh-100">
    {% block navbar %}{% endblock %}

    <div class="flex-grow-1 pb-4">
        {% block body %}{% endblock %}
    </div>

    {% if showFooter is not defined or showFooter %}
        {% include 'partials/footer.twig' %}
    {% endif %}

    <!-- Bootstrap JS -->
    <script src="/js/bootstrap.bundle.min.js"></script>
    {% block scripts %}{% endblock %}
</body>
</html>
```

### Layout Features

- **Sticky Footer**: Uses `min-vh-100` + `flex-column` + `flex-grow-1` pattern
- **Bottom Spacing**: `pb-4` class on main content wrapper (24px padding)
- **Conditional Footer**: Footer can be hidden via `showFooter` variable (e.g., on login page)
- **Dynamic Theme**: Theme is controlled via `{{ theme }}` variable

## Footer Navigation

**File**: `templates/partials/footer.twig`

A sticky footer is included on all pages (except login) with links to project resources:

```twig
<footer class="footer mt-auto py-3 bg-body-tertiary border-top">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-center gap-3 small">
            <a href="https://github.com/benjaminmue/pathary" target="_blank" rel="noopener noreferrer"
               class="text-body-secondary text-decoration-none">
                <i class="bi bi-github me-1"></i>GitHub
            </a>
            <a href="https://github.com/benjaminmue/pathary/wiki" target="_blank" rel="noopener noreferrer"
               class="text-body-secondary text-decoration-none">
                <i class="bi bi-book me-1"></i>Wiki
            </a>
            <a href="https://github.com/benjaminmue/pathary/issues/new" target="_blank" rel="noopener noreferrer"
               class="text-body-secondary text-decoration-none">
                <i class="bi bi-bug me-1"></i>Report Issue
            </a>
        </div>
    </div>
</footer>
```

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

**File**: `templates/component/navbar_app.twig`

Features:
- Logo with SVG image
- Search button
- User dropdown
- Dark theme styling

```twig
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/">
            <img src="/images/logo.svg" alt="Pathary" class="navbar-logo me-2">
            {{ applicationName }}
        </a>

        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/movies">All Movies</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ currentUser.name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile">Profile</a></li>
                        <li><a class="dropdown-item" href="/settings/account/general">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
```

### Logo Styling

**File**: `public/css/global.css`

```css
.navbar-logo {
    width: auto;
    height: 32px;
    object-fit: contain;
}
```

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

```javascript
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const response = await fetch('/api/authentication/token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Movary-Client': 'pathary-web',
        },
        body: JSON.stringify({
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            rememberMe: document.getElementById('rememberMe').checked,
        }),
    });

    if (response.ok) {
        window.location.href = '/';
    } else {
        showError('Invalid credentials');
    }
});
```

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

- [Ratings and Comments](Ratings-and-Comments)] - Rating UI components
- [Routing and Controllers](Routing-and-Controllers)] - Template rendering
- [Architecture](Architecture)] - Twig integration

---

[← Back to Wiki Home](Home)
