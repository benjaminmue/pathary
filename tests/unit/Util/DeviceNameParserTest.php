<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Util;

use Movary\Util\DeviceNameParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Movary\Util\DeviceNameParser::class)]
class DeviceNameParserTest extends TestCase
{
    /**
     * @param string|null $userAgent
     * @param string $expectedDeviceName
     */
    #[DataProvider('provideUserAgents')]
    public function testParse(?string $userAgent, string $expectedDeviceName) : void
    {
        self::assertSame($expectedDeviceName, DeviceNameParser::parse($userAgent));
    }

    /**
     * @return array<string, array{userAgent: string|null, expectedDeviceName: string}>
     */
    public static function provideUserAgents() : array
    {
        return [
            // Chrome on macOS
            'Chrome on macOS' => [
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'expectedDeviceName' => 'Chrome on macOS',
            ],

            // Safari on iOS (iPhone)
            'Safari on iOS (iPhone)' => [
                'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'expectedDeviceName' => 'Safari on iOS',
            ],

            // Safari on iOS (iPad)
            'Safari on iOS (iPad)' => [
                'userAgent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'expectedDeviceName' => 'Safari on iOS',
            ],

            // Firefox on Windows
            'Firefox on Windows' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
                'expectedDeviceName' => 'Firefox on Windows',
            ],

            // Edge on Windows
            'Edge on Windows' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'expectedDeviceName' => 'Edge on Windows',
            ],

            // Chrome on Android
            'Chrome on Android' => [
                'userAgent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'expectedDeviceName' => 'Chrome on Android',
            ],

            // Firefox on Linux
            'Firefox on Linux' => [
                'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
                'expectedDeviceName' => 'Firefox on Linux',
            ],

            // Safari on macOS
            'Safari on macOS' => [
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
                'expectedDeviceName' => 'Safari on macOS',
            ],

            // Opera on Windows
            'Opera on Windows' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0',
                'expectedDeviceName' => 'Opera on Windows',
            ],

            // Chrome on iOS (CriOS)
            'Chrome on iOS' => [
                'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1',
                'expectedDeviceName' => 'Chrome on iOS',
            ],

            // Firefox on iOS (FxiOS)
            'Firefox on iOS' => [
                'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/120.0 Mobile/15E148 Safari/605.1.15',
                'expectedDeviceName' => 'Firefox on iOS',
            ],

            // Unknown device - null user agent
            'Unknown device (null)' => [
                'userAgent' => null,
                'expectedDeviceName' => 'Unknown device',
            ],

            // Unknown device - empty user agent
            'Unknown device (empty)' => [
                'userAgent' => '',
                'expectedDeviceName' => 'Unknown device',
            ],

            // Unknown device - unrecognizable user agent
            'Unknown device (unrecognizable)' => [
                'userAgent' => 'SomeWeirdBot/1.0',
                'expectedDeviceName' => 'Unknown device',
            ],

            // Only browser detected (no OS)
            'Only browser detected' => [
                'userAgent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0',
                'expectedDeviceName' => 'Chrome',
            ],
        ];
    }
}
