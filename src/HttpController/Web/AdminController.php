<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Service\CsrfTokenService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class AdminController
{
    // Tab registry - central configuration for all admin tabs
    private const TAB_REGISTRY = [
        'movies' => [
            'id' => 'movies',
            'label' => 'Movie Management',
            'icon' => 'bi bi-film',
            'route' => '/admin/movies',
            'template' => 'page/admin/tabs/movies.html.twig',
            'enabled' => true,
        ],
        'users' => [
            'id' => 'users',
            'label' => 'User Management',
            'icon' => 'bi bi-people',
            'route' => '/admin/users',
            'template' => 'page/admin/tabs/users.html.twig',
            'enabled' => true,
        ],
        'server' => [
            'id' => 'server',
            'label' => 'Server Management',
            'icon' => 'bi bi-server',
            'route' => '/admin/server',
            'template' => 'page/admin/tabs/server.html.twig',
            'enabled' => true,
        ],
        'integrations' => [
            'id' => 'integrations',
            'label' => 'Integrations',
            'icon' => 'bi bi-plug',
            'route' => '/admin/integrations',
            'template' => 'page/admin/tabs/integrations.html.twig',
            'enabled' => true,
        ],
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
        private readonly CsrfTokenService $csrfTokenService,
    ) {
    }

    /**
     * Main admin panel - redirects to first enabled tab
     */
    public function index(Request $request) : Response
    {
        $firstTab = $this->getFirstEnabledTab();
        return Response::createSeeOther($firstTab['route']);
    }

    /**
     * Render Movie Management tab
     */
    public function renderMoviesTab(Request $request) : Response
    {
        return $this->renderTab('movies', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Render User Management tab
     */
    public function renderUsersTab(Request $request) : Response
    {
        return $this->renderTab('users', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Render Server Management tab
     */
    public function renderServerTab(Request $request) : Response
    {
        return $this->renderTab('server', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Render Integrations tab
     */
    public function renderIntegrationsTab(Request $request) : Response
    {
        return $this->renderTab('integrations', [
            'csrf' => $this->csrfTokenService->generateToken(),
        ]);
    }

    /**
     * Generic tab renderer
     */
    private function renderTab(string $tabId, array $additionalData = []) : Response
    {
        $tabs = $this->getEnabledTabs();
        $activeTab = self::TAB_REGISTRY[$tabId] ?? null;

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
        return array_filter(self::TAB_REGISTRY, fn($tab) => $tab['enabled'] === true);
    }

    /**
     * Get first enabled tab for default redirect
     */
    private function getFirstEnabledTab() : array
    {
        $enabledTabs = $this->getEnabledTabs();
        return reset($enabledTabs);
    }
}
