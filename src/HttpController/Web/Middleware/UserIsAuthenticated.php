<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Service\ApplicationUrlService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\RelativeUrl;

class UserIsAuthenticated implements MiddlewareInterface
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly ApplicationUrlService $urlService,
        private readonly TwoFactorAuthenticationApi $twoFactorAuthenticationApi,
    ) {
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function __invoke(Request $request) : ?Response
    {
        if ($this->authenticationService->isUserAuthenticatedWithCookie() === false) {
            return Response::createForbiddenRedirect(
                $this->urlService->createApplicationUrl(
                    RelativeUrl::create($_SERVER['REQUEST_URI']),
                ),
                $this->urlService->createApplicationUrl(),
            );
        }

        // Two-factor authentication is mandatory. A logged-in user who has not set
        // up 2FA is forced to the security page until they do. The security page and
        // its TOTP endpoints are exempt to avoid a redirect loop. (Logout runs over
        // DELETE /api/authentication/token, which never hits this web middleware.)
        if ($this->userMustSetUpTwoFactor() === true) {
            return Response::createSeeOther('/profile/security');
        }

        return null;
    }

    private function userMustSetUpTwoFactor() : bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

        // Exact page or its sub-paths only (avoid matching e.g. /profile/security-x).
        if ($path === '/profile/security' || str_starts_with($path, '/profile/security/') === true) {
            return false;
        }

        $userId = $this->authenticationService->getCurrentUserId();

        return $this->twoFactorAuthenticationApi->findTotpUri($userId) === null;
    }
}
