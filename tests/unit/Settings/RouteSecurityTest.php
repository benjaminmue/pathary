<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Settings;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Movary\HttpController\Web\Middleware\CsrfProtection;
use Movary\HttpController\Web\Middleware\UserIsAdmin;
use Movary\HttpController\Web\Middleware\UserIsAuthenticated;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Guards settings/routes.php: every state-changing route must be CSRF protected unless
 * it is listed below with a reason. A new route without protection fails this test.
 */
#[CoversNothing]
class RouteSecurityTest extends TestCase
{
    /**
     * Non-GET routes that intentionally run without CsrfProtection.
     * Key: "METHOD path" exactly as registered (including the /api group prefix).
     */
    private const array CSRF_ALLOWLIST = [
        // Login and logout: login has no session (and thus no token) yet; both are JSON
        // API calls also used by non-browser clients with X-Movary-Token.
        'POST /api/authentication/token' => 'login, no session yet',
        'DELETE /api/authentication/token' => 'logout, also used by token clients',

        // Public REST API, authenticated by X-Movary-Token for non-browser clients.
        // Browsers' cookies are SameSite=Lax, so cross-site POSTs carry no session.
        'POST /api/users/{username:[a-zA-Z0-9]+}/history/movies' => 'token API',
        'DELETE /api/users/{username:[a-zA-Z0-9]+}/history/movies' => 'token API',
        'PUT /api/users/{username:[a-zA-Z0-9]+}/history/movies' => 'token API',
        'POST /api/users/{username:[a-zA-Z0-9]+}/watchlist/movies' => 'token API',
        'DELETE /api/users/{username:[a-zA-Z0-9]+}/watchlist/movies' => 'token API',
        'POST /api/users/{username:[a-zA-Z0-9]+}/played/movies' => 'token API',
        'DELETE /api/users/{username:[a-zA-Z0-9]+}/played/movies' => 'token API',
        'PUT /api/users/{username:[a-zA-Z0-9]+}/played/movies' => 'token API',
        'POST /api/movies/add' => 'token API',

        // Media server webhooks: called server-to-server, authenticated by the secret id in the URL.
        'POST /api/webhook/plex/{id:.+}' => 'webhook',
        'POST /api/webhook/jellyfin/{id:.+}' => 'webhook',
        'POST /api/webhook/emby/{id:.+}' => 'webhook',
        'POST /api/webhook/kodi/{id:.+}' => 'webhook',
    ];

    /** @var list<array{method: string, path: string, middleware: list<string>}>|null */
    private static ?array $routes = null;

    public function testEveryStateChangingRouteIsCsrfProtectedOrAllowlisted() : void
    {
        $unprotected = [];
        foreach (self::routes() as $route) {
            if (in_array($route['method'], ['GET', 'HEAD'], true) === true) {
                continue;
            }

            $key = $route['method'] . ' ' . $route['path'];
            if (in_array(CsrfProtection::class, $route['middleware'], true) === false && isset(self::CSRF_ALLOWLIST[$key]) === false) {
                $unprotected[] = $key;
            }
        }

        self::assertSame([], $unprotected, 'State-changing routes without CsrfProtection');
    }

    public function testAllowlistHasNoStaleEntries() : void
    {
        $registered = array_map(fn(array $route) => $route['method'] . ' ' . $route['path'], self::routes());

        self::assertSame([], array_values(array_diff(array_keys(self::CSRF_ALLOWLIST), $registered)));
    }

    public function testAllowlistedRoutesDoNotUseCookieAuthentication() : void
    {
        // An allowlisted route that authenticates via the web session would be CSRF-able.
        $cookieAuthenticated = [];
        foreach (self::routes() as $route) {
            $key = $route['method'] . ' ' . $route['path'];
            $isDeprecatedWebhook = str_starts_with($route['path'], '/old/');
            if (isset(self::CSRF_ALLOWLIST[$key]) === true
                && $isDeprecatedWebhook === false
                && in_array(UserIsAuthenticated::class, $route['middleware'], true) === true) {
                $cookieAuthenticated[] = $key;
            }
        }

        self::assertSame([], $cookieAuthenticated);
    }

    public function testAdminRoutesRequireAuthenticationFirst() : void
    {
        // UserIsAdmin assumes a logged-in user; it must never run (or be relied on) alone.
        $misordered = [];
        foreach (self::routes() as $route) {
            $adminPosition = array_search(UserIsAdmin::class, $route['middleware'], true);
            if ($adminPosition === false) {
                continue;
            }

            $authenticationPosition = array_search(UserIsAuthenticated::class, $route['middleware'], true);
            if ($authenticationPosition === false || $authenticationPosition > $adminPosition) {
                $misordered[] = $route['method'] . ' ' . $route['path'];
            }
        }

        self::assertSame([], $misordered);
    }

    /**
     * @return list<array{method: string, path: string, middleware: list<string>}>
     */
    private static function routes() : array
    {
        // routes.php declares global functions, so it may only be loaded once per process.
        if (self::$routes !== null) {
            return self::$routes;
        }

        $collector = new class(new Std(), new GroupCountBased()) extends RouteCollector {
            /** @var list<array{method: string, path: string, middleware: list<string>}> */
            public array $recorded = [];

            public function addRoute($httpMethod, $route, $handler) : void
            {
                // RouterService registers exactly one method per route, with a handler array.
                $this->recorded[] = [
                    'method' => is_string($httpMethod) === true ? $httpMethod : implode(',', $httpMethod),
                    'path' => $this->currentGroupPrefix . $route,
                    'middleware' => array_values((array)($handler['middleware'] ?? [])),
                ];
            }
        };

        $registerRoutes = require __DIR__ . '/../../../settings/routes.php';
        $registerRoutes($collector);

        self::assertNotEmpty($collector->recorded);

        return self::$routes = $collector->recorded;
    }
}
