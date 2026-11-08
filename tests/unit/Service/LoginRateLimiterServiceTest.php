<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Movary\Service\Exception\LoginRateLimitExceeded;
use Movary\Service\LoginRateLimiterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginRateLimiterService::class)]
class LoginRateLimiterServiceTest extends TestCase
{
    private Connection $connection;

    private LoginRateLimiterService $subject;

    protected function setUp() : void
    {
        // Real SQL against an in-memory database, so the queries themselves are exercised.
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_hash VARCHAR(64) NOT NULL,
                account_hash VARCHAR(64) NULL,
                attempted_at DATETIME NOT NULL,
                success BOOLEAN NOT NULL DEFAULT 0,
                ip_address VARCHAR(45) NULL
            )',
        );

        $this->subject = new LoginRateLimiterService($this->connection);
    }

    public function testFreshIpAndAccountAreAllowed() : void
    {
        $this->expectNotToPerformAssertions();

        $this->subject->ensureNotRateLimited('203.0.113.7', 'user@example.com');
    }

    public function testIpIsBlockedAfterTenFailures() : void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->subject->logAttempt('203.0.113.7', 'victim' . $i . '@example.com', false);
        }

        $this->expectException(LoginRateLimitExceeded::class);
        $this->subject->ensureNotRateLimited('203.0.113.7', 'someone-else@example.com');
    }

    public function testAccountIsBlockedAcrossRotatingIps() : void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->subject->logAttempt('198.51.100.' . $i, 'Victim@Example.com', false);
        }

        // A new IP does not help, and the email is compared case-insensitively.
        $this->expectException(LoginRateLimitExceeded::class);
        $this->subject->ensureNotRateLimited('192.0.2.200', ' victim@example.com');
    }

    public function testAccountBelowThresholdIsNotBlockedByOtherIps() : void
    {
        for ($i = 0; $i < 24; $i++) {
            $this->subject->logAttempt('198.51.100.' . $i, 'victim@example.com', false);
        }

        $this->expectNotToPerformAssertions();
        $this->subject->ensureNotRateLimited('192.0.2.200', 'victim@example.com');
    }

    public function testSuccessfulAttemptsDoNotCount() : void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->subject->logAttempt('203.0.113.7', 'user@example.com', true);
        }

        $this->expectNotToPerformAssertions();
        $this->subject->ensureNotRateLimited('203.0.113.7', 'user@example.com');
    }

    public function testFailuresOutsideTheWindowAreIgnored() : void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->subject->logAttempt('203.0.113.7', 'user@example.com', false);
        }
        $this->connection->executeStatement("UPDATE login_attempts SET attempted_at = '2000-01-01 00:00:00'");

        $this->expectNotToPerformAssertions();
        $this->subject->ensureNotRateLimited('203.0.113.7', 'user@example.com');
    }
}
