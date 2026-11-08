<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\RecoveryCodeService;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TrustedDeviceService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\Service\TwoFactorAuthenticationFactory;
use Movary\Domain\User\Service\Validator;
use Movary\Domain\User\UserApi;
use Movary\HttpController\Web\ProfileSecurityController;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

#[CoversClass(ProfileSecurityController::class)]
class ProfileSecurityControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function limitProvider() : iterable
    {
        yield 'default' => [null, 20];
        yield 'huge value is capped' => ['1000000', 100];
        yield 'zero is raised' => ['0', 1];
        yield 'negative is raised' => ['-5', 1];
        yield 'garbage counts as zero' => ['abc', 1];
    }

    #[DataProvider('limitProvider')]
    public function testSecurityEventLimitIsClamped(?string $limit, int $expectedLimit) : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('getCurrentUserId')->willReturn(7);

        // The controller over-fetches by factor three to make up for filtered events.
        $securityAuditService = $this->createMock(SecurityAuditService::class);
        $securityAuditService->expects(self::once())->method('getRecentEvents')->with(7, $expectedLimit * 3)->willReturn([]);

        $subject = new ProfileSecurityController(
            $this->createStub(Environment::class),
            $authentication,
            $this->createStub(UserApi::class),
            $this->createStub(TwoFactorAuthenticationApi::class),
            $this->createStub(TwoFactorAuthenticationFactory::class),
            $this->createStub(RecoveryCodeService::class),
            $this->createStub(TrustedDeviceService::class),
            $securityAuditService,
            $this->createStub(Validator::class),
        );

        $request = $this->createStub(Request::class);
        $request->method('getGetParameters')->willReturn($limit === null ? [] : ['limit' => $limit]);

        $subject->getSecurityEvents($request);
    }
}
