<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Exception\PasswordTooShort;
use Movary\Domain\User\Service\UserInvitationService;
use Movary\Domain\User\UserApi;
use Movary\Service\ApplicationUrlService;
use Movary\Service\PasswordSetupRateLimiterService;
use Movary\Util\Json;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Movary\ValueObject\RelativeUrl;
use Twig\Environment;

class SetPasswordController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UserInvitationService $invitationService,
        private readonly UserApi $userApi,
        private readonly SessionWrapper $sessionWrapper,
        private readonly ApplicationUrlService $applicationUrlService,
        private readonly PasswordSetupRateLimiterService $passwordSetupRateLimiter,
    ) {
    }

    public function renderPage(Request $request) : Response
    {
        $token = $request->getGetParameters()['token'] ?? null;

        if ($token === null) {
            return Response::create(
                StatusCode::createBadRequest(),
                $this->twig->render('page/error.html.twig', [
                    'errorMessage' => 'Invalid invitation link.',
                ]),
            );
        }

        // Validate token
        $userId = $this->invitationService->validateToken($token);

        if ($userId === null) {
            return Response::create(
                StatusCode::createBadRequest(),
                $this->twig->render('page/error.html.twig', [
                    'errorMessage' => 'This invitation link is invalid or has expired. Please contact your administrator.',
                ]),
            );
        }

        // Get user details
        $user = $this->userApi->fetchUser($userId);

        // Get error messages from session
        $errorPasswordTooShort = $this->sessionWrapper->find('errorPasswordTooShort');
        $errorPasswordPolicyViolation = $this->sessionWrapper->find('errorPasswordPolicyViolation');
        $errorPasswordNotEqual = $this->sessionWrapper->find('errorPasswordNotEqual');
        $missingFormData = $this->sessionWrapper->find('missingFormData');
        $rateLimitExceeded = $this->sessionWrapper->find('rateLimitExceeded');

        $this->sessionWrapper->unset(
            'errorPasswordTooShort',
            'errorPasswordPolicyViolation',
            'errorPasswordNotEqual',
            'missingFormData',
            'rateLimitExceeded',
        );

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('page/setup-password.html.twig', [
                'userName' => $user->getName(),
                'token' => $token,
                'errorPasswordTooShort' => $errorPasswordTooShort,
                'errorPasswordPolicyViolation' => $errorPasswordPolicyViolation,
                'errorPasswordNotEqual' => $errorPasswordNotEqual,
                'missingFormData' => $missingFormData,
                'rateLimitExceeded' => $rateLimitExceeded,
            ]),
        );
    }

    public function setPassword(Request $request) : Response
    {
        $postParameters = $request->getPostParameters();
        $token = $postParameters['token'] ?? null;
        $password = $postParameters['password'] ?? null;
        $repeatPassword = $postParameters['repeatPassword'] ?? null;

        if ($token === null || $password === null || $repeatPassword === null) {
            $this->sessionWrapper->set('missingFormData', true);
            return $this->redirectToSetupPage($token);
        }

        // Check rate limit BEFORE validating password
        // This prevents attackers from learning about password policy through timing attacks
        try {
            $this->passwordSetupRateLimiter->checkRateLimit($token);
        } catch (\RuntimeException $e) {
            // Rate limit exceeded
            $this->sessionWrapper->set('rateLimitExceeded', true);
            return $this->redirectToSetupPage($token);
        }

        if ($password !== $repeatPassword) {
            $this->sessionWrapper->set('errorPasswordNotEqual', true);
            // Log failed attempt
            $this->passwordSetupRateLimiter->logAttempt($token, false, $_SERVER['REMOTE_ADDR'] ?? null);
            return $this->redirectToSetupPage($token);
        }

        // Validate token
        $userId = $this->invitationService->validateToken($token);

        if ($userId === null) {
            return Response::create(
                StatusCode::createBadRequest(),
                $this->twig->render('page/error.html.twig', [
                    'errorMessage' => 'This invitation link is invalid or has expired.',
                ]),
            );
        }

        try {
            // Update user password
            $this->userApi->updatePassword($userId, $password);

            // Mark invitation token as used
            $this->invitationService->markTokenAsUsed($token);

            // Log successful password setup
            $this->passwordSetupRateLimiter->logAttempt($token, true, $_SERVER['REMOTE_ADDR'] ?? null);

            // Set success flag for 2FA recommendation modal
            $this->sessionWrapper->set('passwordSetSuccess', true);

            // Redirect to login page
            $redirectUrl = $this->applicationUrlService->createApplicationUrl(RelativeUrl::create('/login'));

            return Response::create(
                StatusCode::createSeeOther(),
                null,
                [Header::createLocation($redirectUrl)],
            );
        } catch (PasswordTooShort) {
            $this->sessionWrapper->set('errorPasswordTooShort', true);
            // Log failed attempt
            $this->passwordSetupRateLimiter->logAttempt($token, false, $_SERVER['REMOTE_ADDR'] ?? null);
            return $this->redirectToSetupPage($token);
        } catch (PasswordPolicyViolation $e) {
            $this->sessionWrapper->set('errorPasswordPolicyViolation', $e->getMessage());
            // Log failed attempt
            $this->passwordSetupRateLimiter->logAttempt($token, false, $_SERVER['REMOTE_ADDR'] ?? null);
            return $this->redirectToSetupPage($token);
        }
    }

    private function redirectToSetupPage(?string $token) : Response
    {
        $url = $token !== null
            ? RelativeUrl::create('/setup-password?token=' . urlencode($token))
            : RelativeUrl::create('/');

        $redirectUrl = $this->applicationUrlService->createApplicationUrl($url);

        return Response::create(
            StatusCode::createSeeOther(),
            null,
            [Header::createLocation($redirectUrl)],
        );
    }
}
