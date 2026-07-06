<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Repository\SecurityAuditRepository;
use Movary\Domain\User\Service\Authentication;
use Movary\Service\CsrfTokenService;
use Movary\Service\Email\OAuthConfigService;
use Movary\Service\EncryptionService;
use Movary\Service\ServerSettings;
use Movary\Util\Json;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class AdminController
{
    // Tab registry - central configuration for all admin tabs
    private const array TAB_REGISTRY = [
        'movies' => [
            'id' => 'movies',
            'label' => 'admin.tab_movies',
            'icon' => 'bi bi-film',
            'route' => '/admin/movies',
            'template' => 'page/admin/tabs/movies.html.twig',
            'enabled' => true,
        ],
        'users' => [
            'id' => 'users',
            'label' => 'admin.tab_users',
            'icon' => 'bi bi-people',
            'route' => '/admin/users',
            'template' => 'page/admin/tabs/users.html.twig',
            'enabled' => true,
        ],
        'server' => [
            'id' => 'server',
            'label' => 'admin.tab_server',
            'icon' => 'bi bi-server',
            'route' => '/admin/server',
            'template' => 'page/admin/tabs/server.html.twig',
            'enabled' => true,
        ],
        'integrations' => [
            'id' => 'integrations',
            'label' => 'admin.tab_integrations',
            'icon' => 'bi bi-plug',
            'route' => '/admin/integrations',
            'template' => 'page/admin/tabs/integrations.html.twig',
            'enabled' => true,
        ],
        'events' => [
            'id' => 'events',
            'label' => 'admin.tab_events',
            'icon' => 'bi bi-activity',
            'route' => '/admin/events',
            'template' => 'page/admin/tabs/events.html.twig',
            'enabled' => true,
        ],
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
        private readonly CsrfTokenService $csrfTokenService,
        private readonly ServerSettings $serverSettings,
        private readonly OAuthConfigService $oauthConfigService,
        private readonly EncryptionService $encryptionService,
        private readonly SecurityAuditRepository $securityAuditRepository,
    ) {
    }

    /**
     * Main admin panel - redirects to first enabled tab
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function index(Request $request) : Response
    {
        $firstTab = $this->getFirstEnabledTab();
        return Response::createSeeOther($firstTab['route']);
    }

    /**
     * Render Movie Management tab
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function renderMoviesTab(Request $request) : Response
    {
        return $this->renderTab('movies', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Render User Management tab
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function renderUsersTab(Request $request) : Response
    {
        return $this->renderTab('users', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Render Server Management tab
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function renderServerTab(Request $request) : Response
    {
        // Load SMTP settings from database/environment
        $smtpHost = $this->serverSettings->getSmtpHost();
        $smtpPort = $this->serverSettings->getSmtpPort();
        $smtpEncryption = $this->serverSettings->getSmtpEncryption();
        $smtpFromAddress = $this->serverSettings->getFromAddress();
        $smtpFromDisplayName = $this->serverSettings->getFromDisplayName();
        $smtpUsername = $this->serverSettings->getSmtpUser();
        $smtpPassword = $this->serverSettings->getSmtpPassword();
        $smtpWithAuth = $this->serverSettings->getSmtpWithAuthentication();

        // Load OAuth configuration
        $emailAuthMode = $this->serverSettings->getEmailAuthMode();
        $oauthConfig = $this->oauthConfigService->getConfig();
        $encryptionKeyConfigured = $this->encryptionService->isEncryptionKeyConfigured();
        $encryptionKeySource = $this->encryptionService->getEncryptionKeySource();

        // Prepare OAuth status info for UI
        $oauthData = null;
        if ($oauthConfig !== null) {
            $statusInfo = $oauthConfig->getTokenStatusInfo();
            $oauthData = [
                'configured' => true,
                'connected' => $oauthConfig->isConnected(),
                'provider' => $oauthConfig->provider,
                'providerDisplayName' => $oauthConfig->getProviderDisplayName(),
                'clientId' => $oauthConfig->clientId,
                'fromAddress' => $oauthConfig->fromAddress,
                'tenantId' => $oauthConfig->tenantId,
                'tokenStatus' => $statusInfo['status'],
                'tokenStatusLabel' => $statusInfo['label'],
                'tokenStatusVariant' => $statusInfo['variant'],
                'tokenStatusMessage' => $statusInfo['message'],
                'connectedAt' => $oauthConfig->connectedAt,
                'lastTokenRefreshAt' => $oauthConfig->lastTokenRefreshAt,
                'clientSecretExpiring' => $this->oauthConfigService->isClientSecretExpiringSoon(),
                'clientSecretExpiresAt' => $oauthConfig->clientSecretExpiresAt,
                'daysUntilSecretExpiry' => $oauthConfig->getDaysUntilSecretExpiry(),
            ];
        }

        return $this->renderTab('server', [
            'csrf' => $this->csrfTokenService->generateToken(),
            'smtpHost' => $smtpHost,
            'smtpPort' => $smtpPort,
            'smtpEncryption' => $smtpEncryption ?? '',
            'smtpFromAddress' => $smtpFromAddress,
            'smtpFromDisplayName' => $smtpFromDisplayName,
            'smtpUsername' => $smtpUsername,
            'smtpPasswordConfigured' => !empty($smtpPassword),
            'smtpWithAuth' => $smtpWithAuth,
            'emailAuthMode' => $emailAuthMode,
            'oauthConfig' => $oauthData,
            'encryptionKeyConfigured' => $encryptionKeyConfigured,
            'encryptionKeySource' => $encryptionKeySource,
        ]);
    }

    /**
     * Render Integrations tab
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function renderIntegrationsTab(Request $request) : Response
    {
        return $this->renderTab('integrations', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Render Events tab
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function renderEventsTab(Request $request) : Response
    {
        // Fetch distinct event types for filter dropdown
        $eventTypes = $this->securityAuditRepository->findDistinctEventTypes();

        return $this->renderTab('events', [
            'csrf' => $this->csrfTokenService->generateToken(),
            'eventTypes' => $eventTypes,
        ]);
    }

    /**
     * Generic tab renderer
     */
    private function renderTab(string $tabId, array $additionalData = []) : Response
    {
        $tabs = $this->getEnabledTabs();
        $activeTab = $this->getTabRegistry()[$tabId] ?? null;

        if ($activeTab === null || $activeTab['enabled'] === false) {
            return Response::createNotFound();
        }

        $templateData = array_merge([
            'tabs' => $tabs,
            'activeTab' => $tabId,
            'currentUser' => $this->authenticationService->getCurrentUser(),
        ], $additionalData);

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('page/admin/base.html.twig', $templateData)
        );
    }

    /**
     * Get all enabled tabs from registry
     */
    private function getEnabledTabs() : array
    {
        return array_filter($this->getTabRegistry(), fn($tab) => $tab['enabled'] === true);
    }

    /**
     * Tab registry typed so the "enabled" flag is treated as a boolean feature switch.
     *
     * @return array<string, array{id: string, label: string, icon: string, route: string, template: string, enabled: bool}>
     */
    private function getTabRegistry() : array
    {
        return self::TAB_REGISTRY;
    }

    /**
     * Get first enabled tab for default redirect
     */
    private function getFirstEnabledTab() : array
    {
        $enabledTabs = $this->getEnabledTabs();
        $firstTab = reset($enabledTabs);

        return $firstTab === false ? [] : $firstTab;
    }
}
