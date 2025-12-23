<?php declare(strict_types=1);

use Movary\HttpController\Api;
use Movary\HttpController\Web;
use Movary\HttpController\Web\RadarrController;
use Movary\Service\Router\Dto\RouteList;
use Movary\Service\Router\RouterService;

return function (FastRoute\RouteCollector $routeCollector) {
    $routerService = new RouterService();

    $routeCollector->addGroup('', fn($routeCollector) => addWebRoutes($routerService, $routeCollector));
    $routeCollector->addGroup('/api', fn($routeCollector) => addApiRoutes($routerService, $routeCollector));
};

function addWebRoutes(RouterService $routerService, FastRoute\RouteCollector $routeCollector) : void
{
    $routes = RouteList::create();

    ###############
    # Public Home #
    ###############
    $routes->add('GET', '/', [Web\PublicHomeController::class, 'index']);
    $routes->add('GET', '/movie/{id:[0-9]+}', [Web\PublicMovieController::class, 'detail']);
    $routes->add('POST', '/movie/{id:[0-9]+}/rate', [Web\RateMovieController::class, 'rate'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/movie/{id:[0-9]+}/rate/delete', [Web\RateMovieController::class, 'deleteRating'], [Web\Middleware\UserIsAuthenticated::class]);

    ##########
    # Search #
    ##########
    $routes->add('GET', '/search', [Web\SearchController::class, 'search'], [Web\Middleware\UserIsAuthenticated::class]);

    ##############
    # All Movies #
    ##############
    $routes->add('GET', '/movies', [Web\AllMoviesController::class, 'index'], [Web\Middleware\UserIsAuthenticated::class]);

    #######
    # Dev #
    #######
    $routes->add('GET', '/dev/popcorn', [Web\DevController::class, 'renderPopcornTestPage']);

    #########
    # Admin #
    #########
    $routes->add('GET', '/admin', [Web\AdminController::class, 'index'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/admin/movies', [Web\AdminController::class, 'renderMoviesTab'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/admin/users', [Web\AdminController::class, 'renderUsersTab'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/admin/server', [Web\AdminController::class, 'renderServerTab'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/admin/integrations', [Web\AdminController::class, 'renderIntegrationsTab'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    # Admin Health Checks
    $routes->add('GET', '/admin/health', [Web\HealthCheckController::class, 'getHealth'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/admin/health/run', [Web\HealthCheckController::class, 'runHealthCheck'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    ###########
    # Profile #
    ###########
    $routes->add('GET', '/profile', [Web\ProfileController::class, 'show'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile', [Web\ProfileController::class, 'update'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('GET', '/profile-images/{filename:.+}', [Web\ProfileController::class, 'serveImage']);

    #####################
    # Profile Security  #
    #####################
    $routes->add('GET', '/profile/security', [Web\ProfileSecurityController::class, 'show'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/password', [Web\ProfileSecurityController::class, 'changePassword'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/totp/enable', [Web\ProfileSecurityController::class, 'enableTotp'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/totp/verify', [Web\ProfileSecurityController::class, 'verifyAndSaveTotp'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/totp/disable', [Web\ProfileSecurityController::class, 'disableTotp'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/recovery-codes/regenerate', [Web\ProfileSecurityController::class, 'regenerateRecoveryCodes'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/trusted-devices/{deviceId:[0-9]+}/revoke', [Web\ProfileSecurityController::class, 'revokeTrustedDevice'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/profile/security/trusted-devices/revoke-all', [Web\ProfileSecurityController::class, 'revokeAllTrustedDevices'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('GET', '/profile/security/events', [Web\ProfileSecurityController::class, 'getSecurityEvents'], [Web\Middleware\UserIsAuthenticated::class]);

    ################
    # TMDB Movies  #
    ################
    $routes->add('GET', '/tmdb/movie/{tmdbId:[0-9]+}', [Web\TmdbMovieController::class, 'detail'], [Web\Middleware\UserIsAuthenticated::class]);
    $routes->add('POST', '/tmdb/movie/{tmdbId:[0-9]+}/add', [Web\TmdbMovieController::class, 'add'], [Web\Middleware\UserIsAuthenticated::class]);

    $routes->add('GET', '/landing', [Web\LandingPageController::class, 'render'], [Web\Middleware\UserIsUnauthenticated::class, Web\Middleware\ServerHasNoUsers::class]);
    $routes->add('GET', '/login', [Web\AuthenticationController::class, 'renderLoginPage'], [Web\Middleware\UserIsUnauthenticated::class]);
    $routes->add('POST', '/create-user', [Web\CreateUserController::class, 'createUser'], [
        Web\Middleware\UserIsUnauthenticated::class,
        Web\Middleware\ServerHasUsers::class,
        Web\Middleware\ServerHasRegistrationEnabled::class,
    ]);
    $routes->add('GET', '/create-user', [Web\CreateUserController::class, 'renderPage'], [
        Web\Middleware\UserIsUnauthenticated::class,
        Web\Middleware\ServerHasUsers::class,
        Web\Middleware\ServerHasRegistrationEnabled::class
    ]);
    $routes->add('GET', '/docs/api', [Web\OpenApiController::class, 'renderPage']);
    // placeholder image generator
    $routes->add('GET', '/images/placeholder/{imageNameBase64Encoded:.+}', [Web\PlaceholderImageController::class, 'renderPlaceholderImage']);

    #####################
    # Webhook listeners # !!! Deprecated use new api routes
    #####################
    $routes->add('POST', '/old/plex/{id:.+}', [Web\PlexController::class, 'handlePlexWebhook'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jellyfin/{id:.+}', [Web\JellyfinController::class, 'handleJellyfinWebhook'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/emby/{id:.+}', [Web\EmbyController::class, 'handleEmbyWebhook'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    #############
    # Job Queue #
    #############
    $routes->add('GET', '/old/jobs', [Web\JobController::class, 'getJobs'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/job-queue/purge-processed', [Web\JobController::class, 'purgeProcessedJobs'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/job-queue/purge-all', [Web\JobController::class, 'purgeAllJobs'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/trakt-history-sync', [Web\JobController::class, 'scheduleTraktHistorySync'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/trakt-ratings-sync', [Web\JobController::class, 'scheduleTraktRatingsSync'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/letterboxd-diary-sync', [Web\JobController::class, 'scheduleLetterboxdDiaryImport'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/letterboxd-ratings-sync', [Web\JobController::class, 'scheduleLetterboxdRatingsImport'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/plex-watchlist-sync', [Web\JobController::class, 'schedulePlexWatchlistImport'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/jellyfin-import-history', [Web\JobController::class, 'scheduleJellyfinImportHistory'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/jobs/schedule/jellyfin-export-history', [Web\JobController::class, 'scheduleJellyfinExportHistory'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserHasJellyfinToken::class,
        Web\Middleware\UserIsAdmin::class,
    ]);

    ############
    # Settings #
    ############
    $routes->add('GET', '/old/settings/account/general', [Web\SettingsController::class, 'renderGeneralAccountPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/account/general/api-token', [Web\SettingsController::class, 'getApiToken'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/account/general/api-token', [Web\SettingsController::class, 'deleteApiToken'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/account/general/api-token', [Web\SettingsController::class, 'regenerateApiToken'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/account/dashboard', [Web\SettingsController::class, 'renderDashboardAccountPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/account/locations', [Web\SettingsController::class, 'renderLocationsAccountPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/account/security', [Web\SettingsController::class, 'renderSecurityAccountPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/account/data', [Web\SettingsController::class, 'renderDataAccountPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/server/general', [Web\SettingsController::class, 'renderServerGeneralPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/server/jobs', [Web\SettingsController::class, 'renderServerJobsPage'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserIsAdmin::class
    ]);
    $routes->add('POST', '/old/settings/server/general', [Web\SettingsController::class, 'updateServerGeneral'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserIsAdmin::class
    ]);
    $routes->add('GET', '/old/settings/server/users', [Web\SettingsController::class, 'renderServerUsersPage'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserIsAdmin::class
    ]);
    $routes->add('GET', '/old/settings/server/email', [Web\SettingsController::class, 'renderServerEmailPage'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserIsAdmin::class
    ]);
    $routes->add('POST', '/old/settings/server/email', [Web\SettingsController::class, 'updateServerEmail'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserIsAdmin::class
    ]);
    $routes->add('POST', '/old/settings/server/email-test', [Web\SettingsController::class, 'sendTestEmail'], [
        Web\Middleware\UserIsAuthenticated::class,
        Web\Middleware\UserIsAdmin::class
    ]);
    $routes->add('POST', '/old/settings/account', [Web\SettingsController::class, 'updateGeneral'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/security/update-password', [Web\SettingsController::class, 'updatePassword'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/security/create-totp-uri', [
        Web\TwoFactorAuthenticationController::class,
        'createTotpUri'
    ], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/security/disable-totp', [Web\TwoFactorAuthenticationController::class, 'disableTotp'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/security/enable-totp', [Web\TwoFactorAuthenticationController::class, 'enableTotp'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/account/export/csv/{exportType:.+}', [Web\ExportController::class, 'getCsvExport'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/import/csv/{exportType:.+}', [Web\ImportController::class, 'handleCsvImport'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/account/delete-ratings', [Web\SettingsController::class, 'deleteRatings'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/account/delete-history', [Web\SettingsController::class, 'deleteHistory'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/account/delete-account', [Web\SettingsController::class, 'deleteAccount'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/update-dashboard-rows', [Web\SettingsController::class, 'updateDashboardRows'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/account/reset-dashboard-rows', [Web\SettingsController::class, 'resetDashboardRows'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/trakt', [Web\SettingsController::class, 'renderTraktPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/trakt', [Web\SettingsController::class, 'updateTrakt'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/trakt/verify-credentials', [Web\SettingsController::class, 'traktVerifyCredentials'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/letterboxd', [Web\SettingsController::class, 'renderLetterboxdPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/letterboxd-export', [Web\SettingsController::class, 'generateLetterboxdExportData'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/plex', [Web\SettingsController::class, 'renderPlexPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/plex/logout', [Web\PlexController::class, 'removePlexAccessTokens'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/plex/server-url-save', [Web\PlexController::class, 'savePlexServerUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/plex/server-url-verify', [Web\PlexController::class, 'verifyPlexServerUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/plex/authentication-url', [Web\PlexController::class, 'generatePlexAuthenticationUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/plex/callback', [Web\PlexController::class, 'processPlexCallback'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/plex', [Web\SettingsController::class, 'updatePlex'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/plex/webhook', [Web\PlexController::class, 'regeneratePlexWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/plex/webhook', [Web\PlexController::class, 'deletePlexWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/jellyfin', [Web\SettingsController::class, 'renderJellyfinPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/jellyfin', [Web\SettingsController::class, 'updateJellyfin'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/jellyfin/sync', [Web\JellyfinController::class, 'saveJellyfinSyncOptions'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/jellyfin/authenticate', [Web\JellyfinController::class, 'authenticateJellyfinAccount'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/jellyfin/remove-authentication', [Web\JellyfinController::class, 'removeJellyfinAuthentication'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/jellyfin/server-url-save', [Web\JellyfinController::class, 'saveJellyfinServerUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/jellyfin/server-url-verify', [Web\JellyfinController::class, 'verifyJellyfinServerUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/jellyfin/webhook', [Web\JellyfinController::class, 'getJellyfinWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/jellyfin/webhook', [Web\JellyfinController::class, 'regenerateJellyfinWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/jellyfin/webhook', [Web\JellyfinController::class, 'deleteJellyfinWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/emby', [Web\SettingsController::class, 'renderEmbyPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/emby', [Web\SettingsController::class, 'updateEmby'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/emby/webhook', [Web\EmbyController::class, 'regenerateEmbyWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/emby/webhook', [Web\EmbyController::class, 'deleteEmbyWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/kodi', [Web\SettingsController::class, 'renderKodiPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/kodi', [Web\SettingsController::class, 'updateKodi'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/kodi/webhook', [Web\KodiController::class, 'regenerateKodiWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/kodi/webhook', [Web\KodiController::class, 'deleteKodiWebhookUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/app', [Web\SettingsController::class, 'renderAppPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/netflix', [Web\SettingsController::class, 'renderNetflixPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/netflix', [Web\NetflixController::class, 'matchNetflixActivityCsvWithTmdbMovies'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/netflix/import', [Web\NetflixController::class, 'importNetflixData'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/integrations/mastodon', [Web\SettingsController::class, 'renderMastodonPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/integrations/mastodon', [Web\SettingsController::class, 'updateMastodon'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/users', [Web\UserController::class, 'fetchUsers'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/users', [Web\UserController::class, 'createUser'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/users/{userId:\d+}', [Web\UserController::class, 'updateUser'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/users/{userId:\d+}', [Web\UserController::class, 'deleteUser'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/locations', [Web\LocationController::class, 'fetchLocations'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/locations', [Web\LocationController::class, 'createLocation'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/locations/{locationId:\d+}', [Web\LocationController::class, 'updateLocation'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/locations/{locationId:\d+}', [Web\LocationController::class, 'deleteLocation'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/settings/locations/toggle-feature', [Web\LocationController::class, 'fetchToggleFeature'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/settings/locations/toggle-feature', [Web\LocationController::class, 'updateToggleFeature'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    $routes->add('GET', '/old/settings/integrations/radarr', [Web\SettingsController::class, 'renderRadarrPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('PUT', '/old/settings/radarr/feed', [RadarrController::class, 'regenerateRadarrFeedUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('DELETE', '/old/settings/radarr/feed', [RadarrController::class, 'deleteRadarrFeedUrl'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);


    ##########
    # Movies #
    ##########
    $routes->add('GET', '/old/movies/{id:[0-9]+}/refresh-tmdb', [Web\Movie\MovieController::class, 'refreshTmdbData'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/movies/{id:[0-9]+}/refresh-imdb', [Web\Movie\MovieController::class, 'refreshImdbRating'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/movies/{id:[0-9]+}/watch-providers', [Web\Movie\MovieWatchProviderController::class, 'getWatchProviders'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/movies/{id:[0-9]+}/add-watchlist', [Web\Movie\MovieWatchlistController::class, 'addToWatchlist'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/movies/{id:[0-9]+}/remove-watchlist', [Web\Movie\MovieWatchlistController::class, 'removeFromWatchlist'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    ##########
    # Person #
    ##########
    $routes->add('GET', '/old/persons/{id:[0-9]+}/refresh-tmdb', [Web\PersonController::class, 'refreshTmdbData'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/persons/{id:[0-9]+}/hide-in-top-lists', [Web\PersonController::class, 'hideInTopLists'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/persons/{id:[0-9]+}/show-in-top-lists', [Web\PersonController::class, 'showInTopLists'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    ##############
    # User media #
    ##############
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/dashboard', [Web\DashboardController::class, 'redirectToDashboard'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}', [Web\DashboardController::class, 'render'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/history', [Web\HistoryController::class, 'renderHistory'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/watchlist', [Web\WatchlistController::class, 'renderWatchlist'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/movies', [Web\MoviesController::class, 'renderPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/actors', [Web\ActorsController::class, 'renderPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/directors', [Web\DirectorsController::class, 'renderPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    // the following routes (/movies/ and /persons/) can have any non-slash characters following the URL after a -
    //   e.g., http://movary.test/users/alifeee/movies/14-freakier-friday which is identical to
    //         http://movary.test/users/alifeee/movies/14
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/movies/{id:\d+}[-{nameSlugSuffix:[^/]*}]', [Web\Movie\MovieController::class, 'renderPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class, Web\Middleware\MovieSlugRedirector::class]);
    $routes->add('GET', '/old/users/{username:[a-zA-Z0-9]+}/persons/{id:\d+}[-{nameSlugSuffix:[^/]*}]', [Web\PersonController::class, 'renderPage'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class, Web\Middleware\PersonSlugRedirector::class]);
    $routes->add('DELETE', '/old/users/{username:[a-zA-Z0-9]+}/movies/{id:\d+}/history', [
        Web\HistoryController::class,
        'deleteHistoryEntry'
    ], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/users/{username:[a-zA-Z0-9]+}/movies/{id:\d+}/history', [
        Web\HistoryController::class,
        'createHistoryEntry'
    ], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/users/{username:[a-zA-Z0-9]+}/movies/{id:\d+}/rating', [
        Web\Movie\MovieRatingController::class,
        'updateRating'
    ], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/log-movie', [Web\HistoryController::class, 'logMovie'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('POST', '/old/add-movie-to-watchlist', [Web\WatchlistController::class, 'addMovieToWatchlist'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);
    $routes->add('GET', '/old/fetchMovieRatingByTmdbdId', [Web\Movie\MovieRatingController::class, 'fetchMovieRatingByTmdbdId'], [Web\Middleware\UserIsAuthenticated::class, Web\Middleware\UserIsAdmin::class]);

    $routerService->addRoutesToRouteCollector($routeCollector, $routes, true);
}

function addApiRoutes(RouterService $routerService, FastRoute\RouteCollector $routeCollector) : void
{
    $routes = RouteList::create();

    $routes->add('GET', '/openapi', [Api\OpenApiController::class, 'getSchema']);
    $routes->add('POST', '/authentication/token', [Api\AuthenticationController::class, 'createToken']);
    $routes->add('DELETE', '/authentication/token', [Api\AuthenticationController::class, 'destroyToken']);
    $routes->add('GET', '/authentication/token', [Api\AuthenticationController::class, 'getTokenData']);

    $routeUserHistory = '/users/{username:[a-zA-Z0-9]+}/history/movies';
    $routes->add('GET', $routeUserHistory, [Api\HistoryController::class, 'getHistory'], [Api\Middleware\IsAuthorizedToReadUserData::class]);
    $routes->add('POST', $routeUserHistory, [Api\HistoryController::class, 'addToHistory'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);
    $routes->add('DELETE', $routeUserHistory, [Api\HistoryController::class, 'deleteFromHistory'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);
    $routes->add('PUT', $routeUserHistory, [Api\HistoryController::class, 'updateHistory'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);

    $routeUserWatchlist = '/users/{username:[a-zA-Z0-9]+}/watchlist/movies';
    $routes->add('GET', $routeUserWatchlist, [Api\WatchlistController::class, 'getWatchlist'], [Api\Middleware\IsAuthorizedToReadUserData::class]);
    $routes->add('POST', $routeUserWatchlist, [Api\WatchlistController::class, 'addToWatchlist'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);
    $routes->add('DELETE', $routeUserWatchlist, [Api\WatchlistController::class, 'deleteFromWatchlist'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);

    $routeUserPlayed = '/users/{username:[a-zA-Z0-9]+}/played/movies';
    $routes->add('GET', $routeUserPlayed, [Api\PlayedController::class, 'getPlayed'], [Api\Middleware\IsAuthorizedToReadUserData::class]);
    $routes->add('POST', $routeUserPlayed, [Api\PlayedController::class, 'addToPlayed'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);
    $routes->add('DELETE', $routeUserPlayed, [Api\PlayedController::class, 'deleteFromPlayed'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);
    $routes->add('PUT', $routeUserPlayed, [Api\PlayedController::class, 'updatePlayed'], [Api\Middleware\IsAuthorizedToWriteUserData::class]);

    $routes->add('POST', '/movies/add', [Api\MovieAddController::class, 'addMovie'], [Api\Middleware\IsAuthenticated::class]);
    $routes->add('GET', '/movies/search', [Api\MovieSearchController::class, 'search'], [Api\Middleware\IsAuthenticated::class]);

    $routes->add('POST', '/webhook/plex/{id:.+}', [Api\PlexController::class, 'handlePlexWebhook']);
    $routes->add('POST', '/webhook/jellyfin/{id:.+}', [Api\JellyfinController::class, 'handleJellyfinWebhook']);
    $routes->add('POST', '/webhook/emby/{id:.+}', [Api\EmbyController::class, 'handleEmbyWebhook']);
    $routes->add('POST', '/webhook/kodi/{id:.+}', [Api\KodiController::class, 'handleKodiWebhook']);

    $routes->add('GET', '/feed/radarr/{id:.+}', [Api\RadarrController::class, 'renderRadarrFeed']);

    // Admin Settings API (admin-only)
    $routes->add('GET', '/admin/settings/tmdb', [Api\AdminSettingsController::class, 'getTmdbStatus'], [Api\Middleware\IsAuthenticated::class, Api\Middleware\IsAdmin::class]);
    $routes->add('POST', '/admin/settings/tmdb', [Api\AdminSettingsController::class, 'saveTmdbApiKey'], [Api\Middleware\IsAuthenticated::class, Api\Middleware\IsAdmin::class]);
    $routes->add('POST', '/admin/settings/tmdb/test', [Api\AdminSettingsController::class, 'testTmdbConnection'], [Api\Middleware\IsAuthenticated::class, Api\Middleware\IsAdmin::class]);

    $routerService->addRoutesToRouteCollector($routeCollector, $routes);
}
