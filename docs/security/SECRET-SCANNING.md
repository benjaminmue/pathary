# Secret Scanning and Pre-Commit Hooks

This document explains how to prevent accidental commits of API keys, passwords, and other sensitive data in the Pathary repository.

## Overview

The repository includes a secret scanning tool (`scripts/secret-scan.sh`) that automatically detects:
- Hardcoded API keys (TMDB, GitHub, AWS)
- Passwords and authentication tokens
- Database connection strings
- Private keys
- Tracked environment files

## Quick Start

### Running the Scanner Manually

```bash
# Scan for secrets in tracked files
./scripts/secret-scan.sh

# Verbose mode (shows skipped files)
./scripts/secret-scan.sh --verbose

# Run self-test to verify scanner works
./scripts/secret-scan.sh --self-test
```

The scanner will:
- ✅ Check for tracked .env files (except .env.example)
- ✅ Detect hardcoded API keys, passwords, tokens
- ✅ Find untracked secret files not in .gitignore
- ✅ Exit with code 1 if secrets are found (CI-friendly)

## Installing Pre-Commit Hook (Recommended)

To automatically scan for secrets before every commit:

```bash
# Create the pre-commit hook
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash
# Pathary Pre-Commit Secret Scanner

echo "Running secret scanner..."
./scripts/secret-scan.sh

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Commit blocked: Secrets detected!"
    echo "Fix the issues above before committing."
    exit 1
fi

echo "✅ No secrets detected"
exit 0
EOF

# Make it executable
chmod +x .git/hooks/pre-commit
```

### Installing Pre-Push Hook (Optional Extra Safety)

For an additional safety net before pushing:

```bash
# Create the pre-push hook
cat > .git/hooks/pre-push << 'EOF'
#!/bin/bash
# Pathary Pre-Push Secret Scanner

echo "Running final secret scan before push..."
./scripts/secret-scan.sh

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Push blocked: Secrets detected!"
    echo "Fix the issues above before pushing."
    exit 1
fi

echo "✅ Safe to push"
exit 0
EOF

# Make it executable
chmod +x .git/hooks/pre-push
```

## What Files Are Protected

The `.gitignore` file prevents these from being committed:
- `.env`, `.env.local`, `.env.*.local` - Environment variables
- `docker-compose.local.yml` - Local docker configs with hardcoded credentials
- `http-client.env.json` - HTTP client test credentials
- `*.sql`, `*.dump`, `*.backup` - Database dumps
- `*.log` - Log files that might contain secrets

## Patterns Detected

The scanner looks for these patterns:

| Pattern | Description | Example |
|---------|-------------|---------|
| `TMDB_API_KEY` | TMDB API keys | `TMDB_API_KEY="abc123..."` |
| `API_KEY` | Generic API keys | `API_KEY="longkey123"` |
| `GITHUB_TOKEN` | GitHub tokens | `ghp_xxxx` or `github_pat_xxxx` |
| `AWS_KEY` | AWS access keys | `AKIA...` |
| `PRIVATE_KEY` | SSH/TLS keys | `BEGIN PRIVATE KEY` |
| `PASSWORD_HARDCODED` | Hardcoded passwords | `PASSWORD="secret123"` |
| `BEARER_TOKEN` | Bearer tokens | `Authorization: Bearer xxx` |
| `DATABASE_URL` | Database URLs | `mysql://user:pass@host` |

## Remediation Steps

### If Secrets Are Found in Tracked Files

1. **Untrack the file without deleting it:**
   ```bash
   git rm --cached <file>
   ```

2. **Add to .gitignore:**
   ```bash
   echo "<file>" >> .gitignore
   ```

3. **Commit the changes:**
   ```bash
   git add .gitignore
   git commit -m "chore: untrack file with secrets"
   ```

### For Hardcoded Secrets in Code

1. **Replace with environment variable references:**
   ```php
   // Before (BAD)
   $apiKey = "abc123def456";

   // After (GOOD)
   $apiKey = getenv('TMDB_API_KEY');
   ```

2. **Move actual values to `.env.local`:**
   ```bash
   # Create .env.local (never committed)
   echo "TMDB_API_KEY=your_actual_key_here" > .env.local
   ```

3. **Use environment variable pattern in docker-compose:**
   ```yaml
   # Good - uses environment variable with fallback
   TMDB_API_KEY: "${TMDB_API_KEY:-XXXXX}"

   # Bad - hardcoded value
   TMDB_API_KEY: "abc123def456"
   ```

### If Secrets Were Already Committed

**⚠️ Important:** Once a secret is committed to git history, consider it compromised.

1. **Rotate/regenerate the secret immediately** (get a new API key, change password, etc.)

2. **Remove from git history** (advanced):
   ```bash
   # Using git-filter-repo (recommended)
   git filter-repo --path <file-with-secret> --invert-paths

   # Or using BFG Repo-Cleaner
   bfg --delete-files <file-with-secret>
   ```

3. **Force push** (coordinate with team):
   ```bash
   git push --force-with-lease
   ```

## Uninstalling Hooks

```bash
# Remove pre-commit hook
rm .git/hooks/pre-commit

# Remove pre-push hook
rm .git/hooks/pre-push
```

## CI/CD Integration

The scanner can be used in GitHub Actions:

```yaml
- name: Scan for secrets
  run: |
    ./scripts/secret-scan.sh
    if [ $? -ne 0 ]; then
      echo "::error::Secrets detected in repository"
      exit 1
    fi
```

## False Positives

The scanner may flag test fixtures or example files. To exclude:

1. **Name files with `.example`, `.sample`, or `.template` suffix**
   - These are automatically excluded

2. **For test passwords:**
   - Use obviously fake values: `password123`, `dummy_key`, `test_password`
   - The scanner excludes these patterns

3. **Update the scanner exclusions:**
   - Edit `scripts/secret-scan.sh` if needed
   - Add patterns to the exclusion lists

## Support

For issues or questions:
- Review this documentation
- Check the [Security Audit Report](./secret-audit-report.txt)
- Run `./scripts/secret-scan.sh --self-test` to verify functionality
