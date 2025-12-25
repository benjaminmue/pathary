<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Exception\EmailNotUnique;
use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Exception\PasswordTooShort;
use Movary\Domain\User\Exception\UsernameInvalidFormat;
use Movary\Domain\User\Exception\UsernameNotUnique;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\UserInvitationService;
use Movary\Domain\User\UserApi;
use Movary\Service\Email\CannotSendEmailException;
use Movary\Service\Email\EmailService;
use Movary\Util\Json;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Psr\Log\LoggerInterface;

class UserController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
        private readonly EmailService $emailService,
        private readonly UserInvitationService $invitationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createUser(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticatedWithCookie() === false
            && $this->authenticationService->getCurrentUser()->isAdmin() === false) {
            return Response::createForbidden();
        }

        $requestUserData = Json::decode($request->getBody());
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

        // Send welcome email with invitation token if requested
        if ($sendWelcomeEmail) {
            try {
                // Get the newly created user ID
                $user = $this->userApi->findUserByEmail($requestUserData['email']);
                if ($user === null) {
                    $this->logger->error('Failed to find newly created user by email', [
                        'email' => $requestUserData['email'],
                    ]);
                    return Response::createOk('User created but welcome email could not be sent: User not found');
                }

                // Generate invitation token (3 days expiration)
                $invitationToken = $this->invitationService->createInvitation($user->getId());

                // Send welcome email with token
                $this->emailService->sendWelcomeEmail(
                    $requestUserData['email'],
                    $requestUserData['name'],
                    $invitationToken,
                );
            } catch (CannotSendEmailException $e) {
                // Log the error but don't fail user creation
                $this->logger->warning('Failed to send welcome email to new user', [
                    'email' => $requestUserData['email'],
                    'error' => $e->getMessage(),
                ]);
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

        try {
            $this->userApi->updateName($userId, $requestUserData['name']);
            $this->userApi->updateEmail($userId, $requestUserData['email']);
            $this->userApi->updateIsAdmin($userId, $requestUserData['isAdmin']);

            if ($requestUserData['password'] !== null) {
                $this->userApi->updatePassword($userId, $requestUserData['password']);
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
