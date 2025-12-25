<?php declare(strict_types=1);

namespace Movary\Service\Email;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;
use Movary\Service\ServerSettings;
use RuntimeException;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * OAuth Token Service for Gmail and Microsoft 365 email authentication
 *
 * Handles OAuth 2.0 authorization flow, token exchange, and refresh operations.
 * Integrates with league/oauth2-client providers for Gmail and Microsoft 365.
 *
 * @security CRITICAL - Handles OAuth authorization flow and token management
 */
class OAuthTokenService
{
    private const string SESSION_STATE_KEY = 'oauth_email_state';
    private const string SESSION_PROVIDER_KEY = 'oauth_email_provider';

    public function __construct(
        private readonly OAuthConfigService $configService,
        private readonly ServerSettings $serverSettings,
    ) {
    }

    /**
     * Generate OAuth authorization URL and redirect user to provider
     *
     * @param string $provider Provider: 'gmail' or 'microsoft'
     * @param string $redirectUri OAuth callback URL
     * @return string Authorization URL to redirect user to
     * @throws RuntimeException If OAuth config not found or invalid
     */
    public function getAuthorizationUrl(string $provider, string $redirectUri) : string
    {
        $config = $this->configService->getConfigByProvider($provider);
        if ($config === null) {
            throw new RuntimeException("OAuth configuration not found for provider: {$provider}");
        }

        $oauthProvider = $this->createProvider($config, $redirectUri);

        // Get authorization URL with required scopes
        $authUrl = $oauthProvider->getAuthorizationUrl([
            'scope' => $this->getRequiredScopes($provider),
        ]);

        // Store state in session for CSRF protection
        $state = $oauthProvider->getState();
        $this->storeStateInSession($state, $provider);

        return $authUrl;
    }

    /**
     * Handle OAuth callback and exchange authorization code for tokens
     *
     * @param string $code Authorization code from provider
     * @param string $state OAuth state parameter for CSRF validation
     * @param string $redirectUri OAuth callback URL (must match authorization request)
     * @throws RuntimeException If state validation fails or token exchange fails
     */
    public function handleCallback(string $code, string $state, string $redirectUri) : void
    {
        // Validate state (CSRF protection)
        $storedState = $this->getStateFromSession();
        if ($storedState === null || $state !== $storedState) {
            throw new RuntimeException('Invalid OAuth state parameter. Possible CSRF attack.');
        }

        $provider = $this->getProviderFromSession();
        if ($provider === null) {
            throw new RuntimeException('OAuth provider not found in session');
        }

        $config = $this->configService->getConfigByProvider($provider);
        if ($config === null) {
            throw new RuntimeException("OAuth configuration not found for provider: {$provider}");
        }

        $oauthProvider = $this->createProvider($config, $redirectUri);

        try {
            // Exchange authorization code for access token + refresh token
            $accessToken = $oauthProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Extract refresh token (required for long-term access)
            $refreshToken = $accessToken->getRefreshToken();
            if ($refreshToken === null) {
                throw new RuntimeException(
                    'No refresh token received. ' .
                    'For Gmail, ensure "Access Type" is set to "offline". ' .
                    'For Microsoft, ensure "offline_access" scope is requested.'
                );
            }

            // Get granted scopes
            $scopes = $this->extractScopes($accessToken);

            // Save refresh token to database (encrypted)
            $this->configService->saveTokens($refreshToken, $scopes);

            // Clear session data
            $this->clearSession();

        } catch (IdentityProviderException $e) {
            $this->configService->updateTokenStatus('error', $e->getMessage());
            throw new RuntimeException(
                'OAuth authorization failed: ' . $e->getMessage() . '. ' .
                'Please check your client ID, client secret, and redirect URI configuration.',
                0,
                $e
            );
        }
    }

    /**
     * Get a fresh access token (refresh if needed)
     *
     * @param string $redirectUri OAuth callback URL
     * @return AccessToken Fresh access token for SMTP authentication
     * @throws RuntimeException If refresh fails or configuration invalid
     */
    public function getAccessToken(string $redirectUri) : AccessToken
    {
        $config = $this->configService->getConfig();
        if ($config === null) {
            throw new RuntimeException('OAuth configuration not found');
        }

        if (!$config->isConnected()) {
            throw new RuntimeException('OAuth not connected. Please authorize first.');
        }

        try {
            $refreshToken = $this->configService->getDecryptedRefreshToken();
            $oauthProvider = $this->createProvider($config, $redirectUri);

            // Request new access token using refresh token with explicit scopes
            $scopes = $this->getRequiredScopes($config->provider);
            $accessToken = $oauthProvider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
                'scope' => $scopes,
            ]);

            // Debug logging disabled - OAuth is working correctly

            // Update last refresh timestamp
            $this->configService->updateLastTokenRefresh();
            $this->configService->updateTokenStatus('active');

