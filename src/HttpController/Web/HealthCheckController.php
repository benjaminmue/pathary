<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Doctrine\DBAL\Connection;
use Movary\Service\ServerSettings;
use Movary\Util\Json;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

class HealthCheckController
{
    private const int CACHE_DURATION_SECONDS = 60;
    private const int RATE_LIMIT_SECONDS = 10;
    private const int DB_HEALTHY_THRESHOLD_MS = 250;
    private const string CACHE_KEY = 'health_check_cache';
    private const string LAST_RUN_KEY = 'health_check_last_run';

    public function __construct(
        private readonly Connection $dbConnection,
        private readonly ServerSettings $serverSettings,
        private readonly ClientInterface $httpClient,
        private readonly SessionWrapper $sessionWrapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * GET /admin/health - Get cached health status
     */
    public function getHealth(Request $request) : Response
    {
        $cachedData = $this->getCachedHealth();

        if ($cachedData === null) {
            // No cached data, return unknown status with enabled flags
            $cachedData = [
                'database' => $this->createUnknownStatus('Database', true),
                'tmdb' => $this->createUnknownStatus('TMDB API', $this->isTmdbEnabled()),
                'integration1' => $this->createUnknownStatus('Integration 1', false),
                'integration2' => $this->createUnknownStatus('Integration 2', false),
                'overall' => ['status' => 'unknown'],
            ];
        }

        return Response::create(
            StatusCode::createOk(),
            Json::encode($cachedData),
            [Header::createContentTypeJson()],
        );
    }

    /**
     * POST /admin/health/run - Run health checks and return results
     */
    public function runHealthCheck(Request $request) : Response
    {
        // Rate limiting: prevent too frequent health checks
        $lastRun = $this->sessionWrapper->find(self::LAST_RUN_KEY);
        if ($lastRun !== null && (time() - (int)$lastRun) < self::RATE_LIMIT_SECONDS) {
            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'error' => 'Please wait before running another health check',
                    'retry_after' => self::RATE_LIMIT_SECONDS - (time() - (int)$lastRun),
                ]),
                [Header::createContentTypeJson()],
            );
        }

        // Run health checks
        $dbHealth = $this->checkDatabaseHealth();
        $tmdbHealth = $this->checkTmdbHealth();

        // Determine overall status
        $overallStatus = $this->determineOverallStatus($dbHealth['status'], $tmdbHealth['status']);

        $results = [
            'database' => $dbHealth,
            'tmdb' => $tmdbHealth,
            'integration1' => $this->createUnknownStatus('Integration 1', false),
            'integration2' => $this->createUnknownStatus('Integration 2', false),
            'overall' => ['status' => $overallStatus],
        ];

        // Cache results
        $this->cacheHealth($results);
        $this->sessionWrapper->set(self::LAST_RUN_KEY, (string)time());

        return Response::create(
            StatusCode::createOk(),
            Json::encode($results),
            [Header::createContentTypeJson()],
        );
    }

    /**
     * POST /admin/health/db - Run database health check only
     */
    public function runDatabaseCheck(Request $request) : Response
    {
        $dbHealth = $this->checkDatabaseHealth();

        // Update cache with this result
        $cachedData = $this->getCachedHealth() ?? [];
        $cachedData['database'] = $dbHealth;
        $this->cacheHealth($cachedData);

        return Response::create(
            StatusCode::createOk(),
            Json::encode(['database' => $dbHealth]),
            [Header::createContentTypeJson()],
        );
    }

    /**
     * POST /admin/health/tmdb - Run TMDB health check only
     */
    public function runTmdbCheck(Request $request) : Response
    {
        $tmdbHealth = $this->checkTmdbHealth();

        // Update cache with this result
        $cachedData = $this->getCachedHealth() ?? [];
        $cachedData['tmdb'] = $tmdbHealth;
        $this->cacheHealth($cachedData);

        return Response::create(
            StatusCode::createOk(),
            Json::encode(['tmdb' => $tmdbHealth]),
            [Header::createContentTypeJson()],
        );
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabaseHealth() : array
    {
        $startTime = microtime(true);

        try {
            // Perform simple query
            $this->dbConnection->executeQuery('SELECT 1');
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            // Determine status based on latency
            if ($latencyMs < self::DB_HEALTHY_THRESHOLD_MS) {
                return [
                    'enabled' => true,
                    'status' => 'healthy',
                    'message' => 'Query OK',
                    'latency_ms' => $latencyMs,
                    'checked_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                return [
                    'enabled' => true,
                    'status' => 'degraded',
                    'message' => 'Query OK (slow)',
                    'latency_ms' => $latencyMs,
                    'checked_at' => date('Y-m-d H:i:s'),
                ];
            }
        } catch (\Exception $e) {
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            $this->logger->error('Database health check failed', [
                'error' => $e->getMessage(),
                // Do not log sensitive connection details
            ]);

            return [
                'enabled' => true,
                'status' => 'down',
                'message' => 'Database unreachable',
                'latency_ms' => $latencyMs,
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Check if TMDB is enabled (API key configured)
     */
    private function isTmdbEnabled() : bool
    {
        $apiKey = $this->serverSettings->getTmdbApiKey();
        return $apiKey !== null && $apiKey !== '';
    }

    /**
     * Check TMDB API connectivity and authentication
     */
    private function checkTmdbHealth() : array
    {
        $apiKey = $this->serverSettings->getTmdbApiKey();

        // Check if API key is configured
        if ($apiKey === null || $apiKey === '') {
            return [
                'enabled' => false,
                'status' => 'down',
                'message' => 'TMDB key not configured',
                'latency_ms' => 0,
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }

        $startTime = microtime(true);

        try {
            $url = 'https://api.themoviedb.org/3/configuration?api_key=' . urlencode($apiKey);
            $request = new \GuzzleHttp\Psr7\Request('GET', $url);

            $response = $this->httpClient->sendRequest($request);
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                return [
                    'enabled' => true,
                    'status' => 'healthy',
                    'message' => 'TMDB reachable',
                    'latency_ms' => $latencyMs,
                    'checked_at' => date('Y-m-d H:i:s'),
                ];
            }

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'enabled' => true,
                    'status' => 'down',
                    'message' => 'TMDB auth failed',
                    'latency_ms' => $latencyMs,
                    'checked_at' => date('Y-m-d H:i:s'),
                ];
            }

            if ($statusCode === 429) {
                return [
                    'enabled' => true,
                    'status' => 'degraded',
                    'message' => 'TMDB rate limited',
                    'latency_ms' => $latencyMs,
                    'checked_at' => date('Y-m-d H:i:s'),
                ];
            }

            // 5xx or other errors
            return [
                'enabled' => true,
                'status' => 'degraded',
                'message' => 'TMDB error',
                'latency_ms' => $latencyMs,
                'checked_at' => date('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            $this->logger->warning('TMDB health check failed', [
                'error' => $e->getMessage(),
                // Do not log the API key
            ]);

            // Check if it's a timeout
            if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed out')) {
                return [
                    'enabled' => true,
                    'status' => 'down',
                    'message' => 'TMDB timeout',
                    'latency_ms' => $latencyMs,
                    'checked_at' => date('Y-m-d H:i:s'),
                ];
            }

            return [
                'enabled' => true,
                'status' => 'down',
                'message' => 'TMDB unreachable',
                'latency_ms' => $latencyMs,
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Determine overall system status from individual checks
     */
    private function determineOverallStatus(string $dbStatus, string $tmdbStatus) : string
    {
        // If either critical service is down, overall is down
        if ($dbStatus === 'down') {
            return 'down';
        }

        // If any service is degraded, overall is degraded
        if ($dbStatus === 'degraded' || $tmdbStatus === 'degraded') {
            return 'degraded';
        }

        // If TMDB is down but DB is healthy, overall is degraded (not critical)
        if ($tmdbStatus === 'down') {
            return 'degraded';
        }

        // Both healthy
        if ($dbStatus === 'healthy' && $tmdbStatus === 'healthy') {
            return 'healthy';
        }

        return 'unknown';
    }

    /**
     * Get cached health status
     */
    private function getCachedHealth() : ?array
    {
        $cached = $this->sessionWrapper->find(self::CACHE_KEY);

        if ($cached === null) {
            return null;
        }

        $data = Json::decode($cached);

        // Check if cache is still valid
        if (isset($data['cached_at']) && (time() - $data['cached_at']) < self::CACHE_DURATION_SECONDS) {
            unset($data['cached_at']); // Remove internal timestamp
            return $data;
        }

        return null;
    }

    /**
     * Cache health status
     */
    private function cacheHealth(array $data) : void
    {
        $data['cached_at'] = time();
        $this->sessionWrapper->set(self::CACHE_KEY, Json::encode($data));
    }

    /**
     * Create unknown status response
     */
    private function createUnknownStatus(string $service, bool $enabled) : array
    {
        return [
            'enabled' => $enabled,
            'status' => 'unknown',
            'message' => 'Not checked yet',
            'latency_ms' => 0,
            'checked_at' => null,
        ];
    }
}
