<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\RecoveryCodeService;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TrustedDeviceService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\Service\TwoFactorAuthenticationFactory;
use Movary\Domain\User\Service\Validator;
use Movary\Domain\User\UserApi;
use Movary\Util\Json;
use Movary\Util\TrustedDeviceCookie;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use RuntimeException;
use Twig\Environment;

class ProfileSecurityController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
        private readonly TwoFactorAuthenticationApi $twoFactorAuthenticationApi,
        private readonly TwoFactorAuthenticationFactory $twoFactorAuthenticationFactory,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly SecurityAuditService $securityAuditService,
        private readonly Validator $validator,
    ) {
    }

    public function show(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);

        $totpUri = $this->twoFactorAuthenticationApi->findTotpUri($userId);
        $totpEnabled = $totpUri !== null;

        $recoveryCodesCount = $this->recoveryCodeService->getRemainingCodeCount($userId);
        $trustedDevices = $this->trustedDeviceService->getTrustedDevices($userId);

        // Get recent events but exclude user management events (those are admin actions, not user actions)
        $allEvents = $this->securityAuditService->getRecentEvents($userId, 50);
        $userManagementEvents = [
            SecurityAuditService::EVENT_USER_CREATED,
            SecurityAuditService::EVENT_USER_UPDATED,
            SecurityAuditService::EVENT_USER_DELETED,
            SecurityAuditService::EVENT_USER_PASSWORD_CHANGED_BY_ADMIN,
            SecurityAuditService::EVENT_USER_WELCOME_EMAIL_SENT,
            SecurityAuditService::EVENT_USER_WELCOME_EMAIL_FAILED,
        ];

        // Filter out user management events - users should only see their own actions, not admin actions on their account
        $securityEvents = array_filter($allEvents, function($event) use ($userManagementEvents) {
            return !in_array($event['event_type'], $userManagementEvents, true);
        });

        // Take only first 20 after filtering
        $securityEvents = array_slice($securityEvents, 0, 20);

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/profile-security.twig', [
                'userName' => $user->getName(),
                'totpEnabled' => $totpEnabled,
                'recoveryCodesCount' => $recoveryCodesCount,
                'trustedDevices' => $trustedDevices,
                'securityEvents' => $securityEvents,
                'success' => $request->getGetParameters()['success'] ?? null,
                'error' => $request->getGetParameters()['error'] ?? null,
            ]),
        );
    }

    public function enableTotp(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);

        if ($user->hasCoreAccountChangesDisabled() === true) {
            return Response::createJson(
                Json::encode(['error' => 'Account changes are disabled for this user.']),
                StatusCode::createForbidden()
            );
        }

        // Check if TOTP is already enabled
        if ($this->twoFactorAuthenticationApi->findTotpUri($userId) !== null) {
            return Response::createJson(
                Json::encode(['error' => '2FA is already enabled.']),
                StatusCode::createBadRequest()
            );
        }

        // Generate new TOTP secret
        $totp = $this->twoFactorAuthenticationFactory->createTotp($user->getName());
        $totpUri = $totp->getProvisioningUri();

        // Store temporarily in session for verification (session already started by framework)
        $_SESSION['pending_totp_uri'] = $totpUri;

        return Response::createJson(
            Json::encode([
                'totpUri' => $totpUri,
                'secret' => $totp->getSecret(),
            ])
        );
    }

    public function verifyAndSaveTotp(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);

        if ($user->hasCoreAccountChangesDisabled() === true) {
            return Response::createJson(
                Json::encode(['error' => 'Account changes are disabled for this user.']),
                StatusCode::createForbidden()
            );
        }

        $data = Json::decode($request->getBody());
        $verificationCode = (int)($data['code'] ?? 0);

        $pendingTotpUri = $_SESSION['pending_totp_uri'] ?? null;

        if ($pendingTotpUri === null) {
            return Response::createJson(
                Json::encode(['error' => 'No pending 2FA setup found.']),
                StatusCode::createBadRequest()
            );
        }

        // Verify the code
        if ($this->twoFactorAuthenticationApi->verifyTotpUri($userId, $verificationCode, $pendingTotpUri) === false) {
            return Response::createJson(
                Json::encode(['error' => 'Invalid verification code.']),
                StatusCode::createBadRequest()
            );
        }

        // Save TOTP URI
        $this->twoFactorAuthenticationApi->updateTotpUri($userId, $pendingTotpUri);
        unset($_SESSION['pending_totp_uri']);

        // Generate recovery codes
        $recoveryCodes = $this->recoveryCodeService->generateRecoveryCodes($userId);

        // Log security event
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_TOTP_ENABLED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return Response::createJson(
            Json::encode([
                'success' => true,
                'recoveryCodes' => $recoveryCodes,
            ])
        );
    }

    public function disableTotp(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);

        if ($user->hasCoreAccountChangesDisabled() === true) {
            return Response::createJson(
                Json::encode(['error' => 'Account changes are disabled for this user.']),
                StatusCode::createForbidden()
            );
        }

        $data = Json::decode($request->getBody());
        $password = $data['password'] ?? '';

        // Verify password before disabling 2FA
        if ($this->userApi->isValidPassword($userId, $password) === false) {
            return Response::createJson(
                Json::encode(['error' => 'Invalid password.']),
                StatusCode::createBadRequest()
            );
        }

        // Disable TOTP
        $this->twoFactorAuthenticationApi->deleteTotp($userId);

        // Delete all recovery codes and trusted devices
        $this->recoveryCodeService->deleteAllRecoveryCodes($userId);
        $this->trustedDeviceService->revokeAllTrustedDevices($userId);
        TrustedDeviceCookie::clear();

        // Log security event
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_TOTP_DISABLED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return Response::createJson(Json::encode(['success' => true]));
    }

    public function regenerateRecoveryCodes(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();

        // Check if 2FA is enabled
        if ($this->twoFactorAuthenticationApi->findTotpUri($userId) === null) {
            return Response::createJson(
                Json::encode(['error' => '2FA must be enabled first.']),
                StatusCode::createBadRequest()
            );
        }

        $recoveryCodes = $this->recoveryCodeService->generateRecoveryCodes($userId);

        // Log security event
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_RECOVERY_CODES_GENERATED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return Response::createJson(
            Json::encode([
                'success' => true,
                'recoveryCodes' => $recoveryCodes,
            ])
        );
    }

    public function revokeTrustedDevice(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $deviceId = (int)$request->getRouteParameters()['deviceId'];

        // Verify the device belongs to the user and get the device entity
        $devices = $this->trustedDeviceService->getTrustedDevices($userId);
        $deviceToRevoke = null;
        foreach ($devices as $device) {
            if ($device->getId() === $deviceId) {
                $deviceToRevoke = $device;
                break;
            }
        }

        if ($deviceToRevoke === null) {
            return Response::createJson(
                Json::encode(['error' => 'Device not found.']),
                StatusCode::createNotFound()
            );
        }

        // Check if the current request has a cookie that matches this device
        $cookieToken = TrustedDeviceCookie::get();
        $isCurrentDevice = false;
        if ($cookieToken !== null) {
            // Verify if the cookie token matches the device being revoked
            if (password_verify($cookieToken, $deviceToRevoke->getTokenHash()) === true) {
                $isCurrentDevice = true;
            }
        }

        // Revoke the device
        $this->trustedDeviceService->revokeTrustedDevice($deviceId);

        // If this is the current device, clear the cookie
        if ($isCurrentDevice === true) {
            TrustedDeviceCookie::clear();
        }

        // Log security event
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_TRUSTED_DEVICE_REMOVED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            ['device_id' => $deviceId, 'is_current_device' => $isCurrentDevice]
        );

        return Response::createJson(Json::encode([
            'success' => true,
            'message' => 'Trusted device has been removed.',
        ]));
    }

    public function revokeAllTrustedDevices(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();

        // Revoke all trusted devices for this user
        $this->trustedDeviceService->revokeAllTrustedDevices($userId);

        // Clear the trusted device cookie
        TrustedDeviceCookie::clear();

        // Log security event
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_ALL_TRUSTED_DEVICES_REMOVED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return Response::createJson(Json::encode([
            'success' => true,
            'message' => 'All trusted devices have been removed.',
        ]));
    }

    public function changePassword(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);

        if ($user->hasCoreAccountChangesDisabled() === true) {
            return Response::createJson(
                Json::encode(['error' => 'Account changes are disabled for this user.']),
                StatusCode::createForbidden()
            );
        }

        $data = Json::decode($request->getBody());
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        // Verify current password
        if ($this->userApi->isValidPassword($userId, $currentPassword) === false) {
            return Response::createJson(
                Json::encode(['error' => 'Current password is incorrect.']),
                StatusCode::createBadRequest()
            );
        }

        // Validate new password against comprehensive policy
        try {
            $this->validator->ensurePasswordIsValid($newPassword);
        } catch (PasswordPolicyViolation $e) {
            return Response::createJson(
                Json::encode(['error' => $e->getMessage()]),
                StatusCode::createBadRequest()
            );
        }

        // Update password
        $this->userApi->updatePassword($userId, $newPassword);

        // Revoke all trusted devices after password change (security best practice)
        $this->trustedDeviceService->revokeAllTrustedDevices($userId);
        TrustedDeviceCookie::clear();

        // Log security event
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_PASSWORD_CHANGED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return Response::createJson(Json::encode(['success' => true]));
    }

    public function getSecurityEvents(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $limit = (int)($request->getGetParameters()['limit'] ?? 20);

        // Get events but fetch more to account for filtering
        $allEvents = $this->securityAuditService->getRecentEvents($userId, $limit * 3);

        // Exclude user management events (admin actions on the user's account)
        $userManagementEvents = [
            SecurityAuditService::EVENT_USER_CREATED,
            SecurityAuditService::EVENT_USER_UPDATED,
            SecurityAuditService::EVENT_USER_DELETED,
            SecurityAuditService::EVENT_USER_PASSWORD_CHANGED_BY_ADMIN,
            SecurityAuditService::EVENT_USER_WELCOME_EMAIL_SENT,
            SecurityAuditService::EVENT_USER_WELCOME_EMAIL_FAILED,
        ];

        // Filter out user management events
        $filteredEvents = array_filter($allEvents, function($event) use ($userManagementEvents) {
            return !in_array($event['event_type'], $userManagementEvents, true);
        });

        // Take only requested number after filtering
        $events = array_slice($filteredEvents, 0, $limit);

        // Format events for display
        $formattedEvents = array_map(function ($event) {
            return [
                'id' => $event['id'],
                'eventType' => $event['event_type'],
                'eventLabel' => $this->securityAuditService->getEventTypeLabel($event['event_type']),
                'ipAddress' => $event['ip_address'],
                'userAgent' => $event['user_agent'],
                'metadata' => $event['metadata'],
                'createdAt' => $event['created_at'],
            ];
        }, $events);

        return Response::createJson(Json::encode(['events' => $formattedEvents]));
    }
}
