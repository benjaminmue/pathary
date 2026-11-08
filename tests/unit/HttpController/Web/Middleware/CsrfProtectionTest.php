<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web\Middleware;

use Movary\HttpController\Web\Middleware\CsrfProtection;
use Movary\Service\CsrfTokenService;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsrfProtection::class)]
class CsrfProtectionTest extends TestCase
{
    private array $serverBackup;

    private CsrfTokenService $csrfTokenService;

    private CsrfProtection $subject;

    protected function setUp() : void
    {
        $this->serverBackup = $_SERVER;

        // Real token service on an in-memory session.
        $this->csrfTokenService = new CsrfTokenService(new InMemorySessionWrapper());
        $this->subject = new CsrfProtection($this->csrfTokenService);
    }

    protected function tearDown() : void
    {
        $_SERVER = $this->serverBackup;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeMethodProvider() : iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'DELETE' => ['DELETE'];
        yield 'PATCH' => ['PATCH'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeMethodProvider() : iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
    }

    #[DataProvider('safeMethodProvider')]
    public function testSafeMethodsPassWithoutToken(string $method) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;

        self::assertNull(($this->subject)($this->request()));
    }

    #[DataProvider('unsafeMethodProvider')]
    public function testUnsafeMethodsWithoutTokenAreForbidden(string $method) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $this->csrfTokenService->generateToken();

        self::assertSame(403, ($this->subject)($this->request())?->getStatusCode()->getCode());
    }

    #[DataProvider('unsafeMethodProvider')]
    public function testUnsafeMethodsWithValidJsonTokenPass(string $method) : void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $token = $this->csrfTokenService->generateToken();

        self::assertNull(($this->subject)($this->request(body: (string)json_encode(['_csrf_token' => $token]))));
    }

    public function testValidFormTokenPasses() : void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = $this->csrfTokenService->generateToken();

        self::assertNull(($this->subject)($this->request(postParameters: ['_csrf_token' => $token])));
    }

    public function testWrongTokenIsForbidden() : void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->csrfTokenService->generateToken();

        $response = ($this->subject)($this->request(body: (string)json_encode(['_csrf_token' => str_repeat('0', 64)])));

        self::assertSame(403, $response?->getStatusCode()->getCode());
    }

    public function testTokenWithoutSessionTokenIsForbidden() : void
    {
        // A token from another session is worthless when this session has none.
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = ($this->subject)($this->request(body: (string)json_encode(['_csrf_token' => str_repeat('a', 64)])));

        self::assertSame(403, $response?->getStatusCode()->getCode());
    }

    public function testNonStringTokenIsForbidden() : void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->csrfTokenService->generateToken();

        $response = ($this->subject)($this->request(body: (string)json_encode(['_csrf_token' => true])));

        self::assertSame(403, $response?->getStatusCode()->getCode());
    }

    /**
     * @param array<string, mixed> $postParameters
     */
    private function request(array $postParameters = [], string $body = '') : Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getPostParameters')->willReturn($postParameters);
        $request->method('getBody')->willReturn($body);

        return $request;
    }
}
