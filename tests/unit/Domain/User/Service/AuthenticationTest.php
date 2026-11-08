<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Domain\User\Service;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\RecoveryCodeService;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TrustedDeviceService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserRepository;
use Movary\Util\SessionWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
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

    private function createSubject(UserRepository&MockObject $repository) : Authentication
    {
        return new Authentication(
            $repository,
            $this->createStub(UserApi::class),
            $this->createStub(SessionWrapper::class),
            $this->createStub(TwoFactorAuthenticationApi::class),
            $this->createStub(RecoveryCodeService::class),
            $this->createStub(TrustedDeviceService::class),
            $this->createStub(SecurityAuditService::class),
        );
    }
}
