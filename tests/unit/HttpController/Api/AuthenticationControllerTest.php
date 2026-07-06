<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Api;

use Movary\Domain\User\Exception\InvalidCredentials;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\HttpController\Api\AuthenticationController;
use Movary\Service\Exception\LoginRateLimitExceeded;
use Movary\Service\LoginRateLimiterService;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticationController::class)]
class AuthenticationControllerTest extends TestCase
{
    private Authentication&MockObject $authenticationService;

    private TwoFactorAuthenticationApi&MockObject $twoFactorAuthenticationApi;

    private LoginRateLimiterService&MockObject $loginRateLimiter;

    private AuthenticationController $subject;

    protected function setUp() : void
    {
        $this->authenticationService = $this->createMock(Authentication::class);
        $this->twoFactorAuthenticationApi = $this->createMock(TwoFactorAuthenticationApi::class);
        $this->loginRateLimiter = $this->createMock(LoginRateLimiterService::class);

        $this->subject = new AuthenticationController(
            $this->authenticationService,
            $this->createMock(UserApi::class),
            $this->twoFactorAuthenticationApi,
            $this->loginRateLimiter,
        );
    }

    public function testCreateTokenRefusesUserWithoutTwoFactorAndRevokesTheToken() : void
    {
        $this->authenticationService->method('login')->willReturn([
            'user' => $this->createUser(37),
            'token' => 'freshly-created-token',
        ]);
        // Two-factor authentication is mandatory but this user has not set it up.
        $this->twoFactorAuthenticationApi->method('findTotpUri')->with(37)->willReturn(null);

        // The token that login() just created must be revoked so it cannot be used.
        $this->authenticationService->expects(self::once())->method('deleteToken')->with('freshly-created-token');

        $response = $this->subject->createToken($this->createTokenRequest());

        self::assertSame(403, $response->getStatusCode()->getCode());
        self::assertStringContainsString('TwoFactorSetupRequired', (string)$response->getBody());
        self::assertStringNotContainsString('freshly-created-token', (string)$response->getBody());
    }

    public function testCreateTokenIssuesTokenForUserWithTwoFactor() : void
    {
        $this->authenticationService->method('login')->willReturn([
            'user' => $this->createUser(37),
            'token' => 'freshly-created-token',
        ]);
        $this->twoFactorAuthenticationApi->method('findTotpUri')->with(37)->willReturn('otpauth://totp/Pathary:zsmith?secret=ABC');

        $this->authenticationService->expects(self::never())->method('deleteToken');

        $response = $this->subject->createToken($this->createTokenRequest());

        self::assertSame(200, $response->getStatusCode()->getCode());
        self::assertStringContainsString('freshly-created-token', (string)$response->getBody());
    }

    public function testCreateTokenIsRefusedWhenRateLimited() : void
    {
        $this->loginRateLimiter->method('ensureNotRateLimited')->willThrowException(LoginRateLimitExceeded::create(15));

        // A rate-limited request must never reach authentication.
        $this->authenticationService->expects(self::never())->method('login');

        $response = $this->subject->createToken($this->createTokenRequest());

        self::assertSame(429, $response->getStatusCode()->getCode());
        self::assertStringContainsString('RateLimitExceeded', (string)$response->getBody());
    }

    public function testFailedLoginIsRecordedAsAttempt() : void
    {
        $this->authenticationService->method('login')->willThrowException(InvalidCredentials::create());

        // A wrong password must be recorded so repeated failures trip the limiter.
        $this->loginRateLimiter->expects(self::once())->method('logAttempt')->with(self::anything(), false);

        $response = $this->subject->createToken($this->createTokenRequest());

        self::assertSame(401, $response->getStatusCode()->getCode());
    }

    public function testSuccessfulLoginIsRecordedAsAttempt() : void
    {
        $this->authenticationService->method('login')->willReturn([
            'user' => $this->createUser(37),
            'token' => 'freshly-created-token',
        ]);
        $this->twoFactorAuthenticationApi->method('findTotpUri')->willReturn('otpauth://totp/Pathary:zsmith?secret=ABC');

        $this->loginRateLimiter->expects(self::once())->method('logAttempt')->with(self::anything(), true);

        $response = $this->subject->createToken($this->createTokenRequest());

        self::assertSame(200, $response->getStatusCode()->getCode());
    }

    private function createUser(int $id) : UserEntity&MockObject
    {
        $user = $this->createMock(UserEntity::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn('zsmith');
        $user->method('isAdmin')->willReturn(false);

        return $user;
    }

    private function createTokenRequest() : Request&MockObject
    {
        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn((string)json_encode([
            'email' => 'user@example.com',
            'password' => 'secret',
        ]));
        $request->method('getHeaders')->willReturn(['X-Movary-Client' => 'test-client']);
        $request->method('getUserAgent')->willReturn('test-agent');

        return $request;
    }
}
