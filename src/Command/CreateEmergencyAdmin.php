<?php declare(strict_types=1);

namespace Movary\Command;

use Movary\Domain\User\Exception\EmailNotUnique;
use Movary\Domain\User\Exception\PasswordPolicyViolation;
use Movary\Domain\User\Exception\UsernameInvalidFormat;
use Movary\Domain\User\Exception\UsernameNotUnique;
use Movary\Domain\User\Service\RecoveryCodeService;
use Movary\Domain\User\Service\TwoFactorAuthenticationApi;
use Movary\Domain\User\Service\TwoFactorAuthenticationFactory;
use Movary\Domain\User\UserApi;
use Movary\Domain\User\UserEntity;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'user:create-emergency-admin',
    description: 'Create an emergency admin user with mandatory 2FA (use only when all admins are locked out).',
)]
class CreateEmergencyAdmin extends Command
{
    public function __construct(
        private readonly UserApi $userApi,
        private readonly TwoFactorAuthenticationFactory $twoFactorAuthenticationFactory,
        private readonly TwoFactorAuthenticationApi $twoFactorAuthenticationApi,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $helper = $this->getHelper('question');
        \assert($helper instanceof QuestionHelper);

        $output->writeln('<error>======================================</error>');
        $output->writeln('<error>  EMERGENCY ADMIN ACCOUNT CREATION  </error>');
        $output->writeln('<error>======================================</error>');
        $output->writeln('');
        $output->writeln('<comment>This command creates an admin account with mandatory 2FA.</comment>');
        $output->writeln('<comment>Use only in emergency situations (lockout, death, fired employee, etc.).</comment>');
        $output->writeln('');

        // Check if admin users already exist and warn
        $adminCount = $this->userApi->countAdminUsers();
        if ($adminCount > 0) {
            $output->writeln('<comment>Warning: ' . $adminCount . ' admin user(s) already exist in the system.</comment>');
            $output->writeln('<comment>This command should only be used in true emergencies.</comment>');
            $output->writeln('');

            $confirmQuestion = new ConfirmationQuestion(
                '<question>Do you want to proceed with creating an emergency admin? (yes/no):</question> ',
                false
            );

            $confirmed = $helper->ask($input, $output, $confirmQuestion);
            if (!$confirmed) {
                $output->writeln('<info>Aborted. Use the web interface to create users normally.</info>');
                return Command::SUCCESS;
            }
            $output->writeln('');
        }

        $output->writeln('<info>Proceeding with emergency admin creation...</info>');
        $output->writeln('');

        // Collect user information
        ['email' => $email, 'name' => $name, 'password' => $password] =
            $this->promptForUserDetails($helper, $input, $output);

        $output->writeln('');

        // Create user
        try {
            $user = $this->userApi->createUser($email, $password, $name, true);
            $output->writeln('<info>✓ Admin user created successfully.</info>');
        } catch (EmailNotUnique) {
            $output->writeln('<error>Email already in use.</error>');
            return Command::FAILURE;
        } catch (PasswordPolicyViolation $e) {
            $output->writeln('<error>Password policy violation: ' . $e->getMessage() . '</error>');
            $output->writeln('<comment>Password must:</comment>');
            $output->writeln('  - Be at least 10 characters long');
            $output->writeln('  - Contain at least one uppercase letter');
            $output->writeln('  - Contain at least one lowercase letter');
            $output->writeln('  - Contain at least one number');
            $output->writeln('  - Contain at least one special character');
            return Command::FAILURE;
        } catch (UsernameInvalidFormat) {
            $output->writeln('<error>Name must only consist of numbers and letters.</error>');
            return Command::FAILURE;
        } catch (UsernameNotUnique) {
            $output->writeln('<error>Name already in use.</error>');
            return Command::FAILURE;
        } catch (\Throwable $t) {
            $this->logger->error('Could not create emergency admin.', ['exception' => $t]);
            $output->writeln('<error>Failed to create user. Please check logs.</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>=== TWO-FACTOR AUTHENTICATION SETUP ===</info>');
        $output->writeln('');

        // Generate TOTP secret
        $totp = $this->twoFactorAuthenticationFactory->createTotp($user->getName());
        $totpUri = $totp->getProvisioningUri();
        $secret = $totp->getSecret();

        // Display QR code as ASCII art
        $output->writeln('<info>Scan this QR code with your authenticator app:</info>');
        $output->writeln('(Google Authenticator, Authy, 1Password, etc.)');
        $output->writeln('');
        $this->displayAsciiQrCode($output, $totpUri);
        $output->writeln('');
        $output->writeln('<comment>Or enter this secret manually:</comment>');
        $output->writeln('<info>' . $secret . '</info>');
        $output->writeln('');

        // Verify TOTP code
        if ($this->verifyTotpSetup($helper, $input, $output, $user, $totpUri) === false) {
            return Command::FAILURE;
        }

        // Save TOTP
        $this->twoFactorAuthenticationApi->updateTotpUri($user->getId(), $totpUri);
        $output->writeln('');
        $output->writeln('<info>✓ Two-factor authentication enabled.</info>');
        $output->writeln('');

        // Generate and display recovery codes
        $output->writeln('<info>=== RECOVERY CODES ===</info>');
        $output->writeln('');
        $output->writeln('<comment>Save these recovery codes in a secure location.</comment>');
        $output->writeln('<comment>Each code can only be used once.</comment>');
        $output->writeln('<comment>You will not be able to see these codes again.</comment>');
        $output->writeln('');

        $recoveryCodes = $this->recoveryCodeService->generateRecoveryCodes($user->getId());

        foreach ($recoveryCodes as $index => $code) {
            $output->writeln(sprintf('  %2d. <info>%s</info>', $index + 1, $code));
        }

        $output->writeln('');

        // Confirm codes saved
        $confirmQuestion = new ConfirmationQuestion(
            '<question>Have you saved these recovery codes? (yes/no):</question> ',
            false
        );

        $confirmed = $helper->ask($input, $output, $confirmQuestion);
        if (!$confirmed) {
            $output->writeln('<error>You must save your recovery codes before proceeding.</error>');
            $output->writeln('<error>Deleting user and aborting.</error>');
            $this->userApi->deleteUser($user->getId());
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>======================================</info>');
        $output->writeln('<info>  SETUP COMPLETE!                   </info>');
        $output->writeln('<info>======================================</info>');
        $output->writeln('');
        $output->writeln('<info>Emergency admin account created:</info>');
        $output->writeln('  Email: <comment>' . $email . '</comment>');
        $output->writeln('  Name: <comment>' . $name . '</comment>');
        $output->writeln('  2FA: <info>Enabled</info>');
        $output->writeln('  Recovery codes: <info>Generated</info>');
        $output->writeln('');
        $output->writeln('<info>You can now log in via the web interface.</info>');

        return Command::SUCCESS;
    }

    /**
     * Prompt for and validate the emergency admin's email, name and password.
     *
     * @return array{email: string, name: string, password: string}
     */
    private function promptForUserDetails(QuestionHelper $helper, InputInterface $input, OutputInterface $output) : array
    {
        $emailQuestion = new Question('<question>Email address:</question> ');
        $emailQuestion->setValidator(function (?string $value) : string {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid email address.');
            }
            return $value;
        });
        $email = $helper->ask($input, $output, $emailQuestion);

        $nameQuestion = new Question('<question>Name:</question> ');
        $nameQuestion->setValidator(function (?string $value) : string {
            if (empty($value)) {
                throw new \RuntimeException('Name cannot be empty.');
            }
            return $value;
        });
        $name = $helper->ask($input, $output, $nameQuestion);

        $passwordQuestion = new Question('<question>Password (hidden):</question> ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setValidator(function (?string $value) : string {
            if (empty($value)) {
                throw new \RuntimeException('Password cannot be empty.');
            }
            return $value;
        });
        $password = $helper->ask($input, $output, $passwordQuestion);

        return ['email' => $email, 'name' => $name, 'password' => $password];
    }

