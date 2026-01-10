# Issue Labels

This page documents the GitHub Issue label taxonomy for Pathary.

> **📌 [View actual labels with colors →](https://github.com/benjaminmue/pathary/labels)**

## Label Requirements

**Every issue MUST have:**
- Exactly **one Type** label
- Exactly **one Priority** label
- At least **one Area** label

**Optional labels:**
- Status labels for workflow tracking

## Label Groups

### Type Labels

Choose exactly one:

| Label | Description |
|:---:|:---:|
| ![bug](images/bug.svg) | Something isn't working correctly |
| ![enhancement](images/enhancement.svg) | New feature or request |
| ![documentation](images/documentation.svg) | Improvements or additions to documentation |
| ![security](images/security.svg) | Security issues or implementations |
| ![performance](images/performance.svg) | Performance optimization issues |
| ![refactor](images/refactor.svg) | Code quality improvements without new features |
| ![chore](images/chore.svg) | Maintenance tasks (dependencies, tooling, CI) |

### Priority Labels

Choose exactly one:

| Label | Description |
|:---:|:---:|
| ![priority-p0](images/priority-p0.svg) | Critical/Blocker - Must be fixed immediately |
| ![priority-p1](images/priority-p1.svg) | High priority - Should be addressed soon |
| ![priority-p2](images/priority-p2.svg) | Medium priority - Normal timeline |
| ![priority-p3](images/priority-p3.svg) | Low priority - Nice to have |

### Area Labels

Choose one or more:

| Label | Description |
|:---:|:---:|
| ![area-auth](images/area-auth.svg) | Authentication & user management |
| ![area-2fa](images/area-2fa.svg) | Two-factor authentication |
| ![area-email](images/area-email.svg) | Email functionality (SMTP, OAuth, notifications) |
| ![area-admin](images/area-admin.svg) | Admin panel & server management |
| ![area-ui](images/area-ui.svg) | User interface & frontend |
| ![area-movies](images/area-movies.svg) | Movie tracking & ratings |
| ![area-tmdb](images/area-tmdb.svg) | TMDB API integration |
| ![area-database](images/area-database.svg) | Database schema & migrations |
| ![area-docker](images/area-docker.svg) | Docker & containerization |
| ![area-api](images/area-api.svg) | API endpoints & integrations |

### Status Labels (Optional)

| Label | Description |
|:---:|:---:|
| ![status-triage](images/status-triage.svg) | Needs initial review and categorization |
| ![status-ready](images/status-ready.svg) | Ready for development |
| ![status-in-progress](images/status-in-progress.svg) | Currently being worked on |
| ![status-blocked](images/status-blocked.svg) | Blocked by dependencies or external factors |
| ![status-needs-info](images/status-needs-info.svg) | Waiting for more information from reporter |

### Community & Generic Labels

| Label | Description |
|:---:|:---:|
| ![good first issue](images/good%20first%20issue.svg) | Good for newcomers |
| ![help wanted](images/help%20wanted.svg) | Extra attention is needed |
| ![duplicate](images/duplicate.svg) | This issue or pull request already exists |
| ![invalid](images/invalid.svg) | This doesn't seem right |
| ![question](images/question.svg) | Further information is requested |
| ![wontfix](images/wontfix.svg) | This will not be worked on |

## Triage Checklist

When creating or triaging an issue:

1. **Pick Type** - What kind of issue? (bug, enhancement, security, etc.)
2. **Pick Priority** - How urgent? (p0, p1, p2, p3)
3. **Pick Area(s)** - Which part of codebase? (auth, ui, database, etc.)
4. **Add Status** (optional) - Where in workflow? (triage, ready, in-progress, blocked)

## Examples

### Security Vulnerability

**Issue**: "XSS vulnerability in user profile form"

**Labels**: ![security](images/security.svg) ![priority-p0](images/priority-p0.svg) ![area-ui](images/area-ui.svg) ![area-auth](images/area-auth.svg) ![status-triage](images/status-triage.svg)

### Feature Request

**Issue**: "Add email notifications for new ratings"

**Labels**: ![enhancement](images/enhancement.svg) ![priority-p2](images/priority-p2.svg) ![area-email](images/area-email.svg) ![area-movies](images/area-movies.svg) ![status-triage](images/status-triage.svg)

### Performance Issue

**Issue**: "Movie list page loads slowly with 1000+ movies"

**Labels**: ![performance](images/performance.svg) ![priority-p1](images/priority-p1.svg) ![area-movies](images/area-movies.svg) ![area-database](images/area-database.svg) ![status-ready](images/status-ready.svg)

### Documentation Update

**Issue**: "Add OAuth setup instructions to wiki"

**Labels**: ![documentation](images/documentation.svg) ![priority-p2](images/priority-p2.svg) ![area-email](images/area-email.svg) ![status-triage](images/status-triage.svg)

## Questions?

If unsure which labels to apply, add ![status-triage](images/status-triage.svg) and a maintainer will review during triage.
