<?php declare(strict_types=1);

namespace Movary\Util;

/**
 * Single source of truth for connection facts that security decisions depend on.
 *
 * Pathary runs behind a TLS-terminating reverse proxy (e.g. Nginx Proxy Manager).
 * Proxy headers such as X-Forwarded-For and X-Forwarded-Proto are client-controlled
 * unless a trusted proxy rewrites them, so they are never read here. Apache's
 * mod_remoteip (configured with the TRUSTED_PROXIES CIDR list in the container image)
 * replaces REMOTE_ADDR with the real client address and sets HTTPS=on only for
 * requests that arrived from a trusted proxy over https.
 */
class RequestContext
{
    /**
     * Client IP address for rate limiting and audit logs.
     *
     * Deliberately REMOTE_ADDR only: parsing X-Forwarded-For here would let any client
     * pick its own address and bypass per-IP rate limits.
     */
    public static function clientIp() : string
    {
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
            return 'unknown';
        }

        return $remoteAddress;
    }

    /**
     * Whether the browser talks to Pathary over https, which decides the Secure cookie
     * flag and HSTS.
     *
     * Behind a TLS-terminating proxy the PHP request itself is plain http, so an
     * APPLICATION_URL starting with https:// also counts: the operator declared that
     * the public entry point is https-only.
     */
    public static function isHttps() : bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return str_starts_with(strtolower(trim(self::findApplicationUrl())), 'https://');
    }

    private static function findApplicationUrl() : string
    {
        $applicationUrl = getenv('APPLICATION_URL');
        if (is_string($applicationUrl) === true && $applicationUrl !== '') {
            return $applicationUrl;
        }

        return $_ENV['APPLICATION_URL'] ?? '';
    }
}
