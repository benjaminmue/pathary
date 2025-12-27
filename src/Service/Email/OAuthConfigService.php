<?php declare(strict_types=1);

namespace Movary\Service\Email;

use Doctrine\DBAL\Connection;
use Movary\Service\EncryptionService;
use RuntimeException;

/**
 * Service for managing OAuth email configuration
 *
 * Handles CRUD operations for OAuth provider settings including secure storage
 * of client secrets and refresh tokens.
 *
 * @security CRITICAL - Handles encrypted OAuth credentials
 */
class OAuthConfigService
{
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly EncryptionService $encryptionService,
    ) {
    }

    /**
     * Get the current OAuth configuration
     *
     * @return OAuthConfig|null Configuration or null if not configured
     */
    public function getConfig() : ?OAuthConfig
    {
        $row = $this->dbConnection->fetchAssociative(
            'SELECT * FROM `oauth_email_config` ORDER BY `id` DESC LIMIT 1'
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrateConfig($row);
    }

    /**
     * Check if OAuth is configured
     */
    public function isConfigured() : bool
    {
        return $this->getConfig() !== null;
    }

    /**
     * Get OAuth configuration for a specific provider
     *
     * @param string $provider Provider name ('gmail' or 'microsoft')
     * @return OAuthConfig|null Configuration or null if not found
     */
    public function getConfigByProvider(string $provider) : ?OAuthConfig
    {
        $row = $this->dbConnection->fetchAssociative(
            'SELECT * FROM `oauth_email_config` WHERE `provider` = ? ORDER BY `id` DESC LIMIT 1',
            [$provider]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrateConfig($row);
    }

    /**
     * Save OAuth configuration (create or update)
     *
     * @param string $provider Provider: 'gmail' or 'microsoft'
     * @param string $clientId Public client ID
     * @param string $clientSecret Client secret (will be encrypted)
     * @param string $fromAddress Email address to send from
     * @param string|null $tenantId Microsoft only: Azure AD tenant ID
     * @param int|null $secretExpiresInMonths Microsoft only: secret expiry duration
     * @throws RuntimeException If encryption fails
     */
    public function saveConfig(
        string $provider,
        string $clientId,
        string $clientSecret,
        string $fromAddress,
        ?string $tenantId = null,
        ?int $secretExpiresInMonths = null,
    ) : void {
        // Validate provider
        if (!in_array($provider, ['gmail', 'microsoft'], true)) {
            throw new RuntimeException("Invalid provider: {$provider}. Must be 'gmail' or 'microsoft'");
        }

        // Encrypt client secret
        $encrypted = $this->encryptionService->encrypt($clientSecret);

        // Calculate secret expiry (Microsoft only)
        $secretExpiresAt = null;
        if ($provider === 'microsoft' && $secretExpiresInMonths !== null) {
            $secretExpiresAt = date('Y-m-d H:i:s', strtotime("+{$secretExpiresInMonths} months"));
        }

        // Delete existing config (we only allow one OAuth config at a time)
        $this->dbConnection->executeStatement('DELETE FROM `oauth_email_config`');

        // Insert new config
        $this->dbConnection->prepare(
            <<<SQL
            INSERT INTO `oauth_email_config` (
                `provider`,
                `client_id`,
                `client_secret_encrypted`,
                `client_secret_iv`,
                `tenant_id`,
                `from_address`,
                `token_status`,
                `client_secret_expires_at`,
                `created_at`,
                `updated_at`
            ) VALUES (?, ?, ?, ?, ?, ?, 'not_connected', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            SQL
        )->executeStatement([
            $provider,
            $clientId,
            $encrypted['encrypted'],
            $encrypted['iv'],
            $tenantId,
            $fromAddress,
            $secretExpiresAt,
        ]);
    }

    /**
     * Update OAuth connection status after successful authorization
     *
     * @param string $refreshToken Refresh token from provider (will be encrypted)
     * @param string $scopes Granted scopes (space-separated)
     * @throws RuntimeException If no config exists or encryption fails
     */
    public function saveTokens(string $refreshToken, string $scopes) : void
    {
        $config = $this->getConfig();
        if ($config === null) {
            throw new RuntimeException('Cannot save tokens: OAuth configuration not found');
        }

        // Encrypt refresh token
        $encrypted = $this->encryptionService->encrypt($refreshToken);

        $this->dbConnection->prepare(
            <<<SQL
            UPDATE `oauth_email_config`
            SET
                `refresh_token_encrypted` = ?,
                `refresh_token_iv` = ?,
                `scopes` = ?,
                `token_status` = 'active',
                `connected_at` = CURRENT_TIMESTAMP,
                `last_token_refresh_at` = CURRENT_TIMESTAMP,
                `token_error` = NULL,
                `updated_at` = CURRENT_TIMESTAMP
            WHERE `id` = ?
            SQL
        )->executeStatement([
            $encrypted['encrypted'],
            $encrypted['iv'],
            $scopes,
            $config->id,
        ]);
    }

    /**
     * Update last token refresh timestamp
     */
    public function updateLastTokenRefresh() : void
    {
        $this->dbConnection->executeStatement(
            'UPDATE `oauth_email_config` SET `last_token_refresh_at` = CURRENT_TIMESTAMP, `updated_at` = CURRENT_TIMESTAMP'
        );
    }

    /**
     * Update token status and error message
     *
     * @param string $status Status: 'not_connected', 'active', 'expired', 'error'
     * @param string|null $error Error message if status is 'error'
     */
    public function updateTokenStatus(string $status, ?string $error = null) : void
    {
        $validStatuses = ['not_connected', 'active', 'expired', 'error'];
        if (!in_array($status, $validStatuses, true)) {
            throw new RuntimeException("Invalid status: {$status}");
        }

        $this->dbConnection->prepare(
            'UPDATE `oauth_email_config` SET `token_status` = ?, `token_error` = ?, `updated_at` = CURRENT_TIMESTAMP'
        )->executeStatement([$status, $error]);
    }

    /**
     * Disconnect OAuth (clear tokens but keep provider config)
     */
    public function disconnect() : void
    {
        $this->dbConnection->executeStatement(
            <<<SQL
            UPDATE `oauth_email_config`
            SET
                `refresh_token_encrypted` = NULL,
                `refresh_token_iv` = NULL,
                `scopes` = NULL,
                `token_status` = 'not_connected',
                `connected_at` = NULL,
                `last_token_refresh_at` = NULL,
                `token_error` = NULL,
                `updated_at` = CURRENT_TIMESTAMP
            SQL
        );
    }

    /**
     * Delete OAuth configuration completely
     */
    public function deleteConfig() : void
    {
        $this->dbConnection->executeStatement('DELETE FROM `oauth_email_config`');
    }

    /**
     * Check if client secret is expiring soon (Microsoft only)
     *
     * @param int $daysThreshold Number of days before expiry to warn
     * @return bool True if expiring within threshold
     */
    public function isClientSecretExpiringSoon(int $daysThreshold = 30) : bool
    {
        $config = $this->getConfig();
        if ($config === null || $config->clientSecretExpiresAt === null) {
            return false;
        }

        $expiryTimestamp = strtotime($config->clientSecretExpiresAt);
        $thresholdTimestamp = strtotime("+{$daysThreshold} days");

        return $expiryTimestamp <= $thresholdTimestamp;
    }

    /**
     * Get decrypted client secret for OAuth provider initialization
     *
     * @return string Decrypted client secret
     * @throws RuntimeException If no config exists or decryption fails
     */
    public function getDecryptedClientSecret() : string
    {
        $config = $this->getConfig();
        if ($config === null) {
            throw new RuntimeException('OAuth configuration not found');
        }

        return $this->encryptionService->decrypt(
            $config->clientSecretEncrypted,
            $config->clientSecretIv
        );
    }

    /**
     * Get decrypted refresh token for token refresh operations
     *
     * @return string Decrypted refresh token
     * @throws RuntimeException If no config exists, not connected, or decryption fails
     */
    public function getDecryptedRefreshToken() : string
    {
        $config = $this->getConfig();
        if ($config === null) {
            throw new RuntimeException('OAuth configuration not found');
        }

        if ($config->refreshTokenEncrypted === null || $config->refreshTokenIv === null) {
            throw new RuntimeException('Not connected: refresh token not available');
        }

        return $this->encryptionService->decrypt(
            $config->refreshTokenEncrypted,
            $config->refreshTokenIv
        );
    }

    /**
     * Update monitoring fields after token refresh attempt
     *
     * @param bool $success Whether refresh was successful
     * @param string|null $errorCode Error code if failed (sanitized)
     * @param string|null $errorMessage Error message if failed (sanitized, short)
     * @param bool $reauthRequired Whether re-authorization is needed
     */
    public function updateMonitoring(
        bool $success,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        bool $reauthRequired = false
    ) : void {
        if ($success) {
            $this->dbConnection->prepare(
                <<<SQL
                UPDATE `oauth_email_config`
                SET
                    `last_token_refresh_at` = CURRENT_TIMESTAMP,
                    `token_status` = 'active',
                    `token_error` = NULL,
                    `last_error_code` = NULL,
                    `reauth_required` = 0,
                    `updated_at` = CURRENT_TIMESTAMP
                SQL
            )->executeStatement();
        } else {
            // Sanitize error code and message
            $sanitizedCode = $errorCode !== null ? substr($errorCode, 0, 100) : null;
            $sanitizedMessage = $errorMessage !== null ? substr($errorMessage, 0, 255) : null;

            $this->dbConnection->prepare(
                <<<SQL
                UPDATE `oauth_email_config`
                SET
                    `last_failure_at` = CURRENT_TIMESTAMP,
                    `last_error_code` = ?,
                    `token_error` = ?,
                    `token_status` = IF(? = 1, 'expired', 'error'),
                    `reauth_required` = ?,
                    `updated_at` = CURRENT_TIMESTAMP
                SQL
            )->executeStatement([
                $sanitizedCode,
                $sanitizedMessage,
                $reauthRequired ? 1 : 0,
                $reauthRequired ? 1 : 0,
            ]);
        }
    }

    /**
     * Update alert level and next notification time
     *
     * @param string $alertLevel Alert level: ok, warn, critical, expired
     * @param string|null $nextNotificationAt Next notification datetime
     */
    public function updateAlertLevel(string $alertLevel, ?string $nextNotificationAt = null) : void
    {
        $validLevels = ['ok', 'warn', 'critical', 'expired'];
        if (!in_array($alertLevel, $validLevels, true)) {
            throw new RuntimeException("Invalid alert level: {$alertLevel}");
        }

        $this->dbConnection->prepare(
            'UPDATE `oauth_email_config` SET `alert_level` = ?, `next_notification_at` = ?, `updated_at` = CURRENT_TIMESTAMP'
        )->executeStatement([$alertLevel, $nextNotificationAt]);
    }

    /**
     * Hydrate database row into OAuthConfig value object
     *
     * @param array<string, mixed> $row Database row
     * @return OAuthConfig
     */
    private function hydrateConfig(array $row) : OAuthConfig
    {
        return new OAuthConfig(
            id: (int)$row['id'],
            provider: (string)$row['provider'],
            clientId: (string)$row['client_id'],
            clientSecretEncrypted: (string)$row['client_secret_encrypted'],
            clientSecretIv: (string)$row['client_secret_iv'],
            tenantId: $row['tenant_id'] !== null ? (string)$row['tenant_id'] : null,
            refreshTokenEncrypted: $row['refresh_token_encrypted'] !== null ? (string)$row['refresh_token_encrypted'] : null,
            refreshTokenIv: $row['refresh_token_iv'] !== null ? (string)$row['refresh_token_iv'] : null,
            fromAddress: (string)$row['from_address'],
            scopes: $row['scopes'] !== null ? (string)$row['scopes'] : null,
            tokenStatus: (string)$row['token_status'],
            tokenError: $row['token_error'] !== null ? (string)$row['token_error'] : null,
            clientSecretExpiresAt: $row['client_secret_expires_at'] !== null ? (string)$row['client_secret_expires_at'] : null,
            connectedAt: $row['connected_at'] !== null ? (string)$row['connected_at'] : null,
            lastTokenRefreshAt: $row['last_token_refresh_at'] !== null ? (string)$row['last_token_refresh_at'] : null,
            lastFailureAt: $row['last_failure_at'] !== null ? (string)$row['last_failure_at'] : null,
            lastErrorCode: $row['last_error_code'] !== null ? (string)$row['last_error_code'] : null,
            reauthRequired: (bool)($row['reauth_required'] ?? false),
            alertLevel: (string)($row['alert_level'] ?? 'ok'),
            nextNotificationAt: $row['next_notification_at'] !== null ? (string)$row['next_notification_at'] : null,
            createdAt: (string)$row['created_at'],
            updatedAt: (string)$row['updated_at'],
        );
    }
}
