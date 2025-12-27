<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Exception\EmailNotUnique;
use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Exception\PasswordTooShort;
use Movary\Domain\User\Exception\UsernameInvalidFormat;
use Movary\Domain\User\Exception\UsernameNotUnique;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\UserInvitationService;
use Movary\Domain\User\UserApi;
use Movary\Service\CsrfTokenService;
use Movary\Service\Email\CannotSendEmailException;
use Movary\Service\Email\EmailService;
use Movary\Util\Json;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Psr\Log\LoggerInterface;

class UserController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
        private readonly EmailService $emailService,
        private readonly UserInvitationService $invitationService,
        private readonly CsrfTokenService $csrfTokenService,
        private readonly SecurityAuditService $securityAuditService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createUser(Request $request) : Response
    {
        // Authorization enforced by middleware: UserIsAuthenticated + UserIsAdmin
        $requestUserData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestUserData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
            );
        }

        $sendWelcomeEmail = $requestUserData['sendWelcomeEmail'] ?? false;

        // Determine password: use provided password or generate placeholder for invitation
        if ($sendWelcomeEmail && empty($requestUserData['password'])) {
            // Generate secure random password that meets policy requirements
            // User will set their own password via invitation link, so this is just a placeholder
            $password = $this->generateCompliantPassword();
        } else {
            // Use provided password
            $password = $requestUserData['password'] ?? null;
            if ($password === null || $password === '') {
                return Response::createBadRequest('Password is required when not sending welcome email.');
            }
        }

        try {
            $this->userApi->createUser(
                $requestUserData['email'],
                $password,
                $requestUserData['name'],
                $requestUserData['isAdmin'] ?? false,
            );
        } catch (EmailNotUnique) {
            return Response::createBadRequest('Email already in use.');
        } catch (UsernameNotUnique) {
            return Response::createBadRequest('Name already in use.');
        } catch (PasswordTooShort) {
            return Response::createBadRequest('Password too short.');
        } catch (PasswordPolicyViolation $e) {
            return Response::createBadRequest($e->getMessage());
        } catch (UsernameInvalidFormat) {
            return Response::createBadRequest('Name is not in a valid format.');
        }

        // Get the newly created user for audit logging
        $newUser = $this->userApi->findUserByEmail($requestUserData['email']);
        if ($newUser === null) {
            $this->logger->error('Failed to find newly created user for audit logging', [
                'email' => $requestUserData['email'],
            ]);
            return Response::createOk('User created but audit log failed');
        }

        // Log user creation event
        $currentUser = $this->authenticationService->getCurrentUser();
        $this->securityAuditService->log(
            $currentUser->getId(),
            SecurityAuditService::EVENT_USER_CREATED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            [
                'target_user_id' => $newUser->getId(),
                'target_email' => $requestUserData['email'],
                'target_name' => $requestUserData['name'],
                'is_admin' => $requestUserData['isAdmin'] ?? false,
                'welcome_email_requested' => $sendWelcomeEmail,
            ]
        );

        // Send welcome email with invitation token if requested
        if ($sendWelcomeEmail) {
            try {
                // Generate invitation token (3 days expiration)
                $invitationToken = $this->invitationService->createInvitation($newUser->getId());

                // Send welcome email with token
                $this->emailService->sendWelcomeEmail(
                    $requestUserData['email'],
                    $requestUserData['name'],
                    $invitationToken,
                );

                // Log successful welcome email
                $this->securityAuditService->log(
                    $currentUser->getId(),
                    SecurityAuditService::EVENT_USER_WELCOME_EMAIL_SENT,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    [
                        'target_user_id' => $newUser->getId(),
                        'target_email' => $requestUserData['email'],
                    ]
                );
            } catch (CannotSendEmailException $e) {
                // Log the error but don't fail user creation
                $this->logger->warning('Failed to send welcome email to new user', [
                    'email' => $requestUserData['email'],
                    'error' => $e->getMessage(),
                ]);

                // Log failed welcome email event
                $this->securityAuditService->log(
                    $currentUser->getId(),
                    SecurityAuditService::EVENT_USER_WELCOME_EMAIL_FAILED,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    [
                        'target_user_id' => $newUser->getId(),
                        'target_email' => $requestUserData['email'],
                        'error' => $e->getMessage(),
                    ]
                );

                // Return success but indicate email wasn't sent
                return Response::createOk('User created but welcome email could not be sent: ' . $e->getMessage());
            }
        }

        return Response::createOk();
    }

    public function deleteUser(Request $request) : Response
    {
        $userId = (int)$request->getRouteParameters()['userId'];
        $currentUser = $this->authenticationService->getCurrentUser();

        if ($currentUser->getId() !== $userId && $currentUser->isAdmin() === false) {
            return Response::createForbidden();
        }

        $requestUserData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestUserData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
            );
        }

        // Get user info before deletion for audit logging
        $targetUser = $this->userApi->fetchUser($userId);
        if ($targetUser === null) {
            return Response::createNotFound();
        }

        $targetEmail = $this->userApi->findUserEmail($userId);

        // Log user deletion event before deleting
        $this->securityAuditService->log(
            $currentUser->getId(),
            SecurityAuditService::EVENT_USER_DELETED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            [
                'target_user_id' => $userId,
                'target_email' => $targetEmail,
                'target_name' => $targetUser->getName(),
                'was_admin' => $targetUser->isAdmin(),
            ]
        );

        $this->userApi->deleteUser($userId);

        return Response::createOk();
    }

    public function fetchUsers() : Response
    {
        if ($this->authenticationService->isUserAuthenticatedWithCookie() === false
            && $this->authenticationService->getCurrentUser()->isAdmin() === false) {
            return Response::createForbidden();
        }

        return Response::createJson(Json::encode($this->userApi->fetchAll()));
    }

    public function updateUser(Request $request) : Response
    {
        $userId = (int)$request->getRouteParameters()['userId'];
        $currentUser = $this->authenticationService->getCurrentUser();

        if ($currentUser->getId() !== $userId && $currentUser->isAdmin() === false) {
            return Response::createForbidden();
        }

        $requestUserData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestUserData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
            );
        }

        // Get user before update to track changes
        $targetUser = $this->userApi->fetchUser($userId);
        if ($targetUser === null) {
            return Response::createNotFound();
        }

        $changedFields = [];
        $passwordChanged = false;
        $adminStatusChanged = false;
        $previousAdminStatus = $targetUser->isAdmin();

        try {
            // Track name changes
            if ($requestUserData['name'] !== $targetUser->getName()) {
                $this->userApi->updateName($userId, $requestUserData['name']);
                $changedFields[] = 'name';
            }

            // Track email changes
            if ($requestUserData['email'] !== $targetUser->getEmail()) {
                $this->userApi->updateEmail($userId, $requestUserData['email']);
                $changedFields[] = 'email';
            }

            // Track admin status changes
            if ($requestUserData['isAdmin'] !== $targetUser->isAdmin()) {
                $this->userApi->updateIsAdmin($userId, $requestUserData['isAdmin']);
                $changedFields[] = 'is_admin';
                $adminStatusChanged = true;
            }

            // Track password changes
            if ($requestUserData['password'] !== null) {
                $this->userApi->updatePassword($userId, $requestUserData['password']);
                $passwordChanged = true;
            }
        } catch (EmailNotUnique) {
            return Response::createBadRequest('Email already in use.');
        } catch (UsernameNotUnique) {
            return Response::createBadRequest('Name already in use.');
        } catch (PasswordTooShort) {
            return Response::createBadRequest('Password too short.');
        } catch (PasswordPolicyViolation $e) {
            return Response::createBadRequest($e->getMessage());
        } catch (UsernameInvalidFormat) {
            return Response::createBadRequest('Name is not in a valid format.');
        }

        // Log user update event if any changes were made
        if (count($changedFields) > 0 || $passwordChanged) {
            $metadata = [
                'target_user_id' => $userId,
                'target_email' => $requestUserData['email'],
                'changed_fields' => $changedFields,
            ];

            if ($adminStatusChanged) {
                $metadata['admin_status_change'] = [
                    'from' => $previousAdminStatus,
                    'to' => $requestUserData['isAdmin'],
                ];
            }

            $this->securityAuditService->log(
                $currentUser->getId(),
                SecurityAuditService::EVENT_USER_UPDATED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $metadata
            );

            // Log separate event for password change by admin (if not self-update)
            if ($passwordChanged && $currentUser->getId() !== $userId) {
                $this->securityAuditService->log(
                    $currentUser->getId(),
                    SecurityAuditService::EVENT_USER_PASSWORD_CHANGED_BY_ADMIN,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    [
                        'target_user_id' => $userId,
                        'target_email' => $requestUserData['email'],
                    ]
                );
            }
        }

        return Response::createOk();
    }

    /**
     * Generate a random password that meets policy requirements
     * This is used as a placeholder when sending welcome emails with invitation tokens
     */
    private function generateCompliantPassword() : string
    {
        // Character sets for password policy
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        // Ensure at least one character from each required set
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill remaining characters with random selection from all sets
        $allChars = $uppercase . $lowercase . $numbers . $special;
        $remainingLength = 60; // Total length will be 64 characters
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle to avoid predictable pattern (uppercase first, etc.)
        return str_shuffle($password);
    }
}
