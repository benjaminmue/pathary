#!/usr/bin/env bash
# =============================================================================
# End-to-end smoke test against the production image
# =============================================================================
# Starts MySQL 8.0 and the production image (root Dockerfile) on a private
# Docker network and drives a fresh installation over HTTP:
#
#   /healthz -> selftest + cron commands -> / redirects to /init -> init wizard
#   creates the admin (incl. TOTP) -> login -> authenticated page -> security
#   headers -> TRUSTED_PROXIES handling of X-Forwarded-For/-Proto
#
# No TMDB key is needed: TMDB_API_KEY is a dummy and no step calls TMDB.
#
# Usage:  scripts/e2e.sh            (builds the image from this checkout)
#         E2E_IMAGE=pathary:dev E2E_BUILD=0 scripts/e2e.sh
#
# Requires docker, curl, jq, openssl. Everything it creates carries the
# E2E_PREFIX name and is removed on exit (E2E_KEEP=1 keeps it for debugging).
set -euo pipefail

E2E_PREFIX="${E2E_PREFIX:-pathary-e2e}"
E2E_IMAGE="${E2E_IMAGE:-${E2E_PREFIX}:local}"
E2E_BUILD="${E2E_BUILD:-1}"
E2E_PORT="${E2E_PORT:-18080}"
E2E_SUBNET_PREFIX="${E2E_SUBNET_PREFIX:-172.30.99}"
E2E_KEEP="${E2E_KEEP:-0}"

NETWORK="${E2E_PREFIX}-net"
MYSQL="${E2E_PREFIX}-mysql"
APP="${E2E_PREFIX}-app"
BASE_URL="http://127.0.0.1:${E2E_PORT}"
# A container with this address plays the reverse proxy (TRUSTED_PROXIES),
# a second one an untrusted client that sends the same headers.
PROXY_IP="${E2E_SUBNET_PREFIX}.10"
UNTRUSTED_IP="${E2E_SUBNET_PREFIX}.11"

ADMIN_EMAIL="admin@e2e.invalid"
ADMIN_NAME="e2eadmin"
# Throwaway credentials for a throwaway database, generated per run.
ADMIN_PASSWORD="E2e-$(openssl rand -hex 8)-Aa1!"
DB_PASSWORD="$(openssl rand -hex 16)"

WORK_DIR="$(mktemp -d)"
COOKIES="${WORK_DIR}/cookies.txt"
PASSED=0

log() { printf '[e2e] %s\n' "$*"; }
pass() { PASSED=$((PASSED + 1)); printf '[e2e] PASS %s\n' "$*"; }
fail() {
    printf '[e2e] FAIL %s\n' "$*" >&2
    printf '[e2e] --- last app log lines ---\n' >&2
    docker logs --tail 40 "$APP" >&2 2>&1 || true
    exit 1
}

cleanup() {
    local status=$?
    if [ "$E2E_KEEP" = "1" ]; then
        log "E2E_KEEP=1: leaving $APP, $MYSQL and $NETWORK running"
    else
        docker rm -f "$APP" "$MYSQL" >/dev/null 2>&1 || true
        docker network rm "$NETWORK" >/dev/null 2>&1 || true
    fi
    rm -rf "$WORK_DIR"
    exit "$status"
}
trap cleanup EXIT

# curl wrapper: shares one cookie jar, never follows redirects, fails only on
# transport errors so each step can assert the status itself.
http() {
    curl -sS --max-time 20 -b "$COOKIES" -c "$COOKIES" "$@"
}

# -----------------------------------------------------------------------------
# Stack
# -----------------------------------------------------------------------------
build_image() {
    if [ "$E2E_BUILD" = "1" ]; then
        log "Building production image $E2E_IMAGE"
        docker build -q -t "$E2E_IMAGE" "$(cd "$(dirname "$0")/.." && pwd)" >/dev/null
    fi
}

start_stack() {
    docker rm -f "$APP" "$MYSQL" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    docker network create --subnet "${E2E_SUBNET_PREFIX}.0/24" "$NETWORK" >/dev/null

    log "Starting MySQL 8.0"
    docker run -d --name "$MYSQL" --network "$NETWORK" \
        -e MYSQL_RANDOM_ROOT_PASSWORD=yes \
        -e MYSQL_DATABASE=pathary \
        -e MYSQL_USER=pathary \
        -e MYSQL_PASSWORD="$DB_PASSWORD" \
        mysql:8.0 >/dev/null

    local i
    for i in $(seq 1 60); do
        if docker exec "$MYSQL" mysql -upathary -p"$DB_PASSWORD" -e 'SELECT 1' pathary >/dev/null 2>&1; then
            break
        fi
        [ "$i" -eq 60 ] && fail "MySQL did not become ready"
        sleep 2
    done

    log "Starting app ($E2E_IMAGE) on $BASE_URL"
    docker run -d --name "$APP" --network "$NETWORK" -p "127.0.0.1:${E2E_PORT}:80" \
        -e APPLICATION_URL="http://localhost:${E2E_PORT}" \
        -e TMDB_API_KEY=dummy \
        -e DATABASE_MODE=mysql \
        -e DATABASE_MYSQL_HOST="$MYSQL" \
        -e DATABASE_MYSQL_PORT=3306 \
        -e DATABASE_MYSQL_NAME=pathary \
        -e DATABASE_MYSQL_USER=pathary \
        -e DATABASE_MYSQL_PASSWORD="$DB_PASSWORD" \
        -e ENCRYPTION_KEY="$(openssl rand -base64 32)" \
        -e TRUSTED_PROXIES="$PROXY_IP" \
        "$E2E_IMAGE" >/dev/null
}

