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

```twig
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}Pathary{% endblock %}</title>

    <!-- Bootstrap CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/bootstrap-icons-1.10.2.css" rel="stylesheet">

    {% block styles %}{% endblock %}
</head>
<body>
    {% block navbar %}{% endblock %}

    <main class="container py-4">
        {% block content %}{% endblock %}
    </main>

    <!-- Bootstrap JS -->
    <script src="/js/bootstrap.bundle.min.js"></script>
    {% block scripts %}{% endblock %}
</body>
</html>
```

## Dark Mode

Pathary uses Bootstrap 5's built-in dark mode with `data-bs-theme="dark"`:

```html
<html data-bs-theme="dark">
```

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

Bootstrap Icons are used via CSS classes:

```html
<i class="bi bi-calendar-event"></i>  <!-- Calendar -->
<i class="bi bi-geo-alt"></i>          <!-- Location pin -->
<i class="bi bi-trash"></i>            <!-- Delete -->
<i class="bi bi-search"></i>           <!-- Search -->
```

## Related Pages

- [Ratings and Comments](Ratings-and-Comments.md) - Rating UI components
- [Routing and Controllers](Routing-and-Controllers.md) - Template rendering
- [Architecture](Architecture.md) - Twig integration

---

[← Back to Wiki Home](README.md)
