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

        // DEBUG: Log HTTPS detection
        $httpsSource = 'none';
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $httpsSource = 'X-Forwarded-Proto: ' . $_SERVER['HTTP_X_FORWARDED_PROTO'];
        } elseif (isset($_SERVER['HTTPS'])) {
            $httpsSource = 'HTTPS: ' . $_SERVER['HTTPS'];
        }
        error_log('[TRUSTED_DEVICE_DEBUG] HTTPS detection - Source: ' . $httpsSource . ', Secure flag: ' . ($isSecure ? 'YES' : 'NO'));

        $result = setcookie(
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

        error_log('[TRUSTED_DEVICE_DEBUG] setcookie() result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        error_log('[TRUSTED_DEVICE_DEBUG] Cookie name: ' . self::COOKIE_NAME . ', Expires: ' . date('Y-m-d H:i:s', $expirationTimestamp));
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
