<?php declare(strict_types=1);

use Movary\Factory;

require_once(__DIR__ . '/vendor/autoload.php');

$builder = new DI\ContainerBuilder();
$builder->addDefinitions(
    [
        \Movary\ValueObject\Config::class => DI\factory([Factory::class, 'createConfig']),
        \Movary\Service\CsrfTokenService::class => DI\factory([Factory::class, 'createCsrfTokenService']),
        \Movary\Api\Trakt\TraktApi::class => DI\factory([Factory::class, 'createTraktApi']),
        \Movary\Service\ImageCacheService::class => DI\factory([Factory::class, 'createImageCacheService']),
        \Movary\JobQueue\JobQueueScheduler::class => DI\factory([Factory::class, 'createJobQueueScheduler']),
        \Movary\Api\Tmdb\TmdbClient::class => DI\factory([Factory::class, 'createTmdbApiClient']),
        \Movary\Service\ImageUrlService::class => DI\factory([Factory::class, 'createUrlGenerator']),
        \Movary\Service\Export\ExportService::class => DI\factory([Factory::class, 'createExportService']),
        \Movary\HttpController\Api\OpenApiController::class => DI\factory([Factory::class, 'createOpenApiController']),
        \Movary\HttpController\Web\JobController::class => DI\factory([Factory::class, 'createJobController']),
        \Movary\HttpController\Web\LandingPageController::class => DI\factory([Factory::class, 'createLandingPageController']),
        \Movary\HttpController\Web\Middleware\ServerHasRegistrationEnabled::class => DI\factory([Factory::class, 'createMiddlewareServerHasRegistrationEnabled']),
        \Movary\ValueObject\Http\Request::class => DI\factory([Factory::class, 'createCurrentHttpRequest']),
        \Movary\Command\CreatePublicStorageLink::class => DI\factory([Factory::class, 'createCreatePublicStorageLink']),
        \Movary\Command\DatabaseMigrationStatus::class => DI\factory([Factory::class, 'createDatabaseMigrationStatusCommand']),
        \Movary\Command\DatabaseMigrationMigrate::class => DI\factory([Factory::class, 'createDatabaseMigrationMigrateCommand']),
        \Movary\Command\DatabaseMigrationRollback::class => DI\factory([Factory::class, 'createDatabaseMigrationRollbackCommand']),
        \Movary\Command\ProcessJobs::class => DI\factory([Factory::class, 'createProcessJobCommand']),
        \Psr\Http\Client\ClientInterface::class => DI\factory([Factory::class, 'createHttpClient']),
        \Psr\Log\LoggerInterface::class => DI\factory([Factory::class, 'createLogger']),
        \Doctrine\DBAL\Connection::class => DI\factory([Factory::class, 'createDbConnection']),
        \Twig\Loader\LoaderInterface::class => DI\factory([Factory::class, 'createTwigFilesystemLoader']),
        \Twig\Environment::class => DI\factory([Factory::class, 'createTwigEnvironment']),
        \Monolog\Formatter\LineFormatter::class => DI\factory([Factory::class, 'createLineFormatter']),
    ],
);

$container = $builder->build();

$timezone = $container->get(\Movary\ValueObject\Config::class)->getAsString('TIMEZONE', \Movary\ValueObject\DateTime::DEFAULT_TIME_ZONE);
/** @psalm-suppress ArgumentTypeCoercion */
date_default_timezone_set($timezone);

// Define validation function if not already defined
if (!function_exists('validateApplicationUrl')) {
    /**
     * Validate APPLICATION_URL configuration to prevent misconfiguration
     *
     * @throws RuntimeException If APPLICATION_URL is invalid
     */
    function validateApplicationUrl(string $url): void
    {
    // Must start with http:// or https://
    if (!preg_match('/^https?:\/\//i', $url)) {
        throw new RuntimeException(
            'APPLICATION_URL must start with http:// or https://. ' .
            'Current value: ' . $url
        );
    }

    // Must not end with slash
    if (str_ends_with($url, '/')) {
        throw new RuntimeException(
            'APPLICATION_URL must not end with a slash. ' .
            'Current value: ' . $url
        );
    }

    // Must be valid URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException(
            'APPLICATION_URL is not a valid URL. ' .
            'Current value: ' . $url
        );
    }

    // Must not contain path segments (strict check for clean base URLs)
    $parsed = parse_url($url);
    if (!empty($parsed['path']) && $parsed['path'] !== '/') {
        throw new RuntimeException(
            'APPLICATION_URL should be domain only, without path segments. ' .
            'Current value: ' . $url . ' ' .
            'Example: http://localhost or https://pathary.example.com'
        );
    }

    // Must not contain query string or fragment
    if (!empty($parsed['query']) || !empty($parsed['fragment'])) {
        throw new RuntimeException(
            'APPLICATION_URL must not contain query parameters or fragments. ' .
            'Current value: ' . $url
        );
    }
    }
}

// Validate APPLICATION_URL configuration if set
$applicationUrl = getenv('APPLICATION_URL');
if ($applicationUrl !== false && $applicationUrl !== '') {
    validateApplicationUrl($applicationUrl);
}

return $container;
