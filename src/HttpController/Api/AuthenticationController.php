<?php declare(strict_types=1);

namespace Movary\HttpController\Api;

use Movary\Domain\User\Exception\InvalidCredentials;
use Movary\Domain\User\Exception\InvalidTotpCode;
use Movary\Domain\User\Exception\MissingTotpCode;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\UserApi;
use Movary\Service\Exception\LoginRateLimitExceeded;
use Movary\Service\LoginRateLimiterService;
use Movary\Util\Json;
use Movary\Util\RequestContext;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

class AuthenticationController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
        private readonly TwoFactorAuthenticationApi $twoFactorAuthenticationApi,
        private readonly LoginRateLimiterService $loginRateLimiter,
    ) {
    }

    public function createToken(Request $request) : Response
    {
        $tokenRequestBody = Json::decode($request->getBody());
        $headers = $request->getHeaders();

        $validationError = $this->validateTokenRequest($tokenRequestBody, $headers);
        if ($validationError !== null) {
            return $validationError;
        }

        $requestClient = $headers['X-Movary-Client'];
        $totpCode = $this->readTotpCode($tokenRequestBody['totpCode'] ?? null);
        $recoveryCode = empty($tokenRequestBody['recoveryCode']) === true ? null : trim($tokenRequestBody['recoveryCode']);
        $rememberMe = $tokenRequestBody['rememberMe'] ?? false;
        $trustDevice = $tokenRequestBody['trustDevice'] ?? false;

        // Persistent brute-force protection per IP and per account. This endpoint is
        // the only login choke point (the web login form posts here too) and runs
        // before authentication, so a session-based limiter would be trivially bypassed.
        $clientIp = RequestContext::clientIp();
        $email = $tokenRequestBody['email'];
        try {
            $this->loginRateLimiter->ensureNotRateLimited($clientIp, $email);
        } catch (LoginRateLimitExceeded $e) {
            return Response::createJson(
                Json::encode([
                    'error' => 'RateLimitExceeded',
                    'message' => $e->getMessage(),
                ]),
                StatusCode::createTooManyRequests(),
            );
        }

        try {
            $userAndAuthToken = $this->authenticationService->login(
                $email,
                $tokenRequestBody['password'],
                (bool)$rememberMe,
                $requestClient,
                $request->getUserAgent(),
                $totpCode,
                $recoveryCode,
                (bool)$trustDevice,
            );
        } catch (MissingTotpCode) {
            return Response::createBadRequest(
                Json::encode([
                    'error' => 'MissingTotpCode',
                    'message' => 'Two-factor authentication code missing'
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (InvalidTotpCode) {
            $this->loginRateLimiter->logAttempt($clientIp, $email, false);

            return Response::createUnauthorized(
                Json::encode([
                    'error' => 'InvalidTotpCode',
                    'message' => 'Two-factor authentication code wrong'
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (InvalidCredentials $e) {
            $this->loginRateLimiter->logAttempt($clientIp, $email, false);

            return Response::createUnauthorized(
                Json::encode([
                    'error' => 'InvalidCredentials',
                    'message' => $e->getMessage()
                ]),
                [Header::createContentTypeJson()],
            );
        }

        // Credentials (and TOTP, if configured) were valid.
        $this->loginRateLimiter->logAttempt($clientIp, $email, true);

        $user = $userAndAuthToken['user'];

        if ($this->twoFactorAuthenticationApi->findTotpUri($user->getId()) === null) {
            return $this->respondToMissingTwoFactorEnrollment($requestClient, $userAndAuthToken['token']);
        }

        return Response::createJson(
            Json::encode([
                'authToken' => $userAndAuthToken['token'],
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'isAdmin' => $user->isAdmin(),
                ]
            ]),
        );
    }

    /**
     * Two-factor authentication is mandatory. (Users who already have 2FA were verified
     * by login().) A user who has not enrolled yet, e.g. a freshly invited one, can only
     * do so in the web interface:
     * - web client: keep the cookie session that login() created and send the user to
     *   the enrollment page; UserIsAuthenticated confines the session to
     *   /profile/security until 2FA is set up.
     * - API clients cannot run the QR enrollment, so the token just created is revoked
     *   and the client is pointed at the web setup.
     */
    private function respondToMissingTwoFactorEnrollment(string $requestClient, string $token) : Response
    {
        if ($requestClient === Authentication::PATHARY_WEB_CLIENT) {
            return Response::createJson(
                Json::encode([
                    'twoFactorSetupRequired' => true,
                    'redirectPath' => '/profile/security',
                ]),
            );
        }

        $this->authenticationService->deleteToken($token);

        return Response::createJson(
            Json::encode([
                'error' => 'TwoFactorSetupRequired',
                'message' => 'Two-factor authentication is mandatory. Log in via the web interface to set it up before using the API.',
            ]),
            StatusCode::createForbidden(),
        );
    }

    /**
     * TOTP codes stay strings so a leading "0" survives. Numeric JSON values from API
     * clients are padded back to six digits for the same reason.
     */
    private function readTotpCode(mixed $totpCode) : ?string
    {
        if (is_int($totpCode) === true) {
            return str_pad((string)$totpCode, 6, '0', STR_PAD_LEFT);
        }

        if (is_string($totpCode) === false || trim($totpCode) === '') {
            return null;
        }

        return trim($totpCode);
    }

    /**
     * @param array<string, mixed> $tokenRequestBody
     * @param array<string, mixed> $headers
     */
    private function validateTokenRequest(array $tokenRequestBody, array $headers) : ?Response
    {
        if (is_string($tokenRequestBody['email'] ?? null) === false || is_string($tokenRequestBody['password'] ?? null) === false) {
            return Response::createBadRequest(
                Json::encode([
                    'error' => 'MissingCredentials',
                    'message' => 'Email or password is missing'
                ]),
                [Header::createContentTypeJson()],
            );
        }

        if (isset($headers['X-Movary-Client']) === false) {
            return Response::createBadRequest(
                Json::encode([
                    'error' => 'MissingRequestHeader',
                    'message' => 'Missing request header X-Movary-Client'
                ]),
                [Header::createContentTypeJson()],
            );
        }

        return null;
    }

    public function destroyToken(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticatedWithCookie() === true) {
            $this->authenticationService->logout();

            return Response::CreateNoContent();
        }

        $apiToken = $this->authenticationService->getToken($request);
        if ($apiToken === null) {
            return Response::createBadRequest(
                Json::encode([
                    'error' => 'MissingAuthToken',
                    'message' => 'Authentication token header is missing'
                ]),
                [Header::createContentTypeJson()],
            );
        }

        $this->authenticationService->deleteToken($apiToken);

        return Response::CreateNoContent();
    }

    public function getTokenData(Request $request) : Response
    {
        $token = $this->authenticationService->getToken($request);
        if ($token === null) {
            return Response::createBadRequest(
                Json::encode([
                    'error' => 'MissingAuthToken',
                    'message' => 'Authentication token header is missing'
                ]),
                [Header::createContentTypeJson()],
            );
        }

        $user = $this->userApi->findByToken($token);
        if ($user === null) {
            return Response::createUnauthorized();
        }

        if ($this->authenticationService->isUserAuthenticatedWithCookie() === true && $this->authenticationService->isValidToken($token) === false) {
            return Response::createUnauthorized();
        }

        return Response::createJson(
            Json::encode([
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'isAdmin' => $user->isAdmin(),
                ]
            ]),
        );
    }
}
