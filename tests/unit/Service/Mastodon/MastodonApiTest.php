<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service\Mastodon;

use GuzzleHttp\Client as HttpClient;
use Movary\Service\Mastodon\MastodonApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MastodonApi::class)]
class MastodonApiTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUsernameProvider() : iterable
    {
        yield 'no at sign' => ['user'];
        yield 'local handle only' => ['@user'];
        yield 'empty domain' => ['@user@'];
    }

    #[DataProvider('invalidUsernameProvider')]
    public function testRejectsUsernameWithoutDomainBeforeAnyRequest(string $username) : void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects(self::never())->method('request');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not extract domain from username: ' . $username);

        (new MastodonApi($httpClient))->createPost('token', $username, 'public', 'message');
    }

    public function testPostsToTheInstanceOfTheUsername() : void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'https://mastodon.example/api/v1/statuses', self::anything());

        (new MastodonApi($httpClient))->createPost('token', '@user@mastodon.example', 'public', 'message');
    }
}
