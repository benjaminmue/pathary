<?php declare(strict_types=1);

namespace Movary\Service\Email;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Movary\Service\ServerSettings;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class EmailService
{
    public function __construct(
        private PHPMailer $phpMailer,
        private ServerSettings $serverSettings,
        private OAuthConfigService $oauthConfigService,
        private OAuthTokenService $oauthTokenService,
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

        if ($smtpConfig->getHost() === '') {
            throw new CannotSendEmailException('SMTP host must be set.');
        }

        if ($smtpConfig->getPort() === 0) {
            throw new CannotSendEmailException('SMTP port must be set.');
        }

        $this->phpMailer->isSMTP();
        $this->phpMailer->Host = $smtpConfig->getHost();
        $this->phpMailer->Port = $smtpConfig->getPort();

        // Set From address with optional display name
        $displayName = $smtpConfig->getFromDisplayName();
        if ($displayName !== null && $displayName !== '') {
            $this->phpMailer->setFrom($smtpConfig->getFromAddress(), $displayName);
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

        // Check email auth mode and configure authentication
        $emailAuthMode = $this->serverSettings->getEmailAuthMode();

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
        $this->phpMailer->Body = $htmlMessage;

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
            $this->phpMailer->setFrom($fromAddress, $displayName);
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
}
