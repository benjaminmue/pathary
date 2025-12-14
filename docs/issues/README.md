# GitHub Issues - Manual Creation Guide

The audit found issues that should be tracked. Since GitHub CLI (`gh`) is not available,
use these commands to create the issues manually.

## Prerequisites

1. Install GitHub CLI: https://cli.github.com/
2. Authenticate: `gh auth login`

## Commands to Create Issues

### 1. Security Audit Findings

```bash
gh issue create \
  --title "Security audit findings" \
  --label "security" \
  --body-file docs/issues/security-audit-findings.md
```

### 2. Performance Audit Findings

```bash
gh issue create \
  --title "Performance audit findings" \
  --label "performance" \
  --body-file docs/issues/performance-audit-findings.md
```

### 3. General Improvements Backlog

```bash
gh issue create \
  --title "General improvements backlog" \
  --label "enhancement" \
  --body-file docs/issues/general-improvements-backlog.md
```

## Alternative: Manual Web Creation

If you prefer to create issues via the GitHub web interface:

1. Go to: https://github.com/benjaminkomen/movary/issues/new
2. Copy the content from each `.md` file in this directory
3. Set appropriate labels

## Files in This Directory

- `security-audit-findings.md` - 4 security findings (1 Medium, 3 Low)
- `performance-audit-findings.md` - 4 performance findings
- `general-improvements-backlog.md` - 5 improvement suggestions
- `README.md` - This file

## Full Audit Report

See `../audit-report.txt` for the complete audit report with all findings.
