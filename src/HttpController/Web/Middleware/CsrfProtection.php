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
        // Safe methods must not change state, so only they skip the check. Listing the
        // unsafe ones instead would silently let new verbs (e.g. PATCH) through.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) === true) {
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

        // Validate token (a non-string value from JSON, e.g. true or an array, is simply invalid)
        if (is_string($token) === false || $this->csrfTokenService->validateToken($token) === false) {
            return Response::createForbidden();
        }

        return null;
    }
}
