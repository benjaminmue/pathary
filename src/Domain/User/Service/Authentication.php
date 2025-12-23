<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\Domain\User\Exception\EmailNotFound;
use Movary\Domain\User\Exception\InvalidPassword;
use Movary\Domain\User\Exception\InvalidTotpCode;
use Movary\Domain\User\Exception\MissingTotpCode;
use Movary\Domain\User\Repository\RecoveryCodeRepository;
use Movary\Domain\User\Repository\TrustedDeviceRepository;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Movary\Domain\User\UserRepository;
use Movary\HttpController\Web\CreateUserController;
use Movary\Util\SessionWrapper;
use Movary\Util\TrustedDeviceCookie;
use Movary\ValueObject\DateTime;
use Movary\ValueObject\Http\Request;
use RuntimeException;

class Authentication
{
    private const string AUTHENTICATION_COOKIE_NAME = 'id';

    private const int MAX_EXPIRATION_AGE_IN_DAYS = 3650; // 10 years for persistent login

    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserApi $userApi,
        private readonly SessionWrapper $sessionWrapper,
        private readonly TwoFactorAuthenticationApi $twoFactorAuthenticationApi,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly SecurityAuditService $securityAuditService,
    ) {
    }

    public function createExpirationDate(int $days = 1) : DateTime
    {
        $timestamp = strtotime('+' . $days . ' day');

        if ($timestamp === false) {
            throw new RuntimeException('Could not generate timestamp for auth token expiration date.');
        }

        return DateTime::createFromString(date('Y-m-d H:i:s', $timestamp));
    }

    public function deleteToken(string $token) : void
    {
        $this->repository->deleteAuthToken($token);
    }

    public function findUserAndVerifyAuthentication(
        string $email,
        string $password,
        ?int $userTotpCode = null,
        ?string $recoveryCode = null,
        ?string $trustedDeviceToken = null,
    ) : UserEntity {
        $user = $this->repository->findUserByEmail($email);

        if ($user === null) {
            $this->securityAuditService->log(
                0, // User ID unknown at this point
                SecurityAuditService::EVENT_LOGIN_FAILED_PASSWORD,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            throw EmailNotFound::create();
        }

        if ($this->userApi->isValidPassword($user->getId(), $password) === false) {
            $this->securityAuditService->log(
                $user->getId(),
                SecurityAuditService::EVENT_LOGIN_FAILED_PASSWORD,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            throw InvalidPassword::create();
        }

        $totpUri = $this->userApi->findTotpUri($user->getId());
        if ($totpUri === null) {
            // No 2FA configured, login successful
            $this->securityAuditService->log(
                $user->getId(),
                SecurityAuditService::EVENT_LOGIN_SUCCESS,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            return $user;
        }

        // 2FA is enabled - check for trusted device first
        if ($trustedDeviceToken !== null) {
            $trustedDevice = $this->trustedDeviceService->verifyTrustedDevice($trustedDeviceToken, $user->getId());
            if ($trustedDevice !== null) {
                // Trusted device is valid, skip 2FA
                $this->securityAuditService->log(
                    $user->getId(),
                    SecurityAuditService::EVENT_LOGIN_SUCCESS,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ['trusted_device' => true]
                );
                return $user;
            }
        }

        // No trusted device or invalid - require 2FA or recovery code
        if ($userTotpCode === null && $recoveryCode === null) {
            throw MissingTotpCode::create();
        }

        // Try recovery code first if provided
        if ($recoveryCode !== null && $this->recoveryCodeService->verifyRecoveryCode($user->getId(), $recoveryCode) === true) {
            $this->securityAuditService->log(
                $user->getId(),
                SecurityAuditService::EVENT_RECOVERY_CODE_USED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            return $user;
        }

        if ($recoveryCode !== null) {
            $this->securityAuditService->log(
                $user->getId(),
                SecurityAuditService::EVENT_LOGIN_FAILED_RECOVERY_CODE,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
        }

        // Try TOTP code
        if ($userTotpCode !== null && $this->twoFactorAuthenticationApi->verifyTotpUri($user->getId(), $userTotpCode) === false) {
            $this->securityAuditService->log(
                $user->getId(),
                SecurityAuditService::EVENT_LOGIN_FAILED_TOTP,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            throw InvalidTotpCode::create();
        }

        if ($userTotpCode !== null) {
            $this->securityAuditService->log(
                $user->getId(),
                SecurityAuditService::EVENT_LOGIN_SUCCESS,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            return $user;
        }

        // Neither recovery code nor TOTP was valid
        throw InvalidTotpCode::create();
    }

    public function getCurrentUser() : UserEntity
    {
        return $this->userApi->fetchUser($this->getCurrentUserId());
    }

    public function getCurrentUserId() : int
    {
        $userId = $this->sessionWrapper->find('userId');
        $token = (string)filter_input(INPUT_COOKIE, self::AUTHENTICATION_COOKIE_NAME);

        if ($userId === null && $token !== '') {
            $userId = $this->repository->findUserIdByAuthToken($token);
            $this->sessionWrapper->set('userId', $userId);
        }

        if ($userId === null) {
            throw new RuntimeException('Could not find a current user');
        }

        return $userId;
    }

    public function getToken(Request $request) : ?string
    {
        $tokenInCookie = (string)filter_input(INPUT_COOKIE, self::AUTHENTICATION_COOKIE_NAME);
        if ($tokenInCookie !== '') {
            return $tokenInCookie;
        }

        return $request->getHeaders()['X-Movary-Token'] ?? null;
    }

    public function getUserIdByToken(Request $request) : ?int
    {
        $token = $this->getToken($request);
        if ($token === null) {
            return null;
        }

        if ($this->isValidToken($token) === false) {
            return null;
        }

        return $this->userApi->findByToken($token)?->getId();
    }

    public function isUserAuthenticatedWithCookie() : bool
    {
        $token = (string)filter_input(INPUT_COOKIE, self::AUTHENTICATION_COOKIE_NAME);

        if ($token !== '' && $this->isValidAuthToken($token) === true) {
            return true;
        }

        if (empty($token) === false) {
            unset($_COOKIE[self::AUTHENTICATION_COOKIE_NAME]);

            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

            setcookie(
                self::AUTHENTICATION_COOKIE_NAME,
                '',
                [
                    'expires' => 1,
                    'path' => '/',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
            );
        }

        return false;
    }

    public function isUserPageVisibleForApiRequest(Request $request, UserEntity $targetUser) : bool
    {
        $requestUserId = $this->getUserIdByToken($request);

        return $this->isUserPageVisibleForUser($targetUser, $requestUserId);
    }

    public function isUserPageVisibleForWebRequest(UserEntity $targetUser) : bool
    {
        $requestUserId = null;
        if ($this->isUserAuthenticatedWithCookie() === true) {
            $requestUserId = $this->getCurrentUserId();
        }

        return $this->isUserPageVisibleForUser($targetUser, $requestUserId);
    }

    public function isValidToken(string $token) : bool
    {
        return match (true) {
            $this->isValidApiToken($token) => true,
            $this->isValidAuthToken($token) => true,
            default => false,
        };
    }

    /**
     * @return array{user: UserEntity, token: string}
     */
    public function login(
        string $email,
        string $password,
        bool $rememberMe,
        string $deviceName,
        string $userAgent,
        ?int $userTotpInput = null,
        ?string $recoveryCode = null,
        bool $trustDevice = false,
    ) : array {
        // Opportunistic cleanup of expired trusted devices
        $this->trustedDeviceService->cleanupExpiredDevices();

        // Check for existing trusted device token
        $trustedDeviceToken = TrustedDeviceCookie::get();

        $user = $this->findUserAndVerifyAuthentication($email, $password, $userTotpInput, $recoveryCode, $trustedDeviceToken);

        $authTokenExpirationDate = $this->createExpirationDate();
        if ($rememberMe === true) {
            $authTokenExpirationDate = $this->createExpirationDate(self::MAX_EXPIRATION_AGE_IN_DAYS);
        }

        $token = $this->setAuthenticationToken($user->getId(), $deviceName, $userAgent, $authTokenExpirationDate);

        $userAndToken = ['user' => $user, 'token' => $token];

        if ($deviceName === CreateUserController::PATHARY_WEB_CLIENT) {
            $this->setAuthenticationCookieAndNewSession($user->getId(), $token, $authTokenExpirationDate);

            // Create trusted device if requested and 2FA is enabled
            if ($trustDevice === true && $this->userApi->findTotpUri($user->getId()) !== null) {
                $deviceToken = $this->trustedDeviceService->createTrustedDevice(
                    $user->getId(),
                    null, // Let service auto-generate device name from user agent
                    $userAgent,
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
                TrustedDeviceCookie::set($deviceToken);

                $this->securityAuditService->log(
                    $user->getId(),
                    SecurityAuditService::EVENT_TRUSTED_DEVICE_ADDED,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $userAgent,
                );
            }
        }

        return $userAndToken;
    }

    public function logout() : void
    {
        $userId = null;
        try {
            $userId = $this->getCurrentUserId();
        } catch (RuntimeException) {
            // User not logged in, ignore
        }

        $token = (string)filter_input(INPUT_COOKIE, 'id');

        if ($token !== '') {
            $this->deleteToken($token);
            unset($_COOKIE[self::AUTHENTICATION_COOKIE_NAME]);

            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

            // Clear cookie with same options used when setting it
            setcookie(
                self::AUTHENTICATION_COOKIE_NAME,
                '',
                [
                    'expires' => 1, // Past timestamp to delete
                    'path' => '/',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
            );
        }

        // Do NOT clear trusted device cookie - it should persist across logout/login
        // Users can revoke devices manually from their profile if needed

        if ($userId !== null) {
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_LOGOUT,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
        }

        $this->sessionWrapper->destroy();
        $this->sessionWrapper->start();
    }

    public function setAuthenticationCookieAndNewSession(int $userId, string $token, DateTime $expirationDate) : void
    {
        $this->sessionWrapper->destroy();
        $this->sessionWrapper->start();

        // Regenerate session ID to prevent session fixation
        $this->sessionWrapper->regenerateId();

        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(
            self::AUTHENTICATION_COOKIE_NAME,
            $token,
            [
                'expires' => (int)$expirationDate->format('U'),
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );

        $this->sessionWrapper->set('userId', $userId);
    }

    private function isUserPageVisibleForUser(UserEntity $targetUser, ?int $requestUserId) : bool
    {
        $privacyLevel = $targetUser->getPrivacyLevel();

        if ($privacyLevel === 2) {
            return true;
        }

        if ($privacyLevel === 1 && $requestUserId !== null) {
            return true;
        }

        return $targetUser->getId() === $requestUserId;
    }

    private function isValidApiToken(string $token) : bool
    {
        return $this->userApi->findUserIdByApiToken($token) !== null;
    }

    private function isValidAuthToken(string $token) : bool
    {
        $tokenExpirationDate = $this->repository->findAuthTokenExpirationDate($token);

        if ($tokenExpirationDate === null || $tokenExpirationDate->isAfter(DateTime::create()) === false) {
            if ($tokenExpirationDate !== null) {
                $this->repository->deleteAuthToken($token);
            }

            return false;
        }

        return true;
    }

    private function setAuthenticationToken(int $userId, string $deviceName, string $userAgent, DateTime $expirationDate) : string
    {
        // Use 32 bytes (256 bits) for strong security
        $token = bin2hex(random_bytes(32));

        $this->repository->createAuthToken($userId, $token, $deviceName, $userAgent, $expirationDate);

        return $token;
    }
}
