<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Movary\Domain\User\Exception\EmailNotUnique;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\UserInvitationService;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\HttpController\Web\UserController;
use Movary\Service\Email\EmailService;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(UserController::class)]
class UserControllerTest extends TestCase
{
    private const int ADMIN_ID = 1;

    private const int TARGET_ID = 2;

    private Connection $connection;

    protected function setUp() : void
    {
        // Real (in-memory) connection so transactional() really commits or rolls back.
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE change_log (field VARCHAR(20))');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloadProvider() : iterable
    {
        yield 'missing name' => [['email' => 'a@example.com', 'isAdmin' => false]];
        yield 'missing email' => [['name' => 'alice', 'isAdmin' => false]];
        yield 'missing isAdmin' => [['name' => 'alice', 'email' => 'a@example.com']];
        yield 'isAdmin as string' => [['name' => 'alice', 'email' => 'a@example.com', 'isAdmin' => 'true']];
        yield 'password as array' => [['name' => 'alice', 'email' => 'a@example.com', 'isAdmin' => false, 'password' => ['x']]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testUpdateWithInvalidPayloadIsRejectedWithoutChanges(array $payload) : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->expects(self::never())->method('updateName');
        $userApi->expects(self::never())->method('updateEmail');
        $userApi->expects(self::never())->method('updateIsAdmin');
        $userApi->expects(self::never())->method('updatePassword');

        $response = $this->createSubject($userApi)->updateUser($this->request(self::TARGET_ID, (string)json_encode($payload)));

        self::assertSame(400, $response->getStatusCode()->getCode());
    }

    public function testUpdateWithMalformedJsonIsRejected() : void
    {
        $response = $this->createSubject($this->userApiStub())->updateUser($this->request(self::TARGET_ID, '{not json'));

        self::assertSame(400, $response->getStatusCode()->getCode());
    }

    public function testLastAdminCannotBeDemoted() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::TARGET_ID, 'alice', true));
        $userApi->method('countAdminUsers')->willReturn(1);
        $userApi->expects(self::never())->method('updateIsAdmin');

        $response = $this->createSubject($userApi)->updateUser($this->updateRequest(['isAdmin' => false]));

