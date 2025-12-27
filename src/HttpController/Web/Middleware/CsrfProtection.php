<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Service\CsrfTokenService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;

class CsrfProtection
{
    public function __construct(
        private readonly CsrfTokenService $csrfTokenService,
    ) {
    }

    public function __invoke(Request $request, array $routeParameters, callable $next) : Response
    {
        // Only check POST, PUT, DELETE requests
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $next($request, $routeParameters);
        }

        // Get token from POST data
        $postData = $request->getPostParameters();
        $token = $postData['_csrf_token'] ?? null;

        // Validate token
        if (!$this->csrfTokenService->validateToken($token)) {
            return Response::createForbidden('CSRF token validation failed');
        }

        return $next($request, $routeParameters);
    }
}
