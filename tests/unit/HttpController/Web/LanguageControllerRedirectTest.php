<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\HttpController\Web\LanguageController;
use Movary\Service\Translation\Translator;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The language switcher sends the user back to the referring page; that must never
 * become an open redirect to another origin.
 */
#[CoversClass(LanguageController::class)]
class LanguageControllerRedirectTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function refererProvider() : iterable
    {
        yield 'no referer' => [null, '/'];
        yield 'same-site page with query' => ['https://pathary.example/movies?page=2', '/movies?page=2'];
        yield 'foreign host is reduced to its path' => ['https://evil.example/profile', '/profile'];
        yield 'protocol-relative path' => ['https://evil.example//evil.example/phish', '/'];
        yield 'backslash protocol-relative path' => ['https://evil.example/\\evil.example/phish', '/'];
        // Browsers drop tabs, which would turn "/\t/host" into "//host"; parse_url() neutralises them.
        yield 'tab smuggled into path' => ["https://evil.example/\t/evil.example", '/_/evil.example'];
        yield 'line break in query' => ["https://pathary.example/movies?x=1\r\nSet-Cookie: a=b", '/movies?x=1__Set-Cookie: a=b'];
        yield 'not a url' => ['javascript:alert(1)', '/'];
    }

    #[DataProvider('refererProvider')]
    public function testRedirectTargetIsAlwaysASameOriginPath(?string $referer, string $expectedLocation) : void
    {
        $translator = $this->createStub(Translator::class);
        $translator->method('isSupported')->willReturn(false);

        $subject = new LanguageController(
            $this->createStub(SessionWrapper::class),
            $translator,
            $this->createStub(Authentication::class),
            $this->createStub(UserApi::class),
        );

        $request = $this->createStub(Request::class);
        $request->method('getRouteParameters')->willReturn(['locale' => 'xx']);
        $request->method('getHttpReferer')->willReturn($referer);

        $response = $subject->switchLanguage($request);

        self::assertSame(303, $response->getStatusCode()->getCode());
        self::assertContains('Location: ' . $expectedLocation, array_map('strval', $response->getHeaders()));
    }
}
