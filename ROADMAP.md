# Pathary Strategic Roadmap

This roadmap outlines planned features, implementation ideas, and strategic direction for Pathary. It is manually curated and represents the vision for future development.

**For current open issues and bug tracking**, see [OPEN_ISSUES.md](OPEN_ISSUES.md) (auto-generated from GitHub issues).

---

## Current Focus

*Document current development priorities and what you're actively working on*

---

## Planned Features & Enhancements

### Short-term (Next Sprint)

*Implementation ideas ready to start*

- [ ] Center profile and security pages (`/profile`, `/profile/security`)
- [ ] Document popcorn rating system in Wiki

### Medium-term

*Features that need design/planning before implementation*

- [ ] **Movie Poster Lightbox**: Click movie poster to show full-size overlay with close button (X) and click-outside-to-close
- [ ] **Enhanced Admin Movies Page** (`/admin/movies`):
  - Refresh movie posters
  - Edit movie metadata
  - Delete user comments on movies
  - Delete movies
  - Additional movie management features (to be determined)
- [ ] **User Comment Management** (`/admin/users`):
  - View all user comments with delete options
  - Toggle select/deselect all comments
  - Block/unblock users
  - Display user statistics (comment count, movie count)
  - Fix user creation date display
  - Add accordion view for user comments with inline delete
- [ ] **Enhanced Homepage Statistics**:
  - Recently Added (single row only)
  - Most Liked movies (new row)
  - Public statistics for all visitors:
    - Total movies count
    - Total comments count
    - Rating distribution (1-7 with bar chart)
    - Most popular watch locations (Cinema/Home/Other)
  - Visually appealing design with charts/graphs
- [ ] Enable and migrate integrations from `/old/` routes to new routes (Plex, Jellyfin, Trakt, etc.)
  - Create individual Wiki page for each integration
- [ ] **Weekly Admin Statistics Email**:
  - Automated weekly email digest sent to all admins
  - Server statistics: movies added, new users, comments, ratings distribution
  - Growth trends and activity metrics
  - **Primary benefit**: Keeps OAuth refresh token active (prevents 90-day Microsoft 365 inactivity expiration)
  - **Secondary benefit**: Admins stay informed of platform health without logging in
  - Configurable send day/time in admin settings
  - Option to enable/disable per-admin
- [ ] **Monthly User Statistics Email**:
  - Automated monthly personalized digest sent to all users (with email addresses)
  - **Personal statistics**: Movies watched this month/total, ratings given, average rating, watch streak
  - **Platform updates**: Recently added movies (past 30 days), trending movies, top-rated new additions
  - **Engagement**: Recommendations based on user's rating history
  - **OAuth benefit**: Additional monthly email activity to keep refresh token active
  - User preference: opt-in/opt-out in profile settings
  - Admin control: enable/disable feature globally, configure send day of month

### Long-term

*Strategic direction and major features*

- [ ] **GitHub Release Checker** (`/admin/releases`):
  - Auto-check for updates from GitHub repository
  - Show available stable releases, beta releases, and pre-releases
  - Update notification system
- [ ] Mobile app companion
- [ ] Advanced statistics and analytics dashboard

---

## Technical Debt & Refactoring

*Code quality improvements and architectural changes*

- [ ] Example: Complete Movary → Pathary namespace migration (defer to v2.0)
- [ ] Example: Modernize authentication system
- [ ] Example: Update OpenAPI specification

---

## Documentation Priorities

*Documentation that needs to be created or updated*

- [ ] Integration setup guides (Wiki pages for each):
  - Plex integration
  - Jellyfin integration
  - Trakt integration
  - Other media platform integrations
- [ ] Popcorn rating system explanation
- [ ] "For Nerds" technical deep-dive Wiki page
- [ ] Self-hosting best practices

---

## Research & Exploration

*Areas to investigate before committing to implementation*

- [ ] Example: Alternative database backends
- [ ] Example: Performance optimization opportunities
- [ ] Example: Integration with other media platforms

---

## Ideas & Experiments

*Rough ideas that need validation or prototyping*

- User watchlist/wish list feature
- Movie recommendation engine based on user ratings
- Social features (follow users, share lists)
- Export user data (CSV, JSON)
- Public user profiles (optional)
- Movie collections/playlists

---

## Completed Milestones

### v0.5.0-beta.1 (January 3, 2026)
- ✅ OAuth email authentication (Gmail, Microsoft 365)
- ✅ Popcorn rating system (1-7 scale)
- ✅ User invitation system
- ✅ CSRF protection across application
- ✅ Enhanced admin panel
- ✅ Dark mode improvements
- ✅ Mobile-friendly navigation

### v0.1.0-alpha.1 (Earlier)
- ✅ Initial Movary fork
- ✅ Basic movie tracking
- ✅ TMDB integration

---

## How to Use This Roadmap

1. **Add ideas freely** - This is your strategic planning document
2. **Reference in discussions** - Link to specific sections when planning work
3. **Update regularly** - Keep this aligned with your vision
4. **Move to issues** - When ready to implement, create GitHub issues with proper labels
5. **Track progress** - Mark items complete and move to "Completed Milestones"

This roadmap complements the auto-generated issue tracker (OPEN_ISSUES.md) by providing strategic context and long-term vision.