            return $accessToken;

        } catch (IdentityProviderException $e) {
            $errorMessage = $e->getMessage();

            // Check for specific error conditions
            if (str_contains($errorMessage, 'invalid_grant') || str_contains($errorMessage, 'Token has been revoked')) {
                $this->configService->updateTokenStatus('expired', 'Refresh token expired or revoked. Please reconnect.');
                throw new RuntimeException(
                    'OAuth refresh token expired or revoked. ' .
                    'Please disconnect and reconnect your email account.',
                    0,
                    $e
                );
            }

            if (str_contains($errorMessage, 'invalid_client')) {
                $this->configService->updateTokenStatus('error', 'Invalid client credentials');
                throw new RuntimeException(
                    'OAuth client credentials invalid. ' .
                    'Please check your client ID and client secret.',
                    0,
                    $e
                );
            }

            // Generic error
            $this->configService->updateTokenStatus('error', $errorMessage);
            throw new RuntimeException('Failed to refresh OAuth token: ' . $errorMessage, 0, $e);
        }
    }

    /**
     * Create OAuth provider instance for Gmail or Microsoft 365
     *
     * @param OAuthConfig $config OAuth configuration
     * @param string $redirectUri OAuth callback URL
     * @return AbstractProvider Configured OAuth provider
     * @throws RuntimeException If provider is invalid or creation fails
     */
    private function createProvider(OAuthConfig $config, string $redirectUri) : AbstractProvider
    {
        $clientSecret = $this->configService->getDecryptedClientSecret();

        return match ($config->provider) {
            'gmail' => $this->createGmailProvider($config->clientId, $clientSecret, $redirectUri),
            'microsoft' => $this->createMicrosoftProvider($config->clientId, $clientSecret, $config->tenantId, $redirectUri),
            default => throw new RuntimeException("Unsupported provider: {$config->provider}"),
        };
    }

    /**
     * Create Gmail OAuth provider
     */
    private function createGmailProvider(string $clientId, string $clientSecret, string $redirectUri) : Google
    {
        return new Google([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'accessType' => 'offline', // Required for refresh token
            'prompt' => 'consent', // Force consent screen to ensure refresh token
        ]);
    }

    /**
     * Create Microsoft 365 OAuth provider
     */
    private function createMicrosoftProvider(
        string $clientId,
        string $clientSecret,
        ?string $tenantId,
        string $redirectUri
    ) : Azure {
        $tenant = $tenantId ?? 'common'; // Default to common (multi-tenant)

        return new Azure([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'tenant' => $tenant,
            // Use v2.0 endpoint for modern OAuth
            'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
            // Force tokens to be requested for Exchange Online resource
            'urlAPI' => 'https://outlook.office365.com/',
            'resource' => 'https://outlook.office365.com/',
            // Note: scopes are passed via getAuthorizationUrl() options, not constructor
        ]);
    }

    /**
     * Get required OAuth scopes for provider
     *
     * @param string $provider Provider name
     * @return string Space-separated scopes
     */
    private function getRequiredScopes(string $provider) : string
    {
        return match ($provider) {
            'gmail' => 'https://mail.google.com/', // Full Gmail access (includes SMTP)
            // Use .default to request all consented permissions for the application
            // This includes the Exchange Online SMTP.Send permission we configured
            'microsoft' => 'https://outlook.office365.com/.default offline_access',
            default => throw new RuntimeException("Unknown provider: {$provider}"),
        };
    }

    /**
     * Extract granted scopes from access token
     *
     * @param AccessToken $token Access token
     * @return string Space-separated scopes
     */
    private function extractScopes(AccessToken $token) : string
    {
        $values = $token->getValues();

        // Gmail uses 'scope' (space-separated)
        if (isset($values['scope'])) {
            return (string)$values['scope'];
        }

        // Microsoft uses 'scope' as well, but structure may vary
        if (isset($values['scope'])) {
            return is_array($values['scope'])
                ? implode(' ', $values['scope'])
                : (string)$values['scope'];
        }

        // Fallback to empty string
        return '';
    }

    /**
     * Store OAuth state in session for CSRF validation
     */
    private function storeStateInSession(string $state, string $provider) : void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::SESSION_STATE_KEY] = $state;
        $_SESSION[self::SESSION_PROVIDER_KEY] = $provider;
    }

    /**
     * Retrieve OAuth state from session
     */
    private function getStateFromSession() : ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[self::SESSION_STATE_KEY] ?? null;
    }

    /**
     * Retrieve provider from session
     */
    private function getProviderFromSession() : ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[self::SESSION_PROVIDER_KEY] ?? null;
    }

    /**
     * Clear OAuth session data after successful authorization
     */
    private function clearSession() : void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::SESSION_STATE_KEY]);
        unset($_SESSION[self::SESSION_PROVIDER_KEY]);
    }

    /**
     * Validate redirect URI matches APPLICATION_URL
     *
     * @param string $redirectUri Redirect URI to validate
     * @throws RuntimeException If redirect URI is invalid
     */
    public function validateRedirectUri(string $redirectUri) : void
    {
        $applicationUrl = $this->serverSettings->getApplicationUrl();
        if ($applicationUrl === null || trim($applicationUrl) === '') {
            throw new RuntimeException(
                'APPLICATION_URL not configured. ' .
                'This is required for OAuth redirect URI validation.'
            );
        }

        // Ensure redirect URI starts with APPLICATION_URL
        if (!str_starts_with($redirectUri, $applicationUrl)) {
            throw new RuntimeException(
                "Invalid redirect URI: {$redirectUri}. " .
                "Must start with APPLICATION_URL: {$applicationUrl}"
            );
        }

        // Warn if not HTTPS (required by most OAuth providers)
        if (!str_starts_with($redirectUri, 'https://') && !str_starts_with($redirectUri, 'http://localhost')) {
            throw new RuntimeException(
                'OAuth redirect URI must use HTTPS (except localhost). ' .
                "Current URI: {$redirectUri}. " .
                'Please configure APPLICATION_URL with https:// or set up SSL/TLS.'
            );
        }
    }

    /**
     * Build OAuth redirect URI from APPLICATION_URL
     *
     * @return string Complete redirect URI for OAuth callback
     * @throws RuntimeException If APPLICATION_URL not configured
     */
    public function buildRedirectUri() : string
    {
        $applicationUrl = $this->serverSettings->getApplicationUrl();
        if ($applicationUrl === null || trim($applicationUrl) === '') {
            throw new RuntimeException('APPLICATION_URL not configured');
        }

        // Remove trailing slash
        $baseUrl = rtrim($applicationUrl, '/');

        return $baseUrl . '/admin/server/email/oauth/callback';
    }
}
