<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Service\CsrfTokenService;
use Movary\Service\Email\OAuthConfigService;
use Movary\Service\Email\OAuthTokenService;
use Movary\Service\Email\SmtpConfig;
use Movary\Service\Email\EmailService;
use Movary\Service\EncryptionService;
use Movary\Service\ServerSettings;
use Movary\Util\Json;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use RuntimeException;

/**
 * Controller for OAuth email authentication endpoints
 *
 * Handles OAuth configuration, authorization flow, callback, and testing.
 * Admin-only endpoints for managing OAuth-based SMTP authentication.
 */
class OAuthEmailController
{
    public function __construct(
        private readonly OAuthConfigService $oauthConfigService,
        private readonly OAuthTokenService $oauthTokenService,
        private readonly EncryptionService $encryptionService,
        private readonly ServerSettings $serverSettings,
        private readonly CsrfTokenService $csrfTokenService,
        private readonly EmailService $emailService,
        private readonly Authentication $authenticationService,
        private readonly SecurityAuditService $securityAuditService,
    ) {
    }

    /**
     * Save OAuth configuration (provider, client ID, client secret, etc.)
     *
     * POST /admin/server/email/oauth/save
     */
    public function saveConfig(Request $request) : Response
    {
        $requestData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
                [Header::createContentTypeJson()],
            );
        }

        // Validate encryption key is configured
        if (!$this->encryptionService->isEncryptionKeyConfigured()) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode([
                    'error' => 'Encryption key not configured. Please set ENCRYPTION_KEY environment variable or generate a key.',
                    'action' => 'generate_key',
                ]),
                [Header::createContentTypeJson()],
            );
        }

        // Extract and validate parameters
        $provider = trim((string)($requestData['provider'] ?? ''));
        $clientId = trim((string)($requestData['clientId'] ?? ''));
        $clientSecret = trim((string)($requestData['clientSecret'] ?? ''));
        $fromAddress = trim((string)($requestData['fromAddress'] ?? ''));
        $tenantId = isset($requestData['tenantId']) ? trim((string)$requestData['tenantId']) : null;
        $secretExpiresInMonths = isset($requestData['secretExpiresInMonths']) ? (int)$requestData['secretExpiresInMonths'] : null;

        // Validation
        if (!in_array($provider, ['gmail', 'microsoft'], true)) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid provider. Must be "gmail" or "microsoft".']),
                [Header::createContentTypeJson()],
            );
        }

        if ($clientId === '' || $clientSecret === '' || $fromAddress === '') {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Client ID, client secret, and from address are required.']),
                [Header::createContentTypeJson()],
            );
        }

        if ($provider === 'microsoft' && ($tenantId === null || trim($tenantId) === '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Tenant ID is required for Microsoft 365.']),
                [Header::createContentTypeJson()],
            );
        }

        try {
            // Check if config already exists (to determine create vs update)
            $existingConfig = $this->oauthConfigService->getConfig();
            $isUpdate = $existingConfig !== null;

            // Save configuration (encrypts client secret automatically)
            $this->oauthConfigService->saveConfig(
                $provider,
                $clientId,
                $clientSecret,
                $fromAddress,
                $tenantId,
                $secretExpiresInMonths,
            );

            // Log audit event
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                $isUpdate ? SecurityAuditService::EVENT_OAUTH_CONFIG_UPDATED : SecurityAuditService::EVENT_OAUTH_CONFIG_CREATED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'provider' => $provider,
                    'client_id' => $clientId,
                    'from_address' => $fromAddress,
                    'tenant_id' => $tenantId,
                    'has_secret_expiry' => $secretExpiresInMonths !== null,
                ]
            );

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'OAuth configuration saved successfully. Click "Connect" to authorize.',
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (RuntimeException $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to save configuration: ' . $e->getMessage()]),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * Start OAuth authorization flow
     *
     * GET /admin/server/email/oauth/authorize
     */
    public function authorize(Request $request) : Response
    {
        try {
            $config = $this->oauthConfigService->getConfig();
            if ($config === null) {
                return Response::create(
                    StatusCode::createBadRequest(),
                    'OAuth configuration not found. Please configure OAuth settings first.',
                );
            }

            // Build and validate redirect URI
            $redirectUri = $this->oauthTokenService->buildRedirectUri();
            $this->oauthTokenService->validateRedirectUri($redirectUri);

            // Get authorization URL from provider
            $authUrl = $this->oauthTokenService->getAuthorizationUrl($config->provider, $redirectUri);

            // Redirect to provider's consent screen
            return Response::createSeeOther($authUrl);

        } catch (RuntimeException $e) {
            return Response::create(
                StatusCode::createBadRequest(),
                'Failed to start authorization: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Handle OAuth callback from provider
     *
     * GET /admin/server/email/oauth/callback
     */
    public function callback(Request $request) : Response
    {
        $code = $request->getGetParameters()['code'] ?? null;
        $state = $request->getGetParameters()['state'] ?? null;
        $error = $request->getGetParameters()['error'] ?? null;

        // Check for authorization errors
        if ($error !== null) {
            $errorDescription = htmlspecialchars(
                $request->getGetParameters()['error_description'] ?? 'Unknown error',
                ENT_QUOTES,
                'UTF-8'
            );

            // Log failed callback attempt
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_CALLBACK_FAILED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'error' => $error,
                    'error_description' => $errorDescription,
                ]
            );

            return Response::create(
                StatusCode::createBadRequest(),
                'OAuth authorization failed: ' . $error . ' - ' . $errorDescription,
            );
        }

        if ($code === null || $state === null) {
            return Response::create(
                StatusCode::createBadRequest(),
                'Invalid OAuth callback: missing code or state parameter',
            );
        }

        try {
            // Build redirect URI (must match authorization request)
            $redirectUri = $this->oauthTokenService->buildRedirectUri();

            // Handle callback and save tokens
            $this->oauthTokenService->handleCallback($code, $state, $redirectUri);

            // Get config to log connection details
            $config = $this->oauthConfigService->getConfig();

            // Log successful OAuth connection
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_CONNECTED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'provider' => $config?->provider,
                    'from_address' => $config?->fromAddress,
                    'granted_scopes' => $config?->scopes,
                ]
            );

            // Redirect to admin panel with success message
            return Response::createSeeOther('/admin/server?oauth_success=1');

        } catch (RuntimeException $e) {
            // Log failed callback attempt
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_CALLBACK_FAILED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'error' => 'exception',
                    'error_description' => $e->getMessage(),
                ]
            );

            return Response::create(
                StatusCode::createBadRequest(),
                'Failed to complete OAuth authorization: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Disconnect OAuth (clear tokens)
     *
     * POST /admin/server/email/oauth/disconnect
     */
    public function disconnect(Request $request) : Response
    {
        $requestData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
                [Header::createContentTypeJson()],
            );
        }

        try {
            // Get config before disconnecting (to log provider details)
            $config = $this->oauthConfigService->getConfig();

            $this->oauthConfigService->disconnect();

            // Log disconnect event
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_DISCONNECTED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'provider' => $config?->provider,
                    'from_address' => $config?->fromAddress,
                ]
            );

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'OAuth disconnected successfully. Provider configuration preserved.',
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (RuntimeException $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to disconnect: ' . $e->getMessage()]),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * Delete OAuth configuration completely
     *
     * POST /admin/server/email/oauth/delete
     */
    public function deleteConfig(Request $request) : Response
    {
        $requestData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
                [Header::createContentTypeJson()],
            );
        }

        try {
            // Get config before deleting (to log provider details)
            $config = $this->oauthConfigService->getConfig();

            $this->oauthConfigService->deleteConfig();

            // Log delete event
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_CONFIG_DELETED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'provider' => $config?->provider,
                    'client_id' => $config?->clientId,
                    'from_address' => $config?->fromAddress,
                ]
            );

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'OAuth configuration deleted successfully.',
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (RuntimeException $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to delete configuration: ' . $e->getMessage()]),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * Generate encryption key for development
     *
     * POST /admin/server/email/oauth/generate-key
     */
    public function generateEncryptionKey(Request $request) : Response
    {
        $requestData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
                [Header::createContentTypeJson()],
            );
        }

        try {
            // Check if key already exists in environment
            if ($this->encryptionService->getEncryptionKeySource() === 'environment') {
                return Response::create(
                    StatusCode::createBadRequest(),
                    Json::encode([
                        'error' => 'Encryption key already set in environment. Cannot generate new key.',
                        'warning' => 'Generating a new key would invalidate existing encrypted data.',
                    ]),
                    [Header::createContentTypeJson()],
                );
            }

            $keyBase64 = $this->encryptionService->generateAndStoreKey();

            // Log key generation event (NOT the key value itself)
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_ENCRYPTION_KEY_GENERATED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'key_source' => 'database',
                    'key_length' => strlen($keyBase64),
                ]
            );

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'Encryption key generated and stored in database.',
                    'key' => $keyBase64,
                    'warning' => 'For production, set ENCRYPTION_KEY environment variable instead of using database storage.',
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (RuntimeException $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to generate key: ' . $e->getMessage()]),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * Get OAuth configuration status
     *
     * GET /admin/server/email/oauth/status
     */
    public function getStatus(Request $request) : Response
    {
        try {
            $config = $this->oauthConfigService->getConfig();

            if ($config === null) {
                return Response::create(
                    StatusCode::createOk(),
                    Json::encode([
                        'configured' => false,
                        'encryptionKeyConfigured' => $this->encryptionService->isEncryptionKeyConfigured(),
                        'encryptionKeySource' => $this->encryptionService->getEncryptionKeySource(),
                    ]),
                    [Header::createContentTypeJson()],
                );
            }

            $statusInfo = $config->getTokenStatusInfo();

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'configured' => true,
                    'connected' => $config->isConnected(),
                    'provider' => $config->provider,
                    'providerDisplayName' => $config->getProviderDisplayName(),
                    'fromAddress' => $config->fromAddress,
                    'tokenStatus' => $statusInfo['status'],
                    'tokenStatusLabel' => $statusInfo['label'],
                    'tokenStatusVariant' => $statusInfo['variant'],
                    'tokenStatusMessage' => $statusInfo['message'],
                    'connectedAt' => $config->connectedAt,
                    'lastTokenRefreshAt' => $config->lastTokenRefreshAt,
                    'clientSecretExpiring' => $this->oauthConfigService->isClientSecretExpiringSoon(),
                    'clientSecretExpiresAt' => $config->clientSecretExpiresAt,
                    'daysUntilSecretExpiry' => $config->getDaysUntilSecretExpiry(),
                    'encryptionKeyConfigured' => $this->encryptionService->isEncryptionKeyConfigured(),
                    'encryptionKeySource' => $this->encryptionService->getEncryptionKeySource(),
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (RuntimeException $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to get status: ' . $e->getMessage()]),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * Update email authentication mode (smtp_password or smtp_oauth)
     *
     * POST /admin/server/email/auth-mode
     */
    public function updateAuthMode(Request $request) : Response
    {
        $requestData = Json::decode($request->getBody());

        // Validate CSRF token
        if (!$this->csrfTokenService->validateToken($requestData['csrf'] ?? '')) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid CSRF token']),
                [Header::createContentTypeJson()],
            );
        }

        $mode = $requestData['mode'] ?? null;

        if (!in_array($mode, ['smtp_password', 'smtp_oauth'], true)) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid mode. Must be "smtp_password" or "smtp_oauth"']),
                [Header::createContentTypeJson()],
            );
        }

        try {
            // Get old mode before changing
            $oldMode = $this->serverSettings->getEmailAuthMode();

            $this->serverSettings->setEmailAuthMode($mode);

            // Log auth mode change event
            $userId = $this->authenticationService->getCurrentUserId();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_OAUTH_AUTH_MODE_CHANGED,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                [
                    'old_mode' => $oldMode,
                    'new_mode' => $mode,
                ]
            );

            return Response::create(
                StatusCode::createOk(),
                Json::encode(['success' => true, 'mode' => $mode]),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to update auth mode: ' . $e->getMessage()]),
                [Header::createContentTypeJson()],
            );
        }
    }
}
