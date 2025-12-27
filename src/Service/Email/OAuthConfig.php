<?php declare(strict_types=1);

namespace Movary\Service\Email;

/**
 * Value object representing OAuth email configuration
 *
 * Immutable container for OAuth provider settings and token status.
 * Sensitive fields (client_secret, refresh_token) are stored encrypted.
 *
 * @readonly
 */
class OAuthConfig
{
    public function __construct(
        public readonly int $id,
        public readonly string $provider,
        public readonly string $clientId,
        public readonly string $clientSecretEncrypted,
        public readonly string $clientSecretIv,
        public readonly ?string $tenantId,
        public readonly ?string $refreshTokenEncrypted,
        public readonly ?string $refreshTokenIv,
        public readonly string $fromAddress,
        public readonly ?string $scopes,
        public readonly string $tokenStatus,
        public readonly ?string $tokenError,
        public readonly ?string $clientSecretExpiresAt,
        public readonly ?string $connectedAt,
        public readonly ?string $lastTokenRefreshAt,
        public readonly ?string $lastFailureAt,
        public readonly ?string $lastErrorCode,
        public readonly bool $reauthRequired,
        public readonly string $alertLevel,
        public readonly ?string $nextNotificationAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * Check if OAuth is connected (has valid tokens)
     */
    public function isConnected() : bool
    {
        return $this->tokenStatus === 'active'
            && $this->refreshTokenEncrypted !== null
            && $this->refreshTokenIv !== null;
    }

    /**
     * Check if provider is Gmail
     */
    public function isGmail() : bool
    {
        return $this->provider === 'gmail';
    }

    /**
     * Check if provider is Microsoft 365
     */
    public function isMicrosoft() : bool
    {
        return $this->provider === 'microsoft';
    }

    /**
     * Get provider display name
     */
    public function getProviderDisplayName() : string
    {
        return match ($this->provider) {
            'gmail' => 'Gmail',
            'microsoft' => 'Microsoft 365',
            default => $this->provider,
        };
    }

    /**
     * Get SMTP host for this provider
     */
    public function getSmtpHost() : string
    {
        return match ($this->provider) {
            'gmail' => 'smtp.gmail.com',
            'microsoft' => 'smtp.office365.com',
            default => throw new \RuntimeException("Unknown provider: {$this->provider}"),
        };
    }

    /**
     * Get SMTP port for this provider
     */
    public function getSmtpPort() : int
    {
        return match ($this->provider) {
            'gmail' => 465,
            'microsoft' => 587,
            default => throw new \RuntimeException("Unknown provider: {$this->provider}"),
        };
    }

    /**
     * Get SMTP encryption for this provider
     */
    public function getSmtpEncryption() : string
    {
        return match ($this->provider) {
            'gmail' => 'ssl', // PHPMailer::ENCRYPTION_SMTPS
            'microsoft' => 'tls', // PHPMailer::ENCRYPTION_STARTTLS
            default => throw new \RuntimeException("Unknown provider: {$this->provider}"),
        };
    }

    /**
     * Get days until client secret expires (Microsoft only)
     *
     * @return int|null Days until expiry, null if not applicable
     */
    public function getDaysUntilSecretExpiry() : ?int
    {
        if ($this->clientSecretExpiresAt === null) {
            return null;
        }

        $expiryTimestamp = strtotime($this->clientSecretExpiresAt);
        $now = time();

        $diff = $expiryTimestamp - $now;
        return (int)ceil($diff / 86400); // Convert seconds to days
    }

    /**
     * Check if client secret has expired
     */
    public function isClientSecretExpired() : bool
    {
        if ($this->clientSecretExpiresAt === null) {
            return false;
        }

        return strtotime($this->clientSecretExpiresAt) <= time();
    }

    /**
     * Get scopes as array
     *
     * @return array<string>
     */
    public function getScopesArray() : array
    {
        if ($this->scopes === null || trim($this->scopes) === '') {
            return [];
        }

        return array_filter(explode(' ', $this->scopes));
    }

    /**
     * Get token status with contextual information
     *
     * @return array{status: string, label: string, variant: string, message: string|null}
     */
    public function getTokenStatusInfo() : array
    {
        return match ($this->tokenStatus) {
            'active' => [
                'status' => 'active',
                'label' => 'Connected',
                'variant' => 'success',
                'message' => null,
            ],
            'expired' => [
                'status' => 'expired',
                'label' => 'Expired',
                'variant' => 'warning',
                'message' => 'Tokens have expired. Please reconnect.',
            ],
            'error' => [
                'status' => 'error',
                'label' => 'Error',
                'variant' => 'danger',
                'message' => $this->tokenError ?? 'An error occurred. Please reconnect.',
            ],
            'not_connected' => [
                'status' => 'not_connected',
                'label' => 'Not Connected',
                'variant' => 'secondary',
                'message' => 'Click "Connect" to authorize email sending.',
            ],
            default => [
                'status' => 'unknown',
                'label' => 'Unknown',
                'variant' => 'secondary',
                'message' => null,
            ],
        };
    }

    /**
     * Get alert level information with variant and label
     *
     * @return array{level: string, label: string, variant: string, severity: int}
     */
    public function getAlertLevelInfo() : array
    {
        return match ($this->alertLevel) {
            'ok' => [
                'level' => 'ok',
                'label' => 'Healthy',
                'variant' => 'success',
                'severity' => 0,
            ],
            'warn' => [
                'level' => 'warn',
                'label' => 'Warning',
                'variant' => 'warning',
                'severity' => 1,
            ],
            'critical' => [
                'level' => 'critical',
                'label' => 'Critical',
                'variant' => 'danger',
                'severity' => 2,
            ],
            'expired' => [
                'level' => 'expired',
                'label' => 'Expired',
                'variant' => 'danger',
                'severity' => 3,
            ],
            default => [
                'level' => 'unknown',
                'label' => 'Unknown',
                'variant' => 'secondary',
                'severity' => -1,
            ],
        };
    }

    /**
     * Check if alert level requires admin attention
     */
    public function requiresAttention() : bool
    {
        return in_array($this->alertLevel, ['warn', 'critical', 'expired'], true);
    }

    /**
     * Get days since last successful refresh
     */
    public function getDaysSinceLastRefresh() : ?int
    {
        if ($this->lastTokenRefreshAt === null) {
            return null;
        }

        $lastRefresh = strtotime($this->lastTokenRefreshAt);
        $now = time();
        $diff = $now - $lastRefresh;

        return (int)floor($diff / 86400);
    }

    /**
     * Get days since last failure
     */
    public function getDaysSinceLastFailure() : ?int
    {
        if ($this->lastFailureAt === null) {
            return null;
        }

        $lastFailure = strtotime($this->lastFailureAt);
        $now = time();
        $diff = $now - $lastFailure;

        return (int)floor($diff / 86400);
    }
}
