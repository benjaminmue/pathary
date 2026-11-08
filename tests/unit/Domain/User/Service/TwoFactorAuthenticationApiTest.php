<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Domain\User\Service;

use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\Service\TwoFactorAuthenticationFactory;
use Movary\Domain\User\UserApi;
use OTPHP\OTPInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwoFactorAuthenticationApi::class)]
class TwoFactorAuthenticationApiTest extends TestCase
{
    public function testCodeWithLeadingZeroReachesTheVerifierUnchanged() : void
    {
        // Regression: the code used to be cast to int, turning "012345" into "12345".
        $otp = $this->createMock(OTPInterface::class);
        $otp->expects(self::once())->method('verify')->with('012345')->willReturn(true);

        $factory = $this->createStub(TwoFactorAuthenticationFactory::class);
        $factory->method('createOtpFromProvisioningUri')->willReturn($otp);

        $subject = new TwoFactorAuthenticationApi($this->createStub(UserApi::class), $factory);

        self::assertTrue($subject->verifyTotpUri(7, '012345', 'otpauth://totp/Pathary:alice?secret=ABC'));
    }

    public function testCurrentCodeOfRealTotpIsAccepted() : void
    {
        $totp = TOTP::generate();
        $totp->setLabel('alice');

        $factory = $this->createStub(TwoFactorAuthenticationFactory::class);
        $factory->method('createOtpFromProvisioningUri')->willReturn($totp);

        $subject = new TwoFactorAuthenticationApi($this->createStub(UserApi::class), $factory);

        self::assertTrue($subject->verifyTotpUri(7, $totp->now(), $totp->getProvisioningUri()));
        self::assertFalse($subject->verifyTotpUri(7, 'abcdef', $totp->getProvisioningUri()));
    }
}
