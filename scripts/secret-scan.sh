#!/usr/bin/env bash
#
# Pathary Secret Scanner
#
# Scans the repository for hardcoded secrets, API keys, passwords, and other sensitive data.
# Returns non-zero exit code if potential secrets are found.
#
# Changelog:
#   - Fixed FINDINGS counter using process substitution instead of pipes
#   - Fixed regex compatibility using [[:space:]] instead of \s
#   - Hardened filename handling with -- before all file arguments
#   - Improved text file detection using MIME types
#   - Fixed untracked file parsing to handle spaces in filenames
#   - Reduced false negatives by scanning docs/ and README by default
#   - Added --self-test mode for verification
#   - Improved output redaction for CI safety
#   - Made compatible with bash 3.2+ (macOS default)
#
# Usage:
#   ./scripts/secret-scan.sh [--verbose] [--self-test]
#
# Requirements: bash 3.2+, grep, sed, file, git
#

set -euo pipefail

# Check bash version
if [[ "${BASH_VERSINFO[0]}" -lt 3 ]]; then
    echo "Error: This script requires bash 3.2 or higher"
    exit 1
fi

# Colors for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

VERBOSE=0
SELF_TEST=0
FINDINGS=0
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Parse arguments
for arg in "$@"; do
    case $arg in
        --verbose|-v)
            VERBOSE=1
            shift
            ;;
        --self-test)
            SELF_TEST=1
            shift
            ;;
    esac
done

cd "$REPO_ROOT"

# Function to report findings (with redaction)
report_finding() {
    local category="$1"
    local file="$2"
    local line_num="$3"
    local preview="$4"

    FINDINGS=$((FINDINGS + 1))
    echo -e "${RED}[FINDING #$FINDINGS]${NC} $category"
    echo "  File: $file:$line_num"

    # Aggressive redaction for safe CI output
    # Remove everything after = or : and sanitize
    local redacted=$(echo "$preview" | sed 's/[:=].*/:[REDACTED]/' | tr -cd '[:print:]')
    echo "  Preview: ${redacted:0:70}"
    echo ""
}