wait_for_healthz() {
    local i code
    for i in $(seq 1 60); do
        code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$BASE_URL/healthz" || true)
        if [ "$code" = "200" ]; then
            pass "GET /healthz returned 200 after startup"
            return 0
        fi
        sleep 2
    done
    fail "/healthz did not return 200 within 120s (last status: ${code:-none})"
}

# -----------------------------------------------------------------------------
# Console checks (inside the container, as the web server user)
# -----------------------------------------------------------------------------
check_console_commands() {
    local output
    output=$(docker exec -u www-data "$APP" php bin/console.php selftest 2>&1) || {
        printf '%s\n' "$output" >&2
        fail "selftest exited non-zero"
    }
    printf '%s\n' "$output" | sed 's/^/[e2e]   /'
    pass "selftest passed"

    docker exec -u www-data "$APP" php bin/console.php maintenance:prune >/dev/null || fail "maintenance:prune failed"
    pass "maintenance:prune ran"

    docker exec -u www-data "$APP" php bin/console.php oauth:monitor >/dev/null || fail "oauth:monitor failed"
    pass "oauth:monitor ran (no OAuth configured)"
}

# -----------------------------------------------------------------------------
# HTTP flow. Init and login are separate functions because their request shape
# (CSRF token, 2FA redirect) is expected to change; adapt only these.
# -----------------------------------------------------------------------------
check_fresh_install_redirects_to_init() {
    local headers
    headers=$(http -o /dev/null -D - "$BASE_URL/")
    printf '%s' "$headers" | grep -qE '^HTTP/[0-9.]+ 303' || fail "GET / on a fresh DB is not a 303"
    printf '%s' "$headers" | grep -qiE '^location: .*/init' || fail "GET / does not redirect to /init"
    pass "GET / on a fresh database redirects (303) to /init"

    [ "$(http -o /dev/null -w '%{http_code}' "$BASE_URL/init")" = "200" ] || fail "GET /init is not 200"
    pass "GET /init returned 200"
}

# Prints a 6-digit TOTP code for an otpauth:// URI, computed with the app's own
# OTP library so period/digits/algorithm always match. Waits for the next time
# step when the code starts with 0: the token endpoint casts the code to int and
# would drop the leading zero.
totp_code() {
    local uri="$1" code
    while true; do
        code=$(docker exec "$APP" php -r 'require "/app/vendor/autoload.php"; echo OTPHP\Factory::loadFromProvisioningUri($argv[1])->now();' "$uri")
        case "$code" in
            0*) sleep 5 ;;
            *) printf '%s' "$code"; return 0 ;;
        esac
    done
}

init_post() {
    http -X POST -H 'Content-Type: application/json' --data "$1" "$BASE_URL/init/create-admin"
}

run_init_wizard() {
    local response totp_uri
    response=$(init_post "$(jq -nc --arg e "$ADMIN_EMAIL" --arg n "$ADMIN_NAME" --arg p "$ADMIN_PASSWORD" \
        '{step: "create_account", email: $e, name: $n, password: $p, passwordConfirmation: $p}')")
    [ "$(printf '%s' "$response" | jq -r '.success // empty')" = "true" ] || fail "init create_account: $response"

    response=$(init_post '{"step":"setup_totp"}')
    totp_uri=$(printf '%s' "$response" | jq -r '.totpUri // empty')
    [ -n "$totp_uri" ] || fail "init setup_totp returned no totpUri"
    printf '%s' "$totp_uri" > "${WORK_DIR}/totp_uri"

    response=$(init_post "$(jq -nc --arg c "$(totp_code "$totp_uri")" '{step: "verify_totp", code: $c}')")
    [ "$(printf '%s' "$response" | jq -r '.recoveryCodes | length')" = "10" ] || fail "init verify_totp: $(printf '%s' "$response" | jq -c 'del(.recoveryCodes)')"

    response=$(init_post '{"step":"acknowledge_codes"}')
    [ "$(printf '%s' "$response" | jq -r '.redirectUrl // empty')" = "/login" ] || fail "init acknowledge_codes: $response"
    pass "init wizard created the admin with TOTP"

    [ "$(http -o /dev/null -w '%{http_code}' "$BASE_URL/init")" != "200" ] || fail "/init is still reachable after setup"
    pass "/init is closed after setup"
}

