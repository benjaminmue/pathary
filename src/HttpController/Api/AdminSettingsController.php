<?php declare(strict_types=1);

namespace Movary\HttpController\Api;

use Movary\Domain\User\Service\Authentication;
use Psr\Http\Client\ClientInterface;
use Movary\Service\ServerSettings;
use Movary\Util\Json;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Psr\Log\LoggerInterface;

class AdminSettingsController
{
    public function __construct(
        private readonly ServerSettings $serverSettings,
        private readonly Authentication $authenticationService,
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * GET /api/admin/settings/tmdb - Get TMDB API key status
     */
    public function getTmdbStatus(Request $request) : Response
    {
        $isConfigured = $this->serverSettings->isTmdbApiKeyConfigured();
        $metadata = $this->serverSettings->getTmdbApiKeyMetadata();

        $response = [
            'configured' => $isConfigured,
            'updated_at' => $metadata['updated_at'] ?? null,
        ];

        return Response::create(
            StatusCode::createOk(),
            Json::encode($response),
            [Header::createContentTypeJson()],
        );
    }

    /**
     * POST /api/admin/settings/tmdb - Save TMDB API key
     * Note: CSRF protection not needed - Bearer token auth already prevents CSRF attacks
     */
    public function saveTmdbApiKey(Request $request) : Response
    {
        $requestBody = Json::decode($request->getBody());

        // Validate API key format (TMDB v3 API keys are 32 hexadecimal characters)
        $apiKey = trim($requestBody['apiKey'] ?? '');
        if ($apiKey === '') {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'API key cannot be empty']),
                [Header::createContentTypeJson()],
            );
        }

        if (!preg_match('/^[a-f0-9]{32}$/i', $apiKey)) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid API key format. Expected 32 hexadecimal characters.']),
                [Header::createContentTypeJson()],
            );
        }

        // Save key with metadata
        $currentUser = $this->authenticationService->getCurrentUser();
        $userId = $currentUser?->getId();

        try {
            $this->serverSettings->saveTmdbApiKeyWithMetadata($apiKey, $userId);

            // Note: Key is stored in plaintext in database (consistent with other sensitive settings like SMTP password)
            // Security relies on: admin-only access, CSRF protection, and never exposing the key in responses
            $this->logger->info('TMDB API key updated by admin', [
                'user_id' => $userId,
                // Do not log the key itself
            ]);

            $metadata = $this->serverSettings->getTmdbApiKeyMetadata();

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'TMDB API key saved successfully',
                    'updated_at' => $metadata['updated_at'] ?? null,
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to save TMDB API key', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to save API key']),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * POST /api/admin/settings/tmdb/test - Test TMDB API connection
     * Note: CSRF protection not needed - Bearer token auth already prevents CSRF attacks
     */
    public function testTmdbConnection(Request $request) : Response
    {
        // Get API key
        $apiKey = $this->serverSettings->getTmdbApiKey();
        if ($apiKey === null || $apiKey === '') {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode([
                    'success' => false,
                    'message' => 'TMDB API key not configured',
                ]),
                [Header::createContentTypeJson()],
            );
        }

        // Test connection by calling a simple TMDB endpoint
        // Using /configuration endpoint which returns 200 on valid auth
        $startTime = microtime(true);

        try {
            $url = 'https://api.themoviedb.org/3/configuration?api_key=' . urlencode($apiKey);
            $request = new \GuzzleHttp\Psr7\Request('GET', $url);

            $response = $this->httpClient->sendRequest($request);
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                return Response::create(
                    StatusCode::createOk(),
                    Json::encode([
                        'success' => true,
                        'message' => 'Connection successful',
                        'status_code' => $statusCode,
                        'latency_ms' => $latencyMs,
                    ]),
                    [Header::createContentTypeJson()],
                );
            }

            if ($statusCode === 401) {
                return Response::create(
                    StatusCode::createOk(),
                    Json::encode([
                        'success' => false,
                        'message' => 'Invalid API key',
                        'status_code' => $statusCode,
                        'latency_ms' => $latencyMs,
                    ]),
                    [Header::createContentTypeJson()],
                );
            }

            if ($statusCode === 429) {
                return Response::create(
                    StatusCode::createOk(),
                    Json::encode([
                        'success' => false,
                        'message' => 'Rate limit exceeded. Please try again later.',
                        'status_code' => $statusCode,
                        'latency_ms' => $latencyMs,
                    ]),
                    [Header::createContentTypeJson()],
                );
            }

            // Other error codes
            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => false,
                    'message' => 'TMDB API returned error code: ' . $statusCode,
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (\Exception $e) {
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            $this->logger->warning('TMDB API test failed', [
                'error' => $e->getMessage(),
                // Do not log the API key
            ]);

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => false,
                    'message' => 'Network error: Unable to connect to TMDB API',
                    'latency_ms' => $latencyMs,
                ]),
                [Header::createContentTypeJson()],
            );
        }
    }
}