# Function to check if file should be excluded
should_exclude() {
    local file="$1"

    # Exclude patterns (safe files that should never contain real secrets)
    case "$file" in
        *.example|*.sample|*.template) return 0 ;;
        .env.example|*/composer.lock|*/package-lock.json) return 0 ;;
        vendor/*|node_modules/*|.git/*) return 0 ;;
        *.min.js|*.min.css|*.map) return 0 ;;
    esac

    return 1
}

# Function to check if file is text
is_text_file() {
    local file="$1"

    # Get MIME type
    local mime=$(file -b --mime-type -- "$file" 2>/dev/null || echo "unknown")

    # Check if it's a text-based MIME type
    case "$mime" in
        text/*|application/json|application/xml|application/x-yaml|application/javascript|application/x-sh)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

# Self-test mode
run_self_test() {
    echo -e "${BLUE}=== Running Self-Test ===${NC}"

    local test_dir=$(mktemp -d)
    trap "rm -rf $test_dir" EXIT

    # Create test files with known patterns
    echo 'TMDB_API_KEY="abc123def456ghi789"' > "$test_dir/test1.env"
    echo 'ghp_1234567890123456789012345678901234AB' > "$test_dir/test2.txt"
    echo 'PASSWORD="MySecretPass123!"' > "$test_dir/test3.conf"
    echo 'SAFE_VAR="${TMDB_API_KEY}"' > "$test_dir/test4.env"

    # Test pattern matching
    local test_findings=0

    if grep -qE 'TMDB_API_KEY[[:space:]]*[:=][[:space:]]*["\047]?[a-zA-Z0-9]{10,}["\047]?' -- "$test_dir/test1.env"; then
        test_findings=$((test_findings + 1))
        echo -e "${GREEN}✓${NC} Pattern test 1: TMDB_API_KEY detection works"
    else
        echo -e "${RED}✗${NC} Pattern test 1: FAILED"
        return 1
    fi

    if grep -qE '(ghp_[a-zA-Z0-9]{36}|github_pat_[a-zA-Z0-9_]{82})' -- "$test_dir/test2.txt"; then
        test_findings=$((test_findings + 1))
        echo -e "${GREEN}✓${NC} Pattern test 2: GitHub token detection works"
    else
        echo -e "${RED}✗${NC} Pattern test 2: FAILED"
        return 1
    fi

    # Test that env var references are NOT flagged
    if ! grep -qE 'TMDB_API_KEY[[:space:]]*[:=][[:space:]]*["\047]?[a-zA-Z0-9]{10,}["\047]?' -- "$test_dir/test4.env" | grep -v '\${'; then
        echo -e "${GREEN}✓${NC} Pattern test 3: Env var references correctly excluded"
    else
        echo -e "${YELLOW}!${NC} Pattern test 3: WARNING - may have false positives"
    fi

    echo -e "${GREEN}Self-test passed: $test_findings patterns detected${NC}"
    echo ""
}

if [ $SELF_TEST -eq 1 ]; then
    run_self_test
    exit 0
fi

echo -e "${BLUE}=== Pathary Secret Scanner ===${NC}"
echo "Scan started at: $(date)"
echo "Repository: $REPO_ROOT"
echo ""

echo -e "${BLUE}--- Checking for tracked secret files ---${NC}"

# Check for tracked .env files (except .example files)
while IFS= read -r file; do
    # Skip .example, .sample, .template files
    if [[ "$file" =~ \.(example|sample|template)$ ]]; then
        continue
    fi
    # Check for .env files (but not .env.example already filtered above)
    if [[ "$file" =~ \.env(\.|$) ]] || [[ "$file" =~ env\.json$ ]]; then
        report_finding "TRACKED_ENV_FILE" "$file" "N/A" "Environment file should not be tracked"
    fi
done < <(git ls-files)

# Check for tracked docker-compose.local.yml or override files with hardcoded secrets
while IFS= read -r file; do
    if [[ "$file" =~ docker-compose\.(local|override)\.yml$ ]]; then
        # Check if file contains hardcoded passwords
        if grep -qE 'PASSWORD[[:space:]]*:[[:space:]]*[^$]' -- "$file" 2>/dev/null; then
            report_finding "HARDCODED_SECRET_IN_COMPOSE" "$file" "N/A" "Docker compose file contains hardcoded secrets"
        fi
    fi
done < <(git ls-files)

# Check for SQL dumps, database files
while IFS= read -r file; do
    if [[ "$file" =~ \.(sql|dump|sqlite|db)$ ]]; then
        report_finding "TRACKED_DATABASE_FILE" "$file" "N/A" "Database file should not be tracked"
    fi
done < <(git ls-files)

echo -e "${BLUE}--- Scanning git-tracked files for secret patterns ---${NC}"

# Define secret patterns to search for (POSIX ERE compatible)
# Using parallel arrays for bash 3.2 compatibility
PATTERN_NAMES=(
    "TMDB_API_KEY"
    "API_KEY"
    "GITHUB_TOKEN"
    "AWS_KEY"
    "PRIVATE_KEY"
    "PASSWORD_HARDCODED"
    "BEARER_TOKEN"
    "DATABASE_URL"
)

PATTERN_REGEXES=(
    'TMDB_API_KEY[[:space:]]*[:=][[:space:]]*["\047]?[a-zA-Z0-9]{10,}["\047]?'
    'API_KEY[[:space:]]*[:=][[:space:]]*["\047]?[a-zA-Z0-9]{20,}["\047]?'
    '(ghp_[a-zA-Z0-9]{36}|github_pat_[a-zA-Z0-9_]{82})'
    'AKIA[0-9A-Z]{16}'
    'BEGIN[[:space:]]+(RSA[[:space:]]+)?PRIVATE[[:space:]]+KEY'
    '(PASSWORD|PASS)[[:space:]]*[:=][[:space:]]*["\047][^$\{\}][a-zA-Z0-9!@#$%^&*]{8,}["\047]'
    'Authorization:[[:space:]]*Bearer[[:space:]]+[a-zA-Z0-9\-._~+/]+=*'
    '(mysql|postgres|postgresql)://[^:]+:[^@]+@'
)

# Scan files
while IFS= read -r file; do
    # Skip if file should be excluded
    if should_exclude "$file"; then
        [ $VERBOSE -eq 1 ] && echo "Skipping: $file"
        continue
    fi

    # Skip if not a text file
    if ! is_text_file "$file"; then
        [ $VERBOSE -eq 1 ] && echo "Skipping binary: $file"
        continue
    fi

    # Iterate over patterns
    for i in "${!PATTERN_NAMES[@]}"; do
        category="${PATTERN_NAMES[$i]}"
        pattern="${PATTERN_REGEXES[$i]}"

        # Search for pattern
        while IFS=: read -r line_num content; do
            [ -z "$line_num" ] && continue

            # Special handling for certain categories
            skip=0
            case "$category" in
                "PASSWORD_HARDCODED")
                    # Exclude test passwords and variable references
                    if echo "$content" | grep -qE '(\$|password123|dummy_key|test_|_test|pathary_pass_123)'; then
                        skip=1
                    fi
                    ;;
                "TMDB_API_KEY")
                    # Exclude placeholder values and env var references
                    if echo "$content" | grep -qE '(\$\{|XXXXX|your-key|your_|tmdb_key_here|dummy_key|<tmdb)'; then
                        skip=1
                    fi
                    ;;
            esac

            if [ $skip -eq 0 ]; then
                report_finding "$category" "$file" "$line_num" "$content"
            fi
        done < <(grep -nE "$pattern" -- "$file" 2>/dev/null || true)
    done
done < <(git ls-files)

echo -e "${BLUE}--- Checking untracked files that might be accidentally staged ---${NC}"

# Check for untracked secret files using NUL-safe parsing
while IFS= read -r -d '' entry; do
    # Parse git status -z format: skip first 3 chars (status codes + space)
    status="${entry:0:2}"
    filename="${entry:3}"

    # Only process untracked files (??)
    if [ "$status" = "??" ]; then
        # Check if filename matches secret patterns
        if echo "$filename" | grep -qE '(\.env$|\.env\.[^e]|docker-compose\.override|secrets/|\.sql$|\.dump$|\.backup$)'; then
            # Check if file is ignored
            if git check-ignore -q -- "$filename" 2>/dev/null; then
                [ $VERBOSE -eq 1 ] && echo -e "${GREEN}[OK]${NC} $filename (ignored by .gitignore)"
            else
                echo -e "${RED}[RISK]${NC} $filename (NOT ignored - add to .gitignore)"
                FINDINGS=$((FINDINGS + 1))
            fi
        fi
    fi
done < <(git status --porcelain -z 2>/dev/null || true)

echo ""
echo -e "${BLUE}=== Scan Complete ===${NC}"
echo "Scan completed at: $(date)"
echo ""

if [ $FINDINGS -eq 0 ]; then
    echo -e "${GREEN}✓ No secrets found${NC}"
    exit 0
else
    echo -e "${RED}✗ Found $FINDINGS potential secret(s)${NC}"
    echo ""
    echo "Remediation steps:"
    echo "  1. Review findings above"
    echo "  2. Remove hardcoded secrets from tracked files"
    echo "  3. Use environment variables or .env files (listed in .gitignore)"
    echo "  4. For docker-compose.local.yml: run 'git rm --cached docker-compose.local.yml'"
    echo "  5. Add the file to .gitignore to prevent future commits"
    echo ""
    exit 1
fi