login() {
    local csrf body code
    # The login page sets the session cookie and carries the CSRF token.
    csrf=$(http "$BASE_URL/login" | sed -n 's/.*<meta name="csrf-token" content="\([^"]*\)".*/\1/p' | head -n1)

    body=$(jq -nc --arg e "$ADMIN_EMAIL" --arg p "$ADMIN_PASSWORD" --arg t "$(totp_code "$(cat "${WORK_DIR}/totp_uri")")" --arg c "$csrf" \
        '{email: $e, password: $p, totpCode: $t, rememberMe: false, _csrf_token: $c}')
    code=$(http -o "${WORK_DIR}/login.json" -w '%{http_code}' -X POST \
        -H 'Content-Type: application/json' -H 'X-Movary-Client: Pathary Web' \
        --data "$body" "$BASE_URL/api/authentication/token")
    [ "$code" = "200" ] || fail "login returned $code: $(jq -c 'del(.authToken)' "${WORK_DIR}/login.json" 2>/dev/null)"
    pass "login with password and TOTP returned 200"
}

check_authenticated_page() {
    local code
    code=$(http -o /dev/null -w '%{http_code}' "$BASE_URL/movies")
    [ "$code" = "200" ] || fail "GET /movies with the session returned $code"
    pass "GET /movies with the session returned 200"

    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$BASE_URL/movies")
    [ "$code" != "200" ] || fail "GET /movies without a session returned 200"
    pass "GET /movies without a session is rejected ($code)"
}

check_security_headers() {
    local headers
    headers=$(http -o /dev/null -D - "$BASE_URL/login")
    printf '%s' "$headers" | grep -qi '^content-security-policy: ' || fail "Content-Security-Policy missing"
    printf '%s' "$headers" | grep -qi '^x-frame-options: ' || fail "X-Frame-Options missing"
    printf '%s' "$headers" | grep -qi '^x-content-type-options: nosniff' || fail "X-Content-Type-Options missing"
    if printf '%s' "$headers" | grep -qi '^x-powered-by:'; then
        fail "X-Powered-By is exposed"
    fi
    pass "security headers present, no X-Powered-By"
}

check_healthz_body() {
    local body
    body=$(curl -sS --max-time 10 "$BASE_URL/healthz")
    [ "$(printf '%s' "$body" | jq -r '.status')" = "ok" ] || fail "/healthz body: $body"
    [ "$(printf '%s' "$body" | jq -r '[.checks[]] | all(. == "ok")')" = "true" ] || fail "/healthz checks: $body"
    if curl -sS -o /dev/null -D - --max-time 10 "$BASE_URL/healthz" | grep -qi '^set-cookie:'; then
        fail "/healthz starts a session"
    fi
    pass "/healthz reports all checks ok and sets no cookie"
}

# Sends X-Forwarded-For/-Proto from a container with the given address and
# prints the response headers of /login (the session cookie shows whether the
# app saw HTTPS).
request_via() {
    docker run --rm --network "$NETWORK" --ip "$1" --entrypoint curl "$E2E_IMAGE" \
        -sS -o /dev/null -D - --max-time 10 \
        -H 'X-Forwarded-For: 203.0.113.7' -H 'X-Forwarded-Proto: https' \
        "http://${APP}/login?e2e=$2"
}

check_trusted_proxies() {
    local headers
    headers=$(request_via "$PROXY_IP" trusted)
    printf '%s' "$headers" | grep -iE '^set-cookie: ' | grep -qi 'secure' \
        || fail "request from the trusted proxy with X-Forwarded-Proto: https did not get a Secure cookie"
    docker logs "$APP" 2>&1 | grep -q '^203\.0\.113\.7 .*e2e=trusted' \
        || fail "access log does not show the X-Forwarded-For client IP for the trusted proxy"
    pass "trusted proxy: client IP taken from X-Forwarded-For, HTTPS detected"

    headers=$(request_via "$UNTRUSTED_IP" untrusted)
    if printf '%s' "$headers" | grep -iE '^set-cookie: ' | grep -qi 'secure'; then
        fail "untrusted client could switch the app to HTTPS via X-Forwarded-Proto"
    fi
    docker logs "$APP" 2>&1 | grep -q "^${UNTRUSTED_IP//./\\.} .*e2e=untrusted" \
        || fail "access log does not show the untrusted peer address"
    pass "untrusted client: X-Forwarded-For/-Proto ignored"
}

main() {
    for tool in docker curl jq openssl; do
        command -v "$tool" >/dev/null || { echo "missing tool: $tool" >&2; exit 1; }
    done

    build_image
    start_stack
    wait_for_healthz
    check_console_commands
    check_fresh_install_redirects_to_init
    run_init_wizard
    login
    check_authenticated_page
    check_security_headers
    check_healthz_body
    check_trusted_proxies

    log "All $PASSED checks passed."
}

main "$@"
