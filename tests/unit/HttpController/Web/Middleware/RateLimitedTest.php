<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\HttpController\Web\Middleware\RateLimited;
use Movary\HttpController\Web\Middleware\RateLimitedPasswordChange;
use Movary\HttpController\Web\Middleware\RateLimitedPasswordSetup;
use Movary\HttpController\Web\Middleware\RateLimitedTestEmail;
use Movary\HttpController\Web\Middleware\RateLimitPolicy;
use Movary\Service\RateLimiter;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimited::class)]
#[CoversClass(RateLimitPolicy::class)]
class RateLimitedTest extends TestCase
{
    private array $serverBackup;

    private RateLimiter $rateLimiter;

    protected function setUp() : void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

        // Real limiter on an in-memory session, so the counting itself is exercised.
        $this->rateLimiter = new RateLimiter(new InMemorySessionWrapper());
    }

    protected function tearDown() : void
    {
        $_SERVER = $this->serverBackup;
        putenv('RATE_LIMIT_PASSWORD_CHANGE_MAX');
    }

    public function testPerUserLimitBlocksAfterMaxAttempts() : void
    {
        $middleware = new RateLimitedPasswordChange($this->rateLimiter, $this->authenticatedAs(7), $this->createStub(SecurityAuditService::class));

        for ($i = 0; $i < 5; $i++) {
            self::assertNull($middleware($this->request('/profile/security/password')));
        }

        $response = $middleware($this->request('/profile/security/password'));

        self::assertNotNull($response);
        self::assertSame(429, $response->getStatusCode()->getCode());
        self::assertStringContainsString('Rate limit exceeded', (string)$response->getBody());
    }

    public function testUsersHaveSeparateBuckets() : void
    {
        $audit = $this->createStub(SecurityAuditService::class);
        $this->exhaust(new RateLimitedPasswordChange($this->rateLimiter, $this->authenticatedAs(7), $audit), 5);

        $otherUser = new RateLimitedPasswordChange($this->rateLimiter, $this->authenticatedAs(8), $audit);

        self::assertNull($otherUser($this->request('/profile/security/password')));
    }

    public function testRoutesWithDifferentPoliciesDoNotShareABucket() : void
    {
        $authentication = $this->authenticatedAs(7);
        $audit = $this->createStub(SecurityAuditService::class);
        $this->exhaust(new RateLimitedPasswordChange($this->rateLimiter, $authentication, $audit), 5);

        $testEmail = new RateLimitedTestEmail($this->rateLimiter, $authentication, $audit);

        self::assertNull($testEmail($this->request('/api/admin/server/email/test')));
    }

    public function testPolicyDoesNotDependOnThePath() : void
    {
        // The old path-substring matching sent '/setup-password' to a default bucket.
        $middleware = new RateLimitedPasswordSetup($this->rateLimiter, $this->anonymous(), $this->createStub(SecurityAuditService::class));

        $this->exhaust($middleware, 10, '/some/unrelated/path');

        self::assertSame(429, $middleware($this->request('/setup-password'))?->getStatusCode()->getCode());
    }

    public function testAnonymousPolicyIsKeyedByRemoteAddressNotForwardedFor() : void
    {
        $middleware = new RateLimitedPasswordSetup($this->rateLimiter, $this->anonymous(), $this->createStub(SecurityAuditService::class));
        $this->exhaust($middleware, 10);

        // Rotating a spoofed X-Forwarded-For header must not open a fresh bucket.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';

        self::assertSame(429, $middleware($this->request('/setup-password'))?->getStatusCode()->getCode());
    }

    public function testViolationOfAuthenticatedUserIsAudited() : void
    {
        $audit = $this->createMock(SecurityAuditService::class);
        $middleware = new RateLimitedPasswordChange($this->rateLimiter, $this->authenticatedAs(7), $audit);
        $this->exhaust($middleware, 5);

        $audit->expects(self::once())->method('log')->with(7, SecurityAuditService::EVENT_RATE_LIMIT_EXCEEDED, '203.0.113.7');

        $middleware($this->request('/profile/security/password'));
    }

    public function testLimitCanBeOverriddenFromEnvironment() : void
    {
        putenv('RATE_LIMIT_PASSWORD_CHANGE_MAX=2');
        $middleware = new RateLimitedPasswordChange($this->rateLimiter, $this->authenticatedAs(7), $this->createStub(SecurityAuditService::class));

        $this->exhaust($middleware, 2);

        self::assertSame(429, $middleware($this->request('/profile/security/password'))?->getStatusCode()->getCode());
    }

    private function exhaust(RateLimited $middleware, int $attempts, string $path = '/any') : void
    {
        for ($i = 0; $i < $attempts; $i++) {
            self::assertNull($middleware($this->request($path)));
        }
    }

    private function authenticatedAs(int $userId) : Authentication
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserAuthenticatedWithCookie')->willReturn(true);
        $authentication->method('getCurrentUserId')->willReturn($userId);

        return $authentication;
    }

    private function anonymous() : Authentication
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserAuthenticatedWithCookie')->willReturn(false);

        return $authentication;
    }

    private function request(string $path) : Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getPath')->willReturn($path);

        return $request;
    }
}
