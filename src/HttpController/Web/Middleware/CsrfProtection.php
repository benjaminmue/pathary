<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Service\CsrfTokenService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;

class CsrfProtection implements MiddlewareInterface
{
    public function __construct(
        private readonly CsrfTokenService $csrfTokenService,
    ) {
    }

    public function __invoke(Request $request) : ?Response
    {
        // Only check POST, PUT, DELETE requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return null;
        }

        // Get token from POST data (form-encoded) or JSON body
        $token = null;
        $postData = $request->getPostParameters();

        if (isset($postData['_csrf_token'])) {
            // Form-encoded data
            $token = $postData['_csrf_token'];
        } else {
            // Try JSON body
            $body = $request->getBody();
            if (!empty($body)) {
                $jsonData = json_decode($body, true);
                if (is_array($jsonData) && isset($jsonData['_csrf_token'])) {
                    $token = $jsonData['_csrf_token'];
                }
            }
        }

        // Validate token
        if (!$this->csrfTokenService->validateToken($token)) {
            return Response::createForbidden('CSRF token validation failed');
        }

        return null;
    }
}
