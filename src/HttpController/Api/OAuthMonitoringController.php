<?php declare(strict_types=1);

namespace Movary\HttpController\Api;

use Movary\Domain\User\Service\Authentication;
use Movary\Service\Email\OAuthConfigService;
use Movary\Service\Email\OAuthMonitoringService;
use Movary\Util\Json;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

/**
 * API controller for OAuth monitoring features
 */
class OAuthMonitoringController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly OAuthConfigService $oauthConfigService,
        private readonly OAuthMonitoringService $oauthMonitoringService,
    ) {
    }

    /**
     * Get OAuth monitoring status for admin banner
     *
     * Returns whether banner should be shown and relevant data
     */
    public function getMonitoringStatus(Request $request) : Response
    {
        // Only accessible to admins
        if (!$this->authenticationService->isUserAuthenticatedWithCookie()
            || !$this->authenticationService->getCurrentUser()->isAdmin()) {
            return Response::createForbidden();
        }

        $config = $this->oauthConfigService->getConfig();

        // If OAuth not configured or not connected, don't show banner
        if ($config === null || !$config->isConnected()) {
            return Response::createJson(Json::encode([
                'should_show_banner' => false,
                'alert_level' => 'ok',
            ]));
        }

        // Check if banner should be shown for this user
        $userId = $this->authenticationService->getCurrentUserId();
        $shouldShow = $config->requiresAttention()
            && $this->oauthMonitoringService->shouldShowBanner($userId, $config->alertLevel);

        $alertInfo = $config->getAlertLevelInfo();

        return Response::createJson(Json::encode([
            'should_show_banner' => $shouldShow,
            'alert_level' => $config->alertLevel,
            'provider' => $config->provider,
            'provider_display_name' => $config->getProviderDisplayName(),
            'message' => $this->getStatusMessage($config),
            'severity' => $alertInfo['severity'],
            'label' => $alertInfo['label'],
        ]));
    }

    /**
     * Acknowledge OAuth monitoring banner
     *
     * Suppresses banner for 3 hours unless alert level escalates
     */
    public function acknowledgeBanner(Request $request) : Response
    {
        // Only accessible to admins
        if (!$this->authenticationService->isUserAuthenticatedWithCookie()
            || !$this->authenticationService->getCurrentUser()->isAdmin()) {
            return Response::createForbidden();
        }

        $data = Json::decode($request->getBody());
        $alertLevel = $data['alert_level'] ?? null;

        if ($alertLevel === null) {
            return Response::createBadRequest('alert_level is required');
        }

        $userId = $this->authenticationService->getCurrentUserId();

        // Record acknowledgement
        $this->oauthMonitoringService->acknowledgeBanner($userId, $alertLevel);

        return Response::createJson(Json::encode([
            'success' => true,
            'message' => 'Banner acknowledged for 3 hours',
        ]));
    }

    /**
     * Get human-readable status message based on config
     */
    private function getStatusMessage($config) : string
    {
        if ($config->reauthRequired) {
            return 'Re-authorization is required. Email sending is disabled until you reconnect.';
        }

        $daysSinceRefresh = $config->getDaysSinceLastRefresh();

        if ($config->alertLevel === 'expired') {
            return 'OAuth connection has expired. Please reconnect immediately.';
        }

        if ($config->alertLevel === 'critical') {
            if ($daysSinceRefresh !== null && $daysSinceRefresh >= 7) {
                return "Token refresh has been failing for {$daysSinceRefresh} days. Please check the connection.";
            }
            return 'OAuth connection requires immediate attention.';
        }

        if ($config->alertLevel === 'warn') {
            $daysToAction = OAuthMonitoringService::MAX_DAYS_WITHOUT_REFRESH - ($config->getDaysSinceLastRefresh() ?? 0);
            if ($daysToAction > 0) {
                return "OAuth token refresh recommended in approximately {$daysToAction} days to ensure uninterrupted email delivery.";
            }
            return 'OAuth connection may need attention soon.';
        }

        return 'OAuth connection is healthy.';
    }

    private function getDaysSinceConnection($config) : ?int
    {
        if ($config->connectedAt === null) {
            return null;
        }

        $connected = strtotime($config->connectedAt);
        $now = time();
        $diff = $now - $connected;

        return (int)floor($diff / 86400);
    }
}
