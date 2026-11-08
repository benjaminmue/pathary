<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Movary\Domain\User\Repository\RecoveryCodeRepository;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\Service\TwoFactorAuthenticationFactory;
use Movary\Domain\User\Service\Validator;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\Domain\User\UserRepository;
use Movary\HttpController\Web\InitController;
use Movary\Service\SetupService;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Twig\Environment;

#[CoversClass(InitController::class)]
class InitControllerTest extends TestCase
{
    private const string PASSWORD = 'Correct-Horse-9';

    private ?array $sessionBackup;

    private UserApi&Stub $userApi;

    private InitController $subject;

    protected function setUp() : void
    {
        $this->sessionBackup = $_SESSION ?? null;
        $_SESSION = [];

        $this->userApi = $this->createStub(UserApi::class);
        $this->userApi->method('hashPassword')->willReturnCallback(fn(string $password) => password_hash($password, PASSWORD_DEFAULT));

        $this->subject = $this->createSubject($this->userApi);
    }

    protected function tearDown() : void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);

            return;
        }

        $_SESSION = $this->sessionBackup;
    }

    public function testCreateAccountKeepsOnlyThePasswordHashInTheSession() : void
    {
        $response = $this->subject->createAdmin($this->request([
            'step' => 'create_account',
            'email' => 'admin@example.com',
            'name' => 'admin',
            'password' => self::PASSWORD,
            'passwordConfirmation' => self::PASSWORD,
        ]));

        self::assertSame(200, $response->getStatusCode()->getCode());
        self::assertStringNotContainsString(self::PASSWORD, serialize($_SESSION));
        self::assertTrue(password_verify(self::PASSWORD, $_SESSION['init_user_password_hash']));
    }

    public function testCreateAccountRejectsInvalidEmailBeforeStoringAnything() : void
    {
        $response = $this->subject->createAdmin($this->request([
            'step' => 'create_account',
            'email' => 'not-an-email',
            'name' => 'admin',
            'password' => self::PASSWORD,
            'passwordConfirmation' => self::PASSWORD,
        ]));

        self::assertSame(400, $response->getStatusCode()->getCode());
        self::assertSame([], $_SESSION);
    }

    public function testCreateAccountTreatsNonStringFieldsAsMissing() : void
    {
        $response = $this->subject->createAdmin($this->request([
            'step' => 'create_account',
            'email' => ['admin@example.com'],
            'name' => 'admin',
            'password' => self::PASSWORD,
            'passwordConfirmation' => self::PASSWORD,
        ]));

        self::assertSame(400, $response->getStatusCode()->getCode());
    }

    public function testFinalStepCreatesUserFromStoredHash() : void
    {
        $this->primeCompletedWizardSession();
        $passwordHash = $_SESSION['init_user_password_hash'];

        $user = $this->createStub(UserEntity::class);
        $user->method('getId')->willReturn(1);
        $userApi = $this->createMock(UserApi::class);
        $userApi->expects(self::once())
            ->method('createUserWithPasswordHash')
            ->with('admin@example.com', $passwordHash, 'admin', true)
            ->willReturn($user);

        $response = $this->createSubject($userApi)->createAdmin($this->request(['step' => 'acknowledge_codes']));

        self::assertSame(200, $response->getStatusCode()->getCode());
        self::assertArrayNotHasKey('init_user_password_hash', $_SESSION ?? []);
    }

    public function testFinalStepFailureDoesNotLeakTheExceptionMessage() : void
    {
        $this->primeCompletedWizardSession();
        $this->userApi->method('createUserWithPasswordHash')->willThrowException(new RuntimeException('SQLSTATE[42S02] secret internals'));

        $response = $this->subject->createAdmin($this->request(['step' => 'acknowledge_codes']));

        self::assertSame(500, $response->getStatusCode()->getCode());
        self::assertStringNotContainsString('SQLSTATE', (string)$response->getBody());
    }

    private function createSubject(UserApi $userApi) : InitController
    {
        return new InitController(
            $this->createStub(Environment::class),
            $this->createStub(SetupService::class),
            $userApi,
            new Validator($this->createStub(UserRepository::class)),
            $this->createStub(TwoFactorAuthenticationApi::class),
            $this->createStub(TwoFactorAuthenticationFactory::class),
            $this->createStub(RecoveryCodeRepository::class),
            $this->createStub(SecurityAuditService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function primeCompletedWizardSession() : void
    {
        $_SESSION = [
            'init_user_email' => 'admin@example.com',
            'init_user_name' => 'admin',
            'init_user_password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            'init_pending_totp_uri' => 'otpauth://totp/Pathary:admin?secret=ABC',
            'init_recovery_codes' => ['ABCD-EFGH-JK'],
        ];
    }

    private function request(array $body) : Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getBody')->willReturn((string)json_encode($body));

        return $request;
    }
}
