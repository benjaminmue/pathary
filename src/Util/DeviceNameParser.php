<?php declare(strict_types=1);

namespace Movary\Util;

class DeviceNameParser
{
    /**
     * Parse a user agent string and return a human-friendly device name
     *
     * @param string|null $userAgent The user agent string to parse
     * @return string Device name in format "Browser on OS" or "Unknown device"
     */
    public static function parse(?string $userAgent) : string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'Unknown device';
        }

        $browser = self::parseBrowser($userAgent);
        $os = self::parseOS($userAgent);

        if ($browser === 'Unknown' && $os === 'Unknown') {
            return 'Unknown device';
        }

        if ($browser === 'Unknown') {
            return $os;
        }

        if ($os === 'Unknown') {
            return $browser;
        }

        return $browser . ' on ' . $os;
    }

    /**
     * Extract browser name from user agent
     */
    private static function parseBrowser(string $userAgent) : string
    {
        // Order matters - check more specific patterns first
        $browsers = [
            'Edg/' => 'Edge',
            'EdgA/' => 'Edge',
            'Edg' => 'Edge',
            'OPR/' => 'Opera',
            'Opera/' => 'Opera',
            'Firefox/' => 'Firefox',
            'FxiOS/' => 'Firefox',
            'Chrome/' => 'Chrome',
            'CriOS/' => 'Chrome',
            'Safari/' => 'Safari',
            'MSIE ' => 'Internet Explorer',
            'Trident/' => 'Internet Explorer',
        ];

        foreach ($browsers as $pattern => $name) {
            if (stripos($userAgent, $pattern) !== false) {
                // Special case: Chrome-based browsers contain "Chrome/" and "Safari/"
                // so we need to exclude them when detecting Safari
                if ($name === 'Safari') {
                    if (stripos($userAgent, 'Chrome/') !== false ||
                        stripos($userAgent, 'CriOS/') !== false ||
                        stripos($userAgent, 'Edg') !== false ||
                        stripos($userAgent, 'OPR/') !== false) {
                        continue;
                    }
                }

                return $name;
            }
        }

        return 'Unknown';
    }

    /**
     * Extract operating system name from user agent
     */
    private static function parseOS(string $userAgent) : string
    {
        $osList = [
            '/windows nt 10/i' => 'Windows',
            '/windows nt 11/i' => 'Windows',
            '/windows nt 6\.[2-3]/i' => 'Windows',
            '/windows nt 6\.1/i' => 'Windows',
            '/windows nt 6\.0/i' => 'Windows',
            '/windows nt/i' => 'Windows',
            '/win16/i' => 'Windows',
            '/iphone/i' => 'iOS',
            '/ipad/i' => 'iOS',
            '/ipod/i' => 'iOS',
            '/macintosh|mac os x|mac_powerpc/i' => 'macOS',
            '/android/i' => 'Android',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Linux',
            '/fedora/i' => 'Linux',
            '/debian/i' => 'Linux',
        ];

        foreach ($osList as $pattern => $name) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return 'Unknown';
    }
}
