<?php declare(strict_types=1);

namespace Movary\Util;

class TrustedDeviceCookie
{
    private const string COOKIE_NAME = 'pathary_trusted_device';
    private const int EXPIRATION_DAYS = 30;

    /**
     * Detect if the request is HTTPS, respecting reverse proxy headers
     */
    private static function isHttps() : bool
    {
        // Check X-Forwarded-Proto header first (reverse proxy)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
            /** @psalm-suppress RedundantCondition */
            if (is_string($forwardedProto)) {
                return strtolower($forwardedProto) === 'https';
            }
        }

        // Fallback to direct HTTPS detection
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /**
     * Set the trusted device cookie securely
     *
     * @param string $token The trusted device token (will not be logged)
     */
    public static function set(string $token) : void
    {
        $isSecure = self::isHttps();
        $expirationTimestamp = time() + (self::EXPIRATION_DAYS * 24 * 60 * 60);

        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires' => $expirationTimestamp,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );
    }

    /**
     * Clear the trusted device cookie
     */
    public static function clear() : void
    {
        $isSecure = self::isHttps();

        // Clear from PHP superglobal
        unset($_COOKIE[self::COOKIE_NAME]);

        // Send cookie deletion header
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' => 1, // Past timestamp to delete
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );
    }

    /**
     * Get the trusted device cookie value
     *
     * @return string|null The cookie value or null if not set
     */
    public static function get() : ?string
    {
        $value = (string)filter_input(INPUT_COOKIE, self::COOKIE_NAME);

        if ($value === '') {
            return null;
        }

        return $value;
    }
}
