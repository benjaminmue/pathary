<?php declare(strict_types=1);

namespace Movary\Service\Email;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Movary\Service\ApplicationUrlService;
use Movary\Service\ServerSettings;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private PHPMailer $phpMailer,
        private ServerSettings $serverSettings,
        private OAuthConfigService $oauthConfigService,
        private OAuthTokenService $oauthTokenService,
        private Environment $twig,
        private ApplicationUrlService $applicationUrlService,
        private EmailRateLimiterService $emailRateLimiter,
    ) {
    }

    public function sendEmail(string $targetEmailAddress, string $subject, string $htmlMessage, SmtpConfig $smtpConfig) : void
    {
        // Clear PHPMailer state from any previous sends (important for singleton)
        $this->phpMailer->clearAllRecipients();
        $this->phpMailer->clearReplyTos();
        $this->phpMailer->clearAttachments();
        $this->phpMailer->clearCustomHeaders();

        // Disable debug output (OAuth is working correctly)
        $this->phpMailer->SMTPDebug = SMTP::DEBUG_OFF;
        $this->phpMailer->Debugoutput = 'error_log';

        // Check email auth mode for better error messages
        $emailAuthMode = $this->serverSettings->getEmailAuthMode();
        $isOAuthMode = ($emailAuthMode === 'smtp_oauth');

        if ($smtpConfig->getHost() === '') {
            $errorMessage = $isOAuthMode
                ? 'SMTP host must be configured (required even when using OAuth authentication). Go to Admin → Server Management → Email Settings to configure.'
                : 'SMTP host must be set.';
            throw new CannotSendEmailException($errorMessage);
        }

        if ($smtpConfig->getPort() === 0) {
            $errorMessage = $isOAuthMode
                ? 'SMTP port must be configured (required even when using OAuth authentication). Go to Admin → Server Management → Email Settings to configure.'
                : 'SMTP port must be set.';
            throw new CannotSendEmailException($errorMessage);
        }

        $this->phpMailer->isSMTP();
        $this->phpMailer->Host = $smtpConfig->getHost();
        $this->phpMailer->Port = $smtpConfig->getPort();

        // Set From address with optional display name
        $displayName = $smtpConfig->getFromDisplayName();
        if ($displayName !== null && $displayName !== '') {
            // Defensive sanitization: remove CR/LF characters to prevent header injection
            $sanitizedDisplayName = str_replace(["\r", "\n"], '', $displayName);
            $this->phpMailer->setFrom($smtpConfig->getFromAddress(), $sanitizedDisplayName);
        } else {
            $this->phpMailer->setFrom($smtpConfig->getFromAddress());
        }

        // Map encryption value to PHPMailer constants
        $encryption = $smtpConfig->getEncryption();
        if ($encryption === 'tls') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            // No encryption - explicitly set to false
            $this->phpMailer->SMTPSecure = false;
        }

        // Configure authentication based on email auth mode
        if ($emailAuthMode === 'smtp_oauth') {
            // OAuth 2.0 authentication
            $this->configureOAuthAuthentication();
        } else {
            // Traditional password authentication
            $this->phpMailer->SMTPAuth = $smtpConfig->isWithAuthentication();
            $this->phpMailer->Username = (string)$smtpConfig->getUser();
            $this->phpMailer->Password = (string)$smtpConfig->getPassword();
        }

        $this->phpMailer->addAddress($targetEmailAddress);
        $this->phpMailer->Subject = $subject;

        // Configure for HTML email
        $this->phpMailer->isHTML(true);
        $this->phpMailer->Body = $htmlMessage;
        $this->phpMailer->CharSet = 'UTF-8';

        if ($this->phpMailer->send() === false || $this->phpMailer->isError() === true) {
            // Provide more detailed error information
            $errorInfo = $this->phpMailer->ErrorInfo;

            // Add context to common errors
            if (str_contains($errorInfo, 'SMTP connect() failed')) {
                throw new CannotSendEmailException(
                    'SMTP connect() failed. Please verify: ' .
                    '1) SMTP host and port are correct, ' .
                    '2) Encryption setting matches port (587=TLS, 465=SSL), ' .
                    '3) Firewall allows outbound connection. ' .
                    'Error: ' . $errorInfo
                );
            }

            // Microsoft 365 specific error: SMTP AUTH disabled
            if (str_contains($errorInfo, 'SmtpClientAuthentication is disabled')) {
                throw new CannotSendEmailException(
                    'Microsoft 365 SMTP authentication is disabled for your tenant or account. ' .
                    'Your M365 administrator must enable SMTP AUTH. ' .
                    'See: https://aka.ms/smtp_auth_disabled ' .
                    'Error: ' . $errorInfo
                );
            }

            if (str_contains($errorInfo, 'Authentication failed') || str_contains($errorInfo, '535')) {
                throw new CannotSendEmailException(
                    'SMTP authentication failed. Please verify username and password are correct. ' .
                    'For M365, use your full email as username and an app password if MFA is enabled. ' .
                    'Error: ' . $errorInfo
                );
            }

            throw new CannotSendEmailException($errorInfo);
        }
    }

    /**
     * Configure PHPMailer for OAuth 2.0 authentication
     *
     * @throws CannotSendEmailException If OAuth configuration is invalid or token refresh fails
     */
    private function configureOAuthAuthentication() : void
    {
        // Load OAuth configuration
        $oauthConfig = $this->oauthConfigService->getConfig();
        if ($oauthConfig === null) {
            throw new CannotSendEmailException(
                'OAuth email authentication is enabled but not configured. ' .
                'Please configure OAuth in Admin → Server Management → Email Settings.'
            );
        }

        if (!$oauthConfig->isConnected()) {
            throw new CannotSendEmailException(
                'OAuth email authentication is not connected. ' .
                'Please authorize your email account in Admin → Server Management → Email Settings.'
            );
        }

        // Override SMTP settings with OAuth provider defaults
        $this->phpMailer->Host = $oauthConfig->getSmtpHost();
        $this->phpMailer->Port = $oauthConfig->getSmtpPort();

        // Use custom From address if set in server settings, otherwise use OAuth auth mailbox
        $customFromAddress = $this->serverSettings->getFromAddress();
        $fromAddress = (!empty($customFromAddress) && trim($customFromAddress) !== '')
            ? $customFromAddress
            : $oauthConfig->fromAddress;

        // Set From address with optional display name
        $displayName = $this->serverSettings->getFromDisplayName();
        if ($displayName !== null && $displayName !== '') {
            // Defensive sanitization: remove CR/LF characters to prevent header injection
            $sanitizedDisplayName = str_replace(["\r", "\n"], '', $displayName);
            $this->phpMailer->setFrom($fromAddress, $sanitizedDisplayName);
        } else {
            $this->phpMailer->setFrom($fromAddress);
        }

        // Set encryption based on provider
        $encryption = $oauthConfig->getSmtpEncryption();
        if ($encryption === 'tls') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // Get fresh access token
        try {
            $redirectUri = $this->oauthTokenService->buildRedirectUri();
            $accessToken = $this->oauthTokenService->getAccessToken($redirectUri);
        } catch (\RuntimeException $e) {
            throw new CannotSendEmailException(
                'Failed to refresh OAuth access token: ' . $e->getMessage() . '. ' .
                'Please reconnect your email account in Admin → Server Management → Email Settings.',
                0,
                $e
            );
        }

        // Configure PHPMailer for XOAUTH2
        $this->phpMailer->AuthType = 'XOAUTH2';
        $this->phpMailer->SMTPAuth = true;

        // Create OAuth provider for PHPMailer
        $oauthProvider = new OAuth([
            'provider' => $this->createPhpMailerOAuthProvider($oauthConfig, $redirectUri),
            'userName' => $oauthConfig->fromAddress,
            'clientSecret' => $this->oauthConfigService->getDecryptedClientSecret(),
            'clientId' => $oauthConfig->clientId,
            'refreshToken' => $this->oauthConfigService->getDecryptedRefreshToken(),
        ]);

        $this->phpMailer->setOAuth($oauthProvider);
    }

    /**
     * Create OAuth provider for PHPMailer based on configuration
     *
     * @param OAuthConfig $config OAuth configuration
     * @param string $redirectUri OAuth callback URL
     * @return \League\OAuth2\Client\Provider\AbstractProvider
     */
    private function createPhpMailerOAuthProvider(OAuthConfig $config, string $redirectUri) : \League\OAuth2\Client\Provider\AbstractProvider
    {
        $clientSecret = $this->oauthConfigService->getDecryptedClientSecret();

        if ($config->isGmail()) {
            return new \League\OAuth2\Client\Provider\Google([
                'clientId' => $config->clientId,
                'clientSecret' => $clientSecret,
                'redirectUri' => $redirectUri,
            ]);
        }

        if ($config->isMicrosoft()) {
            return new \TheNetworg\OAuth2\Client\Provider\Azure([
                'clientId' => $config->clientId,
                'clientSecret' => $clientSecret,
                'redirectUri' => $redirectUri,
                'tenant' => $config->tenantId ?? 'common',
                // Use v2.0 endpoint for modern OAuth
                'defaultEndPointVersion' => \TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0,
                // Force tokens to be requested for Exchange Online resource
                'urlAPI' => 'https://outlook.office365.com/',
                'resource' => 'https://outlook.office365.com/',
            ]);
        }

        throw new CannotSendEmailException("Unsupported OAuth provider: {$config->provider}");
    }

    /**
     * Send welcome email to a newly created user
     *
     * @param string $recipientEmail Email address of the recipient
     * @param string $recipientName Display name of the recipient
     * @param string|null $invitationToken Optional invitation token for password setup
     * @throws CannotSendEmailException
     */
    public function sendWelcomeEmail(string $recipientEmail, string $recipientName, ?string $invitationToken = null, ?int $senderUserId = null) : void
    {
        // Check rate limits before attempting to send
        $this->emailRateLimiter->checkRateLimit($recipientEmail, $senderUserId);

        try {
            // Get application settings
            $applicationName = $this->serverSettings->getApplicationName() ?? 'Pathary';
            $applicationUrl = (string)$this->applicationUrlService->createApplicationUrl();

            // Build logo URL (prefer PNG for email compatibility)
            $logoUrl = $applicationUrl . '/images/pathary-logo-192x192.png';

            // Build password setup URL if invitation token provided
            $passwordSetupUrl = null;
            if ($invitationToken !== null) {
                $passwordSetupUrl = $applicationUrl . '/setup-password?token=' . urlencode($invitationToken);
            }

            // Render email template
            $htmlMessage = $this->twig->render('email/welcome.html.twig', [
                'recipient_name' => $recipientName,
                'application_name' => $applicationName,
                'application_url' => $applicationUrl,
                'logo_url' => $logoUrl,
                'password_setup_url' => $passwordSetupUrl,
                'has_invitation' => $invitationToken !== null,
            ]);

            // Build SMTP config from current settings
            $smtpConfig = SmtpConfig::create(
                $this->serverSettings->getSmtpHost() ?? '',
                $this->serverSettings->getSmtpPort() ?? 587,
                $this->serverSettings->getFromAddress() ?? '',
                $this->serverSettings->getSmtpEncryption() ?? 'tls',
                $this->serverSettings->getSmtpWithAuthentication() ?? true,
                $this->serverSettings->getSmtpUser(),
                $this->serverSettings->getSmtpPassword(),
                $this->serverSettings->getFromDisplayName(),
            );

            // Send email
            $subject = "Welcome to {$applicationName}";
            $this->sendEmail($recipientEmail, $subject, $htmlMessage, $smtpConfig);

            // Log successful email send
            $this->emailRateLimiter->logEmailSend($recipientEmail, 'welcome', $senderUserId, true);
        } catch (CannotSendEmailException $e) {
            // Log failed email send
            $this->emailRateLimiter->logEmailSend($recipientEmail, 'welcome', $senderUserId, false, $e->getMessage());
            throw $e;
        }
    }
}
