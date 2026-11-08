<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\HttpController\Web\SettingsController;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SettingsController::class)]
class SettingsControllerPasswordTest extends TestCase
{
    public function testPasswordChangeRevokesOtherSessions() : void
    {
        $authentication = $this->createMock(Authentication::class);
        $authentication->method('getCurrentUserId')->willReturn(7);
        $authentication->expects(self::once())->method('revokeOtherSessionsOfUser')->with(7);

        $user = $this->createStub(UserEntity::class);
        $user->method('hasCoreAccountChangesDisabled')->willReturn(false);

        $userApi = $this->createMock(UserApi::class);
        $userApi->method('fetchUser')->willReturn($user);
        $userApi->method('isValidPassword')->willReturn(true);
        $userApi->expects(self::once())->method('updatePassword')->with(7, 'new-password-123');

        $response = $this->createSubject($authentication, $userApi)->updatePassword(
            $this->createRequest('{"currentPassword":"old-password","newPassword":"new-password-123"}'),
        );

        self::assertSame(200, $response->getStatusCode()->getCode());
    }

    public function testWrongCurrentPasswordRevokesNothing() : void
    {
        $authentication = $this->createMock(Authentication::class);
        $authentication->method('getCurrentUserId')->willReturn(7);
        $authentication->expects(self::never())->method('revokeOtherSessionsOfUser');

        $userApi = $this->createStub(UserApi::class);
        $userApi->method('fetchUser')->willReturn($this->createStub(UserEntity::class));
        $userApi->method('isValidPassword')->willReturn(false);

        $response = $this->createSubject($authentication, $userApi)->updatePassword(
            $this->createRequest('{"currentPassword":"wrong","newPassword":"new-password-123"}'),
        );

        self::assertSame(400, $response->getStatusCode()->getCode());
    }

    private function createRequest(string $body) : Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getBody')->willReturn($body);

        return $request;
    }

    /**
     * SettingsController has about twenty collaborators; updatePassword() uses only
     * these two, so the controller is built without its constructor and just they
     * are injected.
     */
    private function createSubject(Authentication $authentication, UserApi $userApi) : SettingsController
    {
        $class = new ReflectionClass(SettingsController::class);
        $subject = $class->newInstanceWithoutConstructor();
        $class->getProperty('authenticationService')->setValue($subject, $authentication);
        $class->getProperty('userApi')->setValue($subject, $userApi);

        return $subject;
    }
}