    /**
     * Prompt for and verify the TOTP code (max 3 attempts).
     *
     * @return bool True when verified; false when aborted (user already deleted).
     */
    private function verifyTotpSetup(
        QuestionHelper $helper,
        InputInterface $input,
        OutputInterface $output,
        UserEntity $user,
        string $totpUri,
    ) : bool {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $codeQuestion = new Question('<question>Enter the 6-digit code from your authenticator app:</question> ');
            $codeQuestion->setValidator(function (?string $value) : int {
                $value = (string)$value;
                if (!ctype_digit($value) || strlen($value) !== 6) {
                    throw new \RuntimeException('Code must be exactly 6 digits.');
                }
                return (int)$value;
            });
            $code = $helper->ask($input, $output, $codeQuestion);

            if ($this->twoFactorAuthenticationApi->verifyTotpUri($user->getId(), $code, $totpUri)) {
                $output->writeln('<info>✓ Code verified successfully.</info>');
                return true;
            }

            if ($attempt < $maxAttempts) {
                $output->writeln('<error>✗ Invalid code. Please try again. (' . ($maxAttempts - $attempt) . ' attempts remaining)</error>');
            } else {
                $output->writeln('<error>✗ Too many failed attempts. Deleting user and aborting.</error>');
                $this->userApi->deleteUser($user->getId());
                return false;
            }
        }

        return false;
    }

    private function displayAsciiQrCode(OutputInterface $output, string $data) : void
    {
        // Simple ASCII QR representation
        // In a real implementation, you could use a library for actual QR generation
        // For now, we'll just display the URI
        $output->writeln('┌────────────────────────────────────────────┐');
        $output->writeln('│                                            │');
        $output->writeln('│   Scan with your authenticator app        │');
        $output->writeln('│   Or use the secret key shown below        │');
        $output->writeln('│                                            │');
        $output->writeln('│   URI for manual entry:                    │');
        $output->writeln('│   ' . substr($data, 0, 40) . '  │');
        if (strlen($data) > 40) {
            $output->writeln('│   ' . substr($data, 40) . str_repeat(' ', max(0, 40 - strlen(substr($data, 40)))) . '  │');
        }
        $output->writeln('│                                            │');
        $output->writeln('└────────────────────────────────────────────┘');
    }
}
