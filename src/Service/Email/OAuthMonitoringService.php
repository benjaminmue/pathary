<?php declare(strict_types=1);

namespace Movary\Service\Email;

use Doctrine\DBAL\Connection;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\UserApi;
use Psr\Log\LoggerInterface;

/**
 * Service for monitoring OAuth token health and managing alerts
 *
 * Evaluates OAuth connection health, determines alert levels,
 * manages notification schedules, and tracks admin acknowledgements.
 *
 * @security CRITICAL - Handles OAuth monitoring but never logs token values
 */
class OAuthMonitoringService
{
    // Health thresholds (days)
    public const THRESHOLD_45_DAYS = 45;
    public const THRESHOLD_30_DAYS = 30;
    public const THRESHOLD_15_DAYS = 15;
    public const DAILY_ALERT_START = 14; // Start daily alerts at 14 days

    // Token health criteria
    public const MAX_DAYS_WITHOUT_REFRESH = 60; // Consider unhealthy if no refresh for 60 days
    public const MAX_CONSECUTIVE_FAILURES = 3; // Critical after 3 consecutive failures

    // Banner acknowledgement TTL (3 hours)
    public const BANNER_ACK_TTL_SECONDS = 10800;

    public function __construct(
        private readonly OAuthConfigService $oauthConfigService,
        private readonly OAuthTokenService $oauthTokenService,
        private readonly SecurityAuditService $securityAuditService,
        private readonly UserApi $userApi,
        private readonly EmailService $emailService,
        private readonly Connection $dbConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Run full monitoring check - attempts token refresh and evaluates health
     *
     * @return array{success: bool, alert_level: string, message: string}
     */
    public function runMonitoring() : array
    {
        $config = $this->oauthConfigService->getConfig();

        if ($config === null || !$config->isConnected()) {
            return [
                'success' => true,
                'alert_level' => 'ok',
                'message' => 'OAuth not configured or not connected',
            ];
        }

        $this->logger->info('OAuth monitoring check started', [
            'provider' => $config->provider,
        ]);

        // Attempt to refresh token (test connectivity)
        $refreshResult = $this->attemptTokenRefresh($config);

        // Evaluate health and determine alert level
        $healthEval = $this->evaluateHealth($config, $refreshResult);

        // Update alert level in database
        $this->oauthConfigService->updateAlertLevel(
            $healthEval['alert_level'],
            $healthEval['next_notification_at']
        );

        // Handle notifications if needed
        if ($healthEval['should_notify']) {
            $this->sendNotifications($config, $healthEval);
        }

        // Log monitoring event
        $this->logMonitoringEvent($config, $healthEval, $refreshResult);

        return [
            'success' => $refreshResult['success'],
            'alert_level' => $healthEval['alert_level'],
            'message' => $healthEval['message'],
        ];
    }

    /**
     * Attempt to refresh OAuth token to test connectivity
     *
     * @return array{success: bool, error_code: string|null, error_message: string|null, reauth_required: bool}
     */
    private function attemptTokenRefresh(OAuthConfig $config) : array
    {
        try {
            // Attempt token refresh using redirect URI
            $redirectUri = $this->oauthTokenService->buildRedirectUri();
            $this->oauthTokenService->getAccessToken($redirectUri);

            // Update monitoring fields with success
            $this->oauthConfigService->updateMonitoring(
                success: true,
                errorCode: null,
                errorMessage: null,
                reauthRequired: false
            );

            $this->logger->info('OAuth token refresh successful', [
                'provider' => $config->provider,
            ]);

            return [
                'success' => true,
                'error_code' => null,
                'error_message' => null,
                'reauth_required' => false,
            ];
        } catch (\Exception $e) {
            // Determine if error requires re-authorization
            $errorMessage = $e->getMessage();
            $reauthRequired = $this->isReauthRequired($errorMessage);
            $errorCode = $this->extractErrorCode($errorMessage);

            // Update monitoring fields with failure
            $this->oauthConfigService->updateMonitoring(
                success: false,
                errorCode: $errorCode,
                errorMessage: substr($errorMessage, 0, 255),
                reauthRequired: $reauthRequired
            );

            $this->logger->warning('OAuth token refresh failed', [
                'provider' => $config->provider,
                'error_code' => $errorCode,
                'error_message' => substr($errorMessage, 0, 255),
                'reauth_required' => $reauthRequired,
            ]);

            return [
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => substr($errorMessage, 0, 255),
                'reauth_required' => $reauthRequired,
            ];
        }
    }

    /**
     * Evaluate OAuth connection health and determine alert level
     *
     * @return array{alert_level: string, days_to_action: int|null, should_notify: bool, message: string, next_notification_at: string|null}
     */
    private function evaluateHealth(OAuthConfig $config, array $refreshResult) : array
    {
        // Refresh the config to get latest monitoring data
        $config = $this->oauthConfigService->getConfig();
        if ($config === null) {
            return [
                'alert_level' => 'ok',
                'days_to_action' => null,
                'should_notify' => false,
                'message' => 'OAuth not configured',
                'next_notification_at' => null,
            ];
        }

        // Check if re-auth is required (highest priority)
        if ($config->reauthRequired) {
            return [
                'alert_level' => 'expired',
                'days_to_action' => 0,
                'should_notify' => $this->shouldNotifyAt('expired', $config),
                'message' => 'Re-authorization required',
                'next_notification_at' => $this->calculateNextNotification('expired'),
            ];
        }

        // Check if recent refresh failed
        if (!$refreshResult['success']) {
            // Determine severity based on days since last success
            $daysSinceSuccess = $config->getDaysSinceLastRefresh();

            if ($daysSinceSuccess === null || $daysSinceSuccess >= 7) {
                return [
                    'alert_level' => 'critical',
                    'days_to_action' => 0,
                    'should_notify' => $this->shouldNotifyAt('critical', $config),
                    'message' => 'Token refresh failing for ' . ($daysSinceSuccess ?? 'unknown') . ' days',
                    'next_notification_at' => $this->calculateNextNotification('critical'),
                ];
            }

            return [
                'alert_level' => 'warn',
                'days_to_action' => 7 - $daysSinceSuccess,
                'should_notify' => $this->shouldNotifyAt('warn', $config),
                'message' => 'Recent token refresh failure',
                'next_notification_at' => $this->calculateNextNotification('warn'),
            ];
        }

        // Token is working - check age-based thresholds
        $daysSinceConnection = $this->getDaysSinceConnection($config);

        // Define warning thresholds based on token age
        // Most OAuth providers expect token rotation/refresh every 60-90 days
        $daysToWarning = self::MAX_DAYS_WITHOUT_REFRESH - ($daysSinceConnection ?? 0);

        if ($daysToWarning <= 0) {
            return [
                'alert_level' => 'critical',
                'days_to_action' => 0,
                'should_notify' => $this->shouldNotifyAt('critical', $config),
                'message' => 'Token age exceeds recommended refresh interval',
                'next_notification_at' => $this->calculateNextNotification('critical'),
            ];
        }

        if ($daysToWarning <= self::THRESHOLD_15_DAYS) {
            return [
                'alert_level' => 'critical',
                'days_to_action' => $daysToWarning,
                'should_notify' => $this->shouldNotifyAt('critical', $config),
                'message' => "Token refresh recommended in {$daysToWarning} days",
                'next_notification_at' => $this->calculateNextNotification('critical'),
            ];
        }

        if ($daysToWarning <= self::THRESHOLD_30_DAYS) {
            return [
                'alert_level' => 'warn',
                'days_to_action' => $daysToWarning,
                'should_notify' => $this->shouldNotifyAt('warn_30', $config),
                'message' => "Token refresh recommended in {$daysToWarning} days",
                'next_notification_at' => $this->calculateNextNotification('warn_30'),
            ];
        }

        if ($daysToWarning <= self::THRESHOLD_45_DAYS) {
            return [
                'alert_level' => 'warn',
                'days_to_action' => $daysToWarning,
                'should_notify' => $this->shouldNotifyAt('warn_45', $config),
                'message' => "Token refresh recommended in {$daysToWarning} days",
                'next_notification_at' => $this->calculateNextNotification('warn_45'),
            ];
        }

        // All good
        return [
            'alert_level' => 'ok',
            'days_to_action' => $daysToWarning,
            'should_notify' => false,
            'message' => 'OAuth connection healthy',
            'next_notification_at' => null,
        ];
    }

    /**
     * Determine if notification should be sent based on threshold and last notification
     */
    private function shouldNotifyAt(string $threshold, OAuthConfig $config) : bool
    {
        // If next_notification_at is null or in the past, we should notify
        if ($config->nextNotificationAt === null) {
            return true;
        }

        $nextNotificationTime = strtotime($config->nextNotificationAt);
        $now = time();

        return $nextNotificationTime <= $now;
    }

    /**
     * Calculate next notification time based on threshold
     */
    private function calculateNextNotification(string $threshold) : ?string
    {
        $now = time();

        $nextTime = match ($threshold) {
            'warn_45' => strtotime('+1 week', $now), // Check again in 1 week
            'warn_30' => strtotime('+3 days', $now), // Check again in 3 days
            'critical', 'expired' => strtotime('+1 day', $now), // Daily notifications
            default => null,
        };

        return $nextTime !== null ? date('Y-m-d H:i:s', $nextTime) : null;
    }

    /**
     * Send email notifications to all admins
     */
    private function sendNotifications(OAuthConfig $config, array $healthEval) : void
    {
        $admins = $this->getAdminUsers();

        if (empty($admins)) {
            $this->logger->warning('No admin users found for OAuth notifications');
            return;
        }

        $emailsSent = 0;

        foreach ($admins as $admin) {
            if ($admin['email'] === null || trim($admin['email']) === '') {
                continue;
            }

            try {
                $this->sendAdminNotificationEmail(
                    $admin['email'],
                    $admin['name'],
                    $config,
                    $healthEval
                );
                $emailsSent++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to send OAuth notification email', [
                    'admin_email' => $admin['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('OAuth notification emails sent', [
            'count' => $emailsSent,
            'alert_level' => $healthEval['alert_level'],
        ]);
    }

    /**
     * Send notification email to an admin
     */
    private function sendAdminNotificationEmail(
        string $email,
        string $name,
        OAuthConfig $config,
        array $healthEval
    ) : void {
        $subject = $this->getEmailSubject($config, $healthEval);
        $body = $this->getEmailBody($name, $config, $healthEval);

        // Use legacy email service (will use SMTP password if OAuth is failing)
        $this->emailService->sendEmail(
            toEmail: $email,
            toName: $name,
            subject: $subject,
            body: $body,
            isHtml: true
        );
    }

    /**
     * Get email subject based on alert level
     */
    private function getEmailSubject(OAuthConfig $config, array $healthEval) : string
    {
        $provider = $config->getProviderDisplayName();
        $alertLevel = $healthEval['alert_level'];

        return match ($alertLevel) {
            'expired' => "[URGENT] {$provider} OAuth Connection Expired - Action Required",
            'critical' => "[CRITICAL] {$provider} OAuth Connection Needs Attention",
            'warn' => "[WARNING] {$provider} OAuth Connection Status",
            default => "{$provider} OAuth Connection Status",
        };
    }

    /**
     * Get email body HTML
     */
    private function getEmailBody(string $name, OAuthConfig $config, array $healthEval) : string
    {
        $provider = $config->getProviderDisplayName();
        $alertLevel = $healthEval['alert_level'];
        $message = $healthEval['message'];
        $daysToAction = $healthEval['days_to_action'];

        $settingsUrl = getenv('APPLICATION_URL') . '/admin/server/email';

        $urgencyColor = match ($alertLevel) {
            'expired', 'critical' => '#dc3545',
            'warn' => '#ffc107',
            default => '#0d6efd',
        };

        $urgencyLabel = match ($alertLevel) {
            'expired' => 'URGENT - IMMEDIATE ACTION REQUIRED',
            'critical' => 'CRITICAL - ACTION NEEDED SOON',
            'warn' => 'WARNING - ATTENTION NEEDED',
            default => 'INFORMATION',
        };

        $actionText = match ($alertLevel) {
            'expired' => 'Your OAuth connection has expired and emails cannot be sent. Please reconnect immediately.',
            'critical' => 'Your OAuth connection is experiencing issues. Please check the connection and reconnect if necessary.',
            'warn' => 'Your OAuth connection may need attention soon. Please review the connection status.',
            default => 'This is an informational message about your OAuth connection.',
        };

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: {$urgencyColor}; color: white; padding: 20px; text-align: center; }
                .content { background: #f8f9fa; padding: 20px; margin-top: 20px; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid {$urgencyColor}; }
                .button { display: inline-block; padding: 12px 24px; background: {$urgencyColor}; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 0.9em; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>{$urgencyLabel}</h1>
                    <p>{$provider} Email OAuth Monitoring Alert</p>
                </div>

                <div class="content">
                    <p>Hello {$name},</p>

                    <p>{$actionText}</p>

                    <div class="details">
                        <strong>Provider:</strong> {$provider}<br>
                        <strong>Status:</strong> {$message}<br>
                        <strong>Alert Level:</strong> {$alertLevel}<br>
                        {$this->renderDaysToAction($daysToAction)}
                        <strong>Last Refresh:</strong> {$this->formatTimestamp($config->lastTokenRefreshAt)}<br>
                        {$this->renderLastError($config)}
                    </div>

                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Open the Email Settings page in your admin panel</li>
                        <li>Navigate to the OAuth tab</li>
                        <li>Review the connection status</li>
                        <li>Click "Reconnect" if needed to re-authorize</li>
                    </ol>

                    <a href="{$settingsUrl}" class="button">Open Email Settings</a>
                </div>

                <div class="footer">
                    <p>This is an automated monitoring alert from Pathary.</p>
                    <p>You are receiving this because you are an administrator.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function renderDaysToAction(?int $days) : string
    {
        if ($days === null || $days <= 0) {
            return '';
        }

        return "<strong>Time to Action:</strong> {$days} days<br>";
    }

    private function renderLastError(OAuthConfig $config) : string
    {
        if ($config->lastErrorCode === null && $config->tokenError === null) {
            return '';
        }

        $error = $config->lastErrorCode ?? $config->tokenError ?? 'Unknown error';
        $errorTime = $config->lastFailureAt !== null ? ' (' . $this->formatTimestamp($config->lastFailureAt) . ')' : '';

        return "<strong>Last Error:</strong> {$error}{$errorTime}<br>";
    }

    private function formatTimestamp(?string $timestamp) : string
    {
        if ($timestamp === null) {
            return 'Never';
        }

        return date('Y-m-d H:i:s', strtotime($timestamp));
    }

    /**
     * Log monitoring event to security audit log
     */
    private function logMonitoringEvent(OAuthConfig $config, array $healthEval, array $refreshResult) : void
    {
        // Determine which event type to log
        $eventType = $this->getEventTypeForHealth($healthEval);

        if ($eventType === null) {
            return; // No event to log for 'ok' status
        }

        // Use system user ID (0) for automated monitoring events
        $userId = 0;

        $metadata = [
            'provider' => $config->provider,
            'alert_level' => $healthEval['alert_level'],
            'status' => $refreshResult['success'] ? 'success' : 'failed',
        ];

        if (!$refreshResult['success']) {
            $metadata['error_code'] = $refreshResult['error_code'];
            $metadata['reauth_required'] = $refreshResult['reauth_required'];
        }

        $this->securityAuditService->log(
            userId: $userId,
            eventType: $eventType,
            ipAddress: null,
            userAgent: 'OAuth Monitoring Service',
            metadata: $metadata
        );
    }

    private function getEventTypeForHealth(array $healthEval) : ?string
    {
        $alertLevel = $healthEval['alert_level'];
        $daysToAction = $healthEval['days_to_action'];

        if ($alertLevel === 'expired') {
            return SecurityAuditService::EVENT_OAUTH_TOKEN_EXPIRED;
        }

        if ($alertLevel === 'critical' && $daysToAction !== null && $daysToAction <= self::DAILY_ALERT_START) {
            return SecurityAuditService::EVENT_OAUTH_TOKEN_WARN_DAILY;
        }

        if ($alertLevel === 'warn' && $daysToAction !== null) {
            if ($daysToAction <= self::THRESHOLD_15_DAYS && $daysToAction > self::DAILY_ALERT_START) {
                return SecurityAuditService::EVENT_OAUTH_TOKEN_WARN_15;
            }
            if ($daysToAction <= self::THRESHOLD_30_DAYS && $daysToAction > self::THRESHOLD_15_DAYS) {
                return SecurityAuditService::EVENT_OAUTH_TOKEN_WARN_30;
            }
            if ($daysToAction <= self::THRESHOLD_45_DAYS && $daysToAction > self::THRESHOLD_30_DAYS) {
                return SecurityAuditService::EVENT_OAUTH_TOKEN_WARN_45;
            }
        }

        return null; // Don't log for 'ok' status
    }

    /**
     * Get all admin users
     *
     * @return array<array{id: int, email: string|null, name: string}>
     */
    private function getAdminUsers() : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT id, email, name FROM user WHERE is_admin = 1 AND email IS NOT NULL AND email != ""'
        );
    }

    /**
     * Get days since OAuth connection was established
     */
    private function getDaysSinceConnection(OAuthConfig $config) : ?int
    {
        if ($config->connectedAt === null) {
            return null;
        }

        $connected = strtotime($config->connectedAt);
        $now = time();
        $diff = $now - $connected;

        return (int)floor($diff / 86400);
    }

    /**
     * Determine if error requires re-authorization
     */
    private function isReauthRequired(string $errorMessage) : bool
    {
        $reauthIndicators = [
            'invalid_grant',
            'invalid_client',
            'unauthorized_client',
            'access_denied',
            'token has been expired or revoked',
            'token_revoked',
            'authorization_pending',
        ];

        $lowerError = strtolower($errorMessage);

        foreach ($reauthIndicators as $indicator) {
            if (str_contains($lowerError, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract error code from error message
     */
    private function extractErrorCode(string $errorMessage) : ?string
    {
        // Try to extract structured error code
        if (preg_match('/error["\']?\s*:\s*["\']?([a-z_]+)["\']?/i', $errorMessage, $matches)) {
            return $matches[1];
        }

        // Try to extract from common OAuth error patterns
        if (preg_match('/(invalid_grant|invalid_client|unauthorized_client|access_denied)/i', $errorMessage, $matches)) {
            return $matches[1];
        }

        // Return generic error code
        if (str_contains(strtolower($errorMessage), 'network')) {
            return 'network_error';
        }

        if (str_contains(strtolower($errorMessage), 'timeout')) {
            return 'timeout';
        }

        return 'unknown_error';
    }

    /**
     * Check if admin should see banner (not acknowledged recently or alert level escalated)
     *
     * @param int $userId Admin user ID
     * @param string $currentAlertLevel Current alert level
     * @return bool True if banner should be shown
     */
    public function shouldShowBanner(int $userId, string $currentAlertLevel) : bool
    {
        // Get last acknowledgement for this user
        $lastAck = $this->dbConnection->fetchAssociative(
            'SELECT alert_level_acked, acked_at FROM oauth_admin_banner_ack WHERE user_id = ? ORDER BY acked_at DESC LIMIT 1',
            [$userId]
        );

        if ($lastAck === false) {
            // Never acknowledged, show banner
            return true;
        }

        $ackedLevel = $lastAck['alert_level_acked'];
        $ackedAt = strtotime($lastAck['acked_at']);
        $now = time();

        // Check if alert level has escalated since last ack
        $ackedSeverity = $this->getAlertSeverity($ackedLevel);
        $currentSeverity = $this->getAlertSeverity($currentAlertLevel);

        if ($currentSeverity > $ackedSeverity) {
            // Alert level escalated, show banner immediately
            return true;
        }

        // Check if acknowledgement has expired (3 hours)
        if (($now - $ackedAt) >= self::BANNER_ACK_TTL_SECONDS) {
            return true;
        }

        // Recently acknowledged and same/lower severity
        return false;
    }

    /**
     * Record admin banner acknowledgement
     */
    public function acknowledgeBanner(int $userId, string $alertLevel) : void
    {
        // Delete old acknowledgement for this user
        $this->dbConnection->executeStatement(
            'DELETE FROM oauth_admin_banner_ack WHERE user_id = ?',
            [$userId]
        );

        // Insert new acknowledgement
        $this->dbConnection->insert('oauth_admin_banner_ack', [
            'user_id' => $userId,
            'alert_level_acked' => $alertLevel,
            'acked_at' => date('Y-m-d H:i:s'),
        ]);

        // Log acknowledgement event
        $config = $this->oauthConfigService->getConfig();
        if ($config !== null) {
            $this->securityAuditService->log(
                userId: $userId,
                eventType: SecurityAuditService::EVENT_OAUTH_BANNER_ACKNOWLEDGED,
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
                userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
                metadata: [
                    'provider' => $config->provider,
                    'alert_level' => $alertLevel,
                ]
            );
        }
    }

    private function getAlertSeverity(string $alertLevel) : int
    {
        return match ($alertLevel) {
            'ok' => 0,
            'warn' => 1,
            'critical' => 2,
            'expired' => 3,
            default => -1,
        };
    }
}
