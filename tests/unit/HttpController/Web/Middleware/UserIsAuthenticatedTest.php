<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\HttpController\Web\Middleware\UserIsAuthenticated;
use Movary\Service\ApplicationUrlService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\RelativeUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserIsAuthenticated::class)]
class UserIsAuthenticatedTest extends TestCase
{
    private array $serverBackup;

    protected function setUp() : void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown() : void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testAnonymousRequestIsSentToLoginWithRedirectBack() : void
    {
        $_SERVER['REQUEST_URI'] = '/movies';

        $response = $this->invoke(isAuthenticated: false, totpUri: null);

        self::assertNotNull($response);
        self::assertSame(403, $response->getStatusCode()->getCode());
        self::assertContains('Location: /login?redirect=%2Fmovies', $this->headers($response));
    }

    public function testUserWithTwoFactorPasses() : void
    {
        $_SERVER['REQUEST_URI'] = '/movies';

        self::assertNull($this->invoke(isAuthenticated: true, totpUri: 'otpauth://totp/Pathary:alice?secret=ABC'));
    }

    public function testUserWithoutTwoFactorIsConfinedToSecurityPage() : void
    {
        $_SERVER['REQUEST_URI'] = '/movies';

        $response = $this->invoke(isAuthenticated: true, totpUri: null);

        self::assertNotNull($response);
        self::assertSame(303, $response->getStatusCode()->getCode());
        self::assertContains('Location: /profile/security', $this->headers($response));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function enrollmentPathProvider() : iterable
    {
        yield 'security page' => ['/profile/security', true];
        yield 'security page with query' => ['/profile/security?tab=2fa', true];
        yield 'totp enrollment endpoint' => ['/profile/security/totp/enable', true];
        yield 'look-alike path' => ['/profile/security-x', false];
        yield 'profile page' => ['/profile', false];
    }

    #[DataProvider('enrollmentPathProvider')]
    public function testOnlyEnrollmentPathsAreReachableWithoutTwoFactor(string $uri, bool $isReachable) : void
    {
        $_SERVER['REQUEST_URI'] = $uri;

        $response = $this->invoke(isAuthenticated: true, totpUri: null);

        self::assertSame($isReachable, $response === null);
    }

    private function invoke(bool $isAuthenticated, ?string $totpUri) : ?Response
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserAuthenticatedWithCookie')->willReturn($isAuthenticated);
        $authentication->method('getCurrentUserId')->willReturn(7);

        $twoFactorAuthenticationApi = $this->createStub(TwoFactorAuthenticationApi::class);
        $twoFactorAuthenticationApi->method('findTotpUri')->willReturn($totpUri);

        // No APPLICATION_URL configured: URLs stay relative.
        $urlService = $this->createStub(ApplicationUrlService::class);
        $urlService->method('createApplicationUrl')->willReturnCallback(fn(?RelativeUrl $relativeUrl = null) => $relativeUrl === null ? '' : (string)$relativeUrl);

        $subject = new UserIsAuthenticated($authentication, $urlService, $twoFactorAuthenticationApi);

        return $subject($this->createStub(Request::class));
    }

    /**
     * @return list<string>
     */
    private function headers(Response $response) : array
    {
        return array_values(array_map('strval', $response->getHeaders()));
    }
}
