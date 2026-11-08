<?php declare(strict_types=1);

namespace Movary\Util;

class TrustedDeviceCookie
{
    private const string COOKIE_NAME = 'pathary_trusted_device';
    private const int EXPIRATION_DAYS = 30;

    /**
     * Set the trusted device cookie securely
     *
     * @param string $token The trusted device token (will not be logged)
     */
    public static function set(string $token) : void
    {
        $isSecure = RequestContext::isHttps();
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
        $isSecure = RequestContext::isHttps();

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
