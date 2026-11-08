<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Util;

use Movary\Util\RequestContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestContext::class)]
class RequestContextTest extends TestCase
{
    private array $serverBackup;

    private string|false $applicationUrlBackup;

    protected function setUp() : void
    {
        $this->serverBackup = $_SERVER;
        $this->applicationUrlBackup = getenv('APPLICATION_URL');
        unset($_SERVER['HTTPS'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        putenv('APPLICATION_URL');
    }

    protected function tearDown() : void
    {
        $_SERVER = $this->serverBackup;
        putenv($this->applicationUrlBackup === false ? 'APPLICATION_URL' : 'APPLICATION_URL=' . $this->applicationUrlBackup);
    }

    public function testClientIpIgnoresSpoofedForwardedForHeader() : void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.7';

        self::assertSame('203.0.113.7', RequestContext::clientIp());
    }

    public function testClientIpAcceptsIpv6() : void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';

        self::assertSame('2001:db8::1', RequestContext::clientIp());
    }

    public function testClientIpIsUnknownWhenMissingOrInvalid() : void
    {
        self::assertSame('unknown', RequestContext::clientIp());

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        self::assertSame('unknown', RequestContext::clientIp());
    }

    public function testIsHttpsWhenServerVariableIsOn() : void
    {
        $_SERVER['HTTPS'] = 'on';

        self::assertTrue(RequestContext::isHttps());
    }

    public function testIsNotHttpsWhenServerVariableIsOff() : void
    {
        $_SERVER['HTTPS'] = 'off';

        self::assertFalse(RequestContext::isHttps());
    }

    public function testIsHttpsWhenApplicationUrlIsHttps() : void
    {
        putenv('APPLICATION_URL=https://pathary.example');

        self::assertTrue(RequestContext::isHttps());
    }

    public function testIsNotHttpsForHttpApplicationUrl() : void
    {
        putenv('APPLICATION_URL=http://pathary.local');

        self::assertFalse(RequestContext::isHttps());
    }

    public function testForwardedProtoHeaderAloneDoesNotCountAsHttps() : void
    {
        // Client-controlled unless a trusted proxy rewrote it into HTTPS=on.
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        self::assertFalse(RequestContext::isHttps());
    }
}
