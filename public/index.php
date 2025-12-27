<?php declare(strict_types=1);

/** @var DI\Container $container */

use Movary\HttpController\Web\ErrorController;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Psr\Log\LoggerInterface;

$container = require(__DIR__ . '/../bootstrap.php');
$httpRequest = $container->get(Request::class);

// Security headers - applied to all responses
$securityHeaders = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'SAMEORIGIN',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' https://image.tmdb.org data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'self';",
];

try {
    $dispatcher = FastRoute\simpleDispatcher(
        require(__DIR__ . '/../settings/routes.php'),
    );

    $uri = $_SERVER['REQUEST_URI'];

    // Strip query string (?foo=bar) and decode URI
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    $uri = rawurldecode($uri);

    $routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            $response = Response::createNotFound();
            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $response = Response::createMethodNotAllowed();
            break;
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1]['handler'];

            $httpRequest->addRouteParameters($routeInfo[2]);

            foreach ($routeInfo[1]['middleware'] as $middleware) {
                $middlewareResponse = $container->call($middleware, [$httpRequest]);

                if ($middlewareResponse instanceof Response) {
                    $response = $middlewareResponse;
                    break 2;
                }
            }

            $response = $container->call($handler, [$httpRequest]);
            break;
        default:
            throw new LogicException('Unhandled dispatcher status :' . $routeInfo[0]);
    }

    // Handle different error responses for web routes (not API)
    if (str_starts_with($uri, '/api') === false) {
        $statusCode = $response->getStatusCode()->getCode();

        if ($statusCode === 404) {
            $response = $container->get(ErrorController::class)->renderNotFound($httpRequest);
        } elseif ($statusCode === 401) {
            $response = $container->get(ErrorController::class)->renderUnauthorized($httpRequest);
        } elseif ($statusCode === 403 && $response->getBody() === null) {
            // Only render 403 page if there's no redirect (empty body)
            $response = $container->get(ErrorController::class)->renderForbidden($httpRequest);
        }
    }
} catch (Throwable $t) {
    $container->get(LoggerInterface::class)->emergency($t->getMessage(), ['exception' => $t]);

    if (str_starts_with($uri, '/api') === false) {
        $response = $container->get(ErrorController::class)->renderInternalServerError($httpRequest);
    } else {
        // For API endpoints, return generic JSON error without exposing details
        $response = Response::create(StatusCode::createInternalServerError());
    }
}

header((string)$response->getStatusCode());

// Apply security headers
foreach ($securityHeaders as $name => $value) {
    header($name . ': ' . $value);
}

foreach ($response->getHeaders() as $header) {
    header((string)$header);
}

echo $response->getBody();

exit(0);
