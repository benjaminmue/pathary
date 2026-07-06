<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Repository\RecoveryCodeRepository;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\Service\TwoFactorAuthenticationFactory;
use Movary\Domain\User\Service\Validator;
use Movary\Domain\User\UserApi;
use Movary\Service\SetupService;
use Movary\Util\Json;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use OTPHP\Factory;
use Twig\Environment;

class InitController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SetupService $setupService,
        private readonly UserApi $userApi,
        private readonly Validator $validator,
        private readonly TwoFactorAuthenticationApi $twoFactorAuthenticationApi,
        private readonly TwoFactorAuthenticationFactory $twoFactorAuthenticationFactory,
        private readonly RecoveryCodeRepository $recoveryCodeRepository,
        private readonly SecurityAuditService $securityAuditService,
    ) {
    }

    public function renderWizard(Request $request) : Response
    {
        // Middleware ensures we can only access this if setup is not completed

        // Clear any wizard session data to allow fresh start
        unset($_SESSION['init_user_email']);
        unset($_SESSION['init_user_name']);
        unset($_SESSION['init_user_password']);
        unset($_SESSION['init_pending_totp_uri']);
        unset($_SESSION['init_recovery_codes']);

        $step = $request->getGetParameters()['step'] ?? 'welcome';

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('init/wizard.twig', [
                'currentStep' => $step,
                'error' => $request->getGetParameters()['error'] ?? null,
            ]),
        );
    }

    public function createAdmin(Request $request) : Response
    {
        // Middleware ensures setup is not completed

        $data = Json::decode($request->getBody());
        $step = $data['step'] ?? '';

        return match ($step) {
            'create_account' => $this->handleCreateAccount($data),
            'setup_totp' => $this->handleSetupTotp(),
            'verify_totp' => $this->handleVerifyTotp($data),
            'acknowledge_codes' => $this->handleAcknowledgeCodes(),
            default => Response::createJson(
                Json::encode(['error' => 'Invalid step']),
                StatusCode::createBadRequest()
            ),
        };
    }

    private function handleCreateAccount(array $data) : Response
    {
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';
        $password = $data['password'] ?? '';
        $passwordConfirmation = $data['passwordConfirmation'] ?? '';

        // Validate inputs
        if (empty($email) || empty($name) || empty($password)) {
            return Response::createJson(
                Json::encode(['error' => 'All fields are required.']),
                StatusCode::createBadRequest()
            );
        }

        if ($password !== $passwordConfirmation) {
            return Response::createJson(
                Json::encode(['error' => 'Passwords do not match.']),
                StatusCode::createBadRequest()
            );
        }

        // Validate password policy (without creating user yet)
        try {
            $this->validator->ensurePasswordIsValid($password);
        } catch (PasswordPolicyViolation $e) {
            return Response::createJson(
                Json::encode(['error' => $e->getMessage()]),
                StatusCode::createBadRequest()
            );
        }

        // Store user data in session (don't create user in DB yet)
        $_SESSION['init_user_email'] = $email;
        $_SESSION['init_user_name'] = $name;
        $_SESSION['init_user_password'] = $password;

        return Response::createJson(
            Json::encode(['success' => true, 'nextStep' => 'setup_totp'])
        );
    }

    private function handleSetupTotp() : Response
    {
        $userName = $_SESSION['init_user_name'] ?? null;

        if ($userName === null) {
            return Response::createJson(
                Json::encode(['error' => 'Session expired. Please start over.']),
                StatusCode::createBadRequest()
            );
        }

        // Generate TOTP secret using the name from session
        $totp = $this->twoFactorAuthenticationFactory->createTotp($userName);
        $totpUri = $totp->getProvisioningUri();

        // Store temporarily in session for verification
        $_SESSION['init_pending_totp_uri'] = $totpUri;

        return Response::createJson(
            Json::encode([
                'totpUri' => $totpUri,
                'secret' => $totp->getSecret(),
                'nextStep' => 'verify_totp',
            ])
        );
    }

    private function handleVerifyTotp(array $data) : Response
    {
        $pendingTotpUri = $_SESSION['init_pending_totp_uri'] ?? null;

        if ($pendingTotpUri === null) {
            return Response::createJson(
                Json::encode(['error' => 'Session expired. Please start over.']),
                StatusCode::createBadRequest()
            );
        }

        $verificationCode = (string)($data['code'] ?? '');

        if ($verificationCode === '') {
            return Response::createJson(
                Json::encode(['error' => 'Invalid verification code. Please try again.']),
                StatusCode::createBadRequest()
            );
        }

        // Verify the TOTP code using OTPHP Factory
        try {
            $totp = Factory::loadFromProvisioningUri($pendingTotpUri);
            if ($totp->verify($verificationCode) === false) {
                return Response::createJson(
                    Json::encode(['error' => 'Invalid verification code. Please try again.']),
                    StatusCode::createBadRequest()
                );
            }
        } catch (\Exception $e) {
            return Response::createJson(
                Json::encode(['error' => 'Invalid TOTP configuration. Please start over.']),
                StatusCode::createBadRequest()
            );
        }

        // Store temporary recovery codes for display (will be hashed and stored in final step)
        $recoveryCodes = $this->generateTemporaryRecoveryCodes();
        $_SESSION['init_recovery_codes'] = $recoveryCodes;

        return Response::createJson(
            Json::encode([
                'success' => true,
                'recoveryCodes' => $recoveryCodes,
                'nextStep' => 'acknowledge_codes',
            ])
        );
    }

    private function generateTemporaryRecoveryCodes() : array
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing characters
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $code = '';
            for ($j = 0; $j < 10; $j++) {
                if ($j > 0 && $j % 4 === 0) {
                    $code .= '-';
                }
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $codes[] = $code;
        }

        return $codes;
    }

    private function handleAcknowledgeCodes() : Response
    {
        $email = $_SESSION['init_user_email'] ?? null;
        $name = $_SESSION['init_user_name'] ?? null;
        $password = $_SESSION['init_user_password'] ?? null;
        $totpUri = $_SESSION['init_pending_totp_uri'] ?? null;
        $recoveryCodes = $_SESSION['init_recovery_codes'] ?? null;

        if ($email === null || $name === null || $password === null || $totpUri === null || $recoveryCodes === null) {
            return Response::createJson(
                Json::encode(['error' => 'Session expired. Please start over.']),
                StatusCode::createBadRequest()
            );
        }

        try {
            // NOW create the user in the database with all data
            $user = $this->userApi->createUser(
                $email,
                $password,
                $name,
                true  // isAdmin
            );
            $userId = $user->getId();

            // Save TOTP URI
            $this->twoFactorAuthenticationApi->updateTotpUri($userId, $totpUri);

            // Save the recovery codes that were shown to the user (hash and store them)
            foreach ($recoveryCodes as $code) {
                // Normalize code (remove dashes, uppercase) before hashing
                $normalizedCode = str_replace(['-', ' '], '', strtoupper(trim($code)));
                $codeHash = password_hash($normalizedCode, PASSWORD_DEFAULT);
                $this->recoveryCodeRepository->create($userId, $codeHash);
            }

            // Log security events
            $this->logSetupCompletion($userId);

            // Mark setup as completed
            $this->setupService->markSetupCompleted();

            // Clean up session
            unset($_SESSION['init_user_email']);
            unset($_SESSION['init_user_name']);
            unset($_SESSION['init_user_password']);
            unset($_SESSION['init_pending_totp_uri']);
            unset($_SESSION['init_recovery_codes']);

            return Response::createJson(
                Json::encode([
                    'success' => true,
                    'redirectUrl' => '/login',
                ])
            );
        } catch (\Exception $e) {
            // Log the actual error for debugging
            error_log('Init wizard error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            return Response::createJson(
                Json::encode(['error' => 'Failed to complete setup: ' . $e->getMessage()]),
                StatusCode::createBadRequest()
            );
        }
    }

    private function logSetupCompletion(int $userId) : void
    {
        $this->securityAuditService->log(
            $userId,
            SecurityAuditService::EVENT_TOTP_ENABLED,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );
        $this->securityAuditService->log(
            $userId,
            'SETUP_COMPLETED',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        );
    }
}