        self::assertSame(400, $response->getStatusCode()->getCode());
    }

    public function testAdminCanBeDemotedWhileAnotherAdminRemains() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::TARGET_ID, 'alice', true));
        $userApi->method('findUserEmail')->willReturn('alice@example.com');
        $userApi->method('countAdminUsers')->willReturn(2);
        $userApi->expects(self::once())->method('updateIsAdmin')->with(self::TARGET_ID, false);

        $response = $this->createSubject($userApi)->updateUser($this->updateRequest(['isAdmin' => false]));

        self::assertSame(200, $response->getStatusCode()->getCode());
    }

    public function testFailingFieldRollsBackEarlierChanges() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::TARGET_ID, 'alice', false));
        $userApi->method('findUserEmail')->willReturn('alice@example.com');
        // The name change hits the database, then the email change fails.
        $userApi->expects(self::once())->method('updateName')->willReturnCallback(
            fn() => $this->connection->insert('change_log', ['field' => 'name']),
        );
        $userApi->method('updateEmail')->willThrowException(new EmailNotUnique());

        $response = $this->createSubject($userApi)->updateUser(
            $this->updateRequest(['name' => 'alice2', 'email' => 'taken@example.com']),
        );

        self::assertSame(400, $response->getStatusCode()->getCode());
        self::assertSame(0, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM change_log'));
    }

    public function testPasswordResetRevokesTheUsersSessions() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::TARGET_ID, 'alice', false));
        $userApi->method('findUserEmail')->willReturn('alice@example.com');
        $userApi->expects(self::once())->method('updatePassword')->with(self::TARGET_ID, 'New-Password-123');

        $authentication = $this->adminAuthentication();
        $authentication->expects(self::once())->method('revokeOtherSessionsOfUser')->with(self::TARGET_ID);

        $response = $this->createSubject($userApi, $authentication)->updateUser($this->updateRequest(['password' => 'New-Password-123']));

        self::assertSame(200, $response->getStatusCode()->getCode());
    }

    public function testUpdateWithoutPasswordKeepsPasswordAndSessions() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::TARGET_ID, 'alice', false));
        $userApi->method('findUserEmail')->willReturn('alice@example.com');
        $userApi->expects(self::never())->method('updatePassword');

        $authentication = $this->adminAuthentication();
        $authentication->expects(self::never())->method('revokeOtherSessionsOfUser');

        // The admin users tab omits the key entirely when the field is empty.
        $response = $this->createSubject($userApi, $authentication)->updateUser(
            $this->request(self::TARGET_ID, (string)json_encode(['name' => 'alice', 'email' => 'alice@example.com', 'isAdmin' => false])),
        );

        self::assertSame(200, $response->getStatusCode()->getCode());
    }

    public function testLastAdminCannotBeDeleted() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::ADMIN_ID, 'admin', true));
        $userApi->method('countAdminUsers')->willReturn(1);
        $userApi->expects(self::never())->method('deleteUser');

        $response = $this->createSubject($userApi)->deleteUser($this->request(self::ADMIN_ID, ''));

        self::assertSame(400, $response->getStatusCode()->getCode());
    }

    public function testRegularUserCanBeDeleted() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn($this->user(self::TARGET_ID, 'alice', false));
        $userApi->expects(self::once())->method('deleteUser')->with(self::TARGET_ID);

        $response = $this->createSubject($userApi)->deleteUser($this->request(self::TARGET_ID, ''));

        self::assertSame(200, $response->getStatusCode()->getCode());
    }

    public function testDeletingUnknownUserIsNotFound() : void
    {
        $userApi = $this->createMock(UserApi::class);
        $userApi->method('findUserById')->willReturn(null);
        $userApi->expects(self::never())->method('deleteUser');

        $response = $this->createSubject($userApi)->deleteUser($this->request(99, ''));

        self::assertSame(404, $response->getStatusCode()->getCode());
    }

    private function createSubject(UserApi $userApi, ?Authentication $authentication = null) : UserController
    {
        return new UserController(
            $authentication ?? $this->adminAuthenticationStub(),
            $userApi,
            $this->createStub(EmailService::class),
            $this->createStub(UserInvitationService::class),
            $this->createStub(SecurityAuditService::class),
            $this->createStub(LoggerInterface::class),
            $this->connection,
        );
    }

    private function adminAuthentication() : Authentication&MockObject
    {
        $authentication = $this->createMock(Authentication::class);
        $authentication->method('getCurrentUser')->willReturn($this->user(self::ADMIN_ID, 'admin', true));

        return $authentication;
    }

    private function adminAuthenticationStub() : Authentication&Stub
    {
        $authentication = $this->createStub(Authentication::class);
        $authentication->method('getCurrentUser')->willReturn($this->user(self::ADMIN_ID, 'admin', true));

        return $authentication;
    }

    private function userApiStub() : UserApi&Stub
    {
        return $this->createStub(UserApi::class);
    }

    private function user(int $id, string $name, bool $isAdmin) : UserEntity&Stub
    {
        $user = $this->createStub(UserEntity::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn($name);
        $user->method('isAdmin')->willReturn($isAdmin);

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function updateRequest(array $overrides) : Request&Stub
    {
        $payload = array_merge(['name' => 'alice', 'email' => 'alice@example.com', 'isAdmin' => false, 'password' => null], $overrides);

        return $this->request(self::TARGET_ID, (string)json_encode($payload));
    }

    private function request(int $userId, string $body) : Request&Stub
    {
        $request = $this->createStub(Request::class);
        $request->method('getRouteParameters')->willReturn(['userId' => (string)$userId]);
        $request->method('getBody')->willReturn($body);

        return $request;
    }
}
