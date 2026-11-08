<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\HttpController\Api\Middleware\IsAuthorizedToReadUserData as ApiIsAuthorizedToReadUserData;
use Movary\HttpController\Api\Middleware\IsAuthorizedToWriteUserData as ApiIsAuthorizedToWriteUserData;
use Movary\HttpController\Web\Middleware\IsAuthorizedToReadUserData as WebIsAuthorizedToReadUserData;
use Movary\HttpController\Web\Middleware\UserIsAdmin;
use Movary\Service\ApplicationUrlService;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Record-level access to user data: owners, other users, anonymous visitors and admins.
 */
#[CoversClass(WebIsAuthorizedToReadUserData::class)]
#[CoversClass(ApiIsAuthorizedToReadUserData::class)]
#[CoversClass(ApiIsAuthorizedToWriteUserData::class)]
#[CoversClass(UserIsAdmin::class)]
class AuthorizationMiddlewareTest extends TestCase
{
    private const int OWNER_ID = 7;

    private const int OTHER_USER_ID = 8;

    public function testWebReadOfUnknownUserIsNotFound() : void
    {
        $subject = new WebIsAuthorizedToReadUserData($this->userApi(null), $this->createStub(Authentication::class), $this->urlService());

        self::assertSame(404, $subject($this->request('ghost'))?->getStatusCode()->getCode());
    }

    public function testWebReadOfVisibleUserPasses() : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserPageVisibleForWebRequest')->willReturn(true);

        $subject = new WebIsAuthorizedToReadUserData($this->userApi($this->owner()), $authentication, $this->urlService());

        self::assertNull($subject($this->request('alice')));
    }

    public function testWebReadOfHiddenUserRedirectsToLogin() : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserPageVisibleForWebRequest')->willReturn(false);

        $subject = new WebIsAuthorizedToReadUserData($this->userApi($this->owner()), $authentication, $this->urlService());
        $response = $subject($this->request('alice'));

        self::assertSame(403, $response?->getStatusCode()->getCode());
    }

    public function testApiReadOfUnknownUserIsNotFound() : void
    {
        $subject = new ApiIsAuthorizedToReadUserData($this->userApi(null), $this->createStub(Authentication::class));

        self::assertSame(404, $subject($this->request('ghost'))?->getStatusCode()->getCode());
    }

    public function testApiReadOfHiddenUserIsForbidden() : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserPageVisibleForApiRequest')->willReturn(false);

        $subject = new ApiIsAuthorizedToReadUserData($this->userApi($this->owner()), $authentication);

        self::assertSame(403, $subject($this->request('alice'))?->getStatusCode()->getCode());
    }

    public function testApiReadOfVisibleUserPasses() : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('isUserPageVisibleForApiRequest')->willReturn(true);

        $subject = new ApiIsAuthorizedToReadUserData($this->userApi($this->owner()), $authentication);

        self::assertNull($subject($this->request('alice')));
    }

    public function testApiWriteByOwnerPasses() : void
    {
        $subject = new ApiIsAuthorizedToWriteUserData($this->userApi($this->owner()), $this->authenticatedViaToken(self::OWNER_ID));

        self::assertNull($subject($this->request('alice')));
    }

    public function testApiWriteByOtherUserIsForbidden() : void
    {
        // Changing the username in the URL must not grant access to someone else's data.
        $subject = new ApiIsAuthorizedToWriteUserData($this->userApi($this->owner()), $this->authenticatedViaToken(self::OTHER_USER_ID));

        self::assertSame(403, $subject($this->request('alice'))?->getStatusCode()->getCode());
    }

    public function testApiWriteByAnonymousIsForbidden() : void
    {
        $subject = new ApiIsAuthorizedToWriteUserData($this->userApi($this->owner()), $this->authenticatedViaToken(null));

        self::assertSame(403, $subject($this->request('alice'))?->getStatusCode()->getCode());
    }

    public function testApiWriteOfUnknownUserIsNotFound() : void
    {
        $subject = new ApiIsAuthorizedToWriteUserData($this->userApi(null), $this->authenticatedViaToken(self::OWNER_ID));

        self::assertSame(404, $subject($this->request('ghost'))?->getStatusCode()->getCode());
    }

    public function testAdminMiddlewareRejectsRegularUser() : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('getCurrentUser')->willReturn($this->user(self::OWNER_ID, false));

        self::assertSame(403, (new UserIsAdmin($authentication))($this->request('alice'))?->getStatusCode()->getCode());
    }

    public function testAdminMiddlewareAcceptsAdmin() : void
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('getCurrentUser')->willReturn($this->user(self::OTHER_USER_ID, true));

        self::assertNull((new UserIsAdmin($authentication))($this->request('alice')));
    }

    private function authenticatedViaToken(?int $userId) : Authentication&Stub
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('getUserIdByToken')->willReturn($userId);

        return $authentication;
    }

    private function owner() : UserEntity&Stub
    {
        return $this->user(self::OWNER_ID, false);
    }

    private function user(int $id, bool $isAdmin) : UserEntity&Stub
    {
        $user = $this->createStub(UserEntity::class);
        $user->method('getId')->willReturn($id);
        $user->method('isAdmin')->willReturn($isAdmin);

        return $user;
    }

    private function userApi(?UserEntity $user) : UserApi&Stub
    {
        $userApi = $this->createStub(UserApi::class);
        $userApi->method('findUserByName')->willReturn($user);

        return $userApi;
    }

    private function urlService() : ApplicationUrlService&Stub
    {
        $urlService = $this->createStub(ApplicationUrlService::class);
        $urlService->method('createApplicationUrl')->willReturn('');

        return $urlService;
    }

    private function request(string $username) : Request&Stub
    {
        $request = $this->createStub(Request::class);
        $request->method('getRouteParameters')->willReturn(['username' => $username]);
        $request->method('getPath')->willReturn('/users/' . $username);

        return $request;
    }
}
