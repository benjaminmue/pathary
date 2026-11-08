<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Domain\User\Service;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\RecoveryCodeService;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TrustedDeviceService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\Domain\User\UserRepository;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Authentication::class)]
class AuthenticationTest extends TestCase
{
    public function testRevokingSessionsWithoutAuthCookieRevokesAllOfThem() : void
    {
        $repository = $this->createMock(UserRepository::class);

        // No auth cookie in this request (e.g. invitation password setup or an API call).
        $repository->expects(self::once())->method('deleteAuthTokensOfUser')->with(37, null);

        $this->createSubject($repository)->revokeOtherSessionsOfUser(37);
    }

    public function testRememberMeLifetimeIsBoundedToNinetyDays() : void
    {
        // Guards against reintroducing the former ten-year "remember me" lifetime.
        $maxAgeInDays = (new \ReflectionClassConstant(Authentication::class, 'MAX_EXPIRATION_AGE_IN_DAYS'))->getValue();

        self::assertSame(90, $maxAgeInDays);
    }

    /**
     * Privacy levels: 0 = only the owner, 1 = any logged-in user, 2 = public.
     *
     * @return iterable<string, array{int, ?int, bool}>
     */
    public static function visibilityProvider() : iterable
    {
        yield 'private page, owner' => [0, 7, true];
        yield 'private page, other user' => [0, 8, false];
        yield 'private page, anonymous' => [0, null, false];
        yield 'users-only page, other user' => [1, 8, true];
        yield 'users-only page, anonymous' => [1, null, false];
        yield 'public page, anonymous' => [2, null, true];
    }

    #[DataProvider('visibilityProvider')]
    public function testUserPageVisibilityForApiRequests(int $privacyLevel, ?int $requestingUserId, bool $isVisible) : void
    {
        $targetUser = $this->createStub(UserEntity::class);
        $targetUser->method('getId')->willReturn(7);
        $targetUser->method('getPrivacyLevel')->willReturn($privacyLevel);

        $requestingUser = $this->createStub(UserEntity::class);
        $requestingUser->method('getId')->willReturn((int)$requestingUserId);

        // API token sent in the X-Movary-Token header; valid only for a requesting user.
        $userApi = $this->createStub(UserApi::class);
        $userApi->method('findUserIdByApiToken')->willReturn($requestingUserId);
        $userApi->method('findByToken')->willReturn($requestingUserId === null ? null : $requestingUser);

        $request = $this->createStub(Request::class);
        $request->method('getHeaders')->willReturn(['X-Movary-Token' => 'api-token']);

        $subject = $this->createSubject($this->createStub(UserRepository::class), $userApi);

        self::assertSame($isVisible, $subject->isUserPageVisibleForApiRequest($request, $targetUser));
    }

    public function testApiTreatsSessionOfUserWithoutTwoFactorAsAnonymous() : void
    {
        $user = $this->createStub(UserEntity::class);
        $user->method('getId')->willReturn(7);

        $userApi = $this->createStub(UserApi::class);
        $userApi->method('findUserIdByApiToken')->willReturn(7);
        $userApi->method('findByToken')->willReturn($user);

        $request = $this->createStub(Request::class);
        $request->method('getHeaders')->willReturn(['X-Movary-Token' => 'api-token']);

        $enrolled = $this->createSubject($this->createStub(UserRepository::class), $userApi);
        self::assertSame(7, $enrolled->getUserIdByToken($request));

        $notEnrolled = $this->createSubject($this->createStub(UserRepository::class), $userApi, totpUri: null);
        self::assertNull($notEnrolled->getUserIdByToken($request));
    }

    private function createSubject(
        UserRepository $repository,
        ?UserApi $userApi = null,
        ?string $totpUri = 'otpauth://totp/Pathary:user?secret=JBSWY3DPEHPK3PXP',
    ) : Authentication {
        $twoFactorAuthenticationApi = $this->createStub(TwoFactorAuthenticationApi::class);
        $twoFactorAuthenticationApi->method('findTotpUri')->willReturn($totpUri);

        return new Authentication(
            $repository,
            $userApi ?? $this->createStub(UserApi::class),
            $this->createStub(SessionWrapper::class),
            $twoFactorAuthenticationApi,
            $this->createStub(RecoveryCodeService::class),
            $this->createStub(TrustedDeviceService::class),
            $this->createStub(SecurityAuditService::class),
        );
    }
}
