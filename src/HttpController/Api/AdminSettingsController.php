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
        $isSetInEnvironment = $this->serverSettings->isTmdbApiKeySetInEnvironment();
        $metadata = $this->serverSettings->getTmdbApiKeyMetadata();

        $response = [
            'configured' => $isConfigured,
            'is_environment' => $isSetInEnvironment,
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

    /**
     * GET /api/admin/settings/omdb - Get OMDb API key status
     */
    public function getOmdbStatus(Request $request) : Response
    {
        $isConfigured = $this->serverSettings->isOmdbApiKeyConfigured();
        $isSetInEnvironment = $this->serverSettings->isOmdbApiKeySetInEnvironment();
        $metadata = $this->serverSettings->getOmdbApiKeyMetadata();

        $response = [
            'configured' => $isConfigured,
            'is_environment' => $isSetInEnvironment,
            'updated_at' => $metadata['updated_at'] ?? null,
        ];

        return Response::create(
            StatusCode::createOk(),
            Json::encode($response),
            [Header::createContentTypeJson()],
        );
    }

    /**
     * POST /api/admin/settings/omdb - Save OMDb API key
     * Note: CSRF protection not needed - Bearer token auth already prevents CSRF attacks
     */
    public function saveOmdbApiKey(Request $request) : Response
    {
        $requestBody = Json::decode($request->getBody());

        // Validate API key format (OMDb API keys are 8 alphanumeric characters)
        $apiKey = trim($requestBody['apiKey'] ?? '');
        if ($apiKey === '') {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'API key cannot be empty']),
                [Header::createContentTypeJson()],
            );
        }

        if (!preg_match('/^[a-zA-Z0-9]{8}$/i', $apiKey)) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid API key format. Expected 8 alphanumeric characters.']),
                [Header::createContentTypeJson()],
            );
        }

        // Save key with metadata
        $currentUser = $this->authenticationService->getCurrentUser();
        $userId = $currentUser?->getId();

        try {
            $this->serverSettings->saveOmdbApiKeyWithMetadata($apiKey, $userId);

            $this->logger->info('OMDb API key updated by admin', [
                'user_id' => $userId,
                // Do not log the key itself
            ]);

            $metadata = $this->serverSettings->getOmdbApiKeyMetadata();

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'OMDb API key saved successfully',
                    'updated_at' => $metadata['updated_at'] ?? null,
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to save OMDb API key', [
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
     * POST /api/admin/settings/omdb/test - Test OMDb API connection
     * Note: CSRF protection not needed - Bearer token auth already prevents CSRF attacks
     */
    public function testOmdbConnection(Request $request) : Response
    {
        // Get API key
        $apiKey = $this->serverSettings->getOmdbApiKey();
        if ($apiKey === null || $apiKey === '') {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode([
                    'success' => false,
                    'message' => 'OMDb API key not configured',
                ]),
                [Header::createContentTypeJson()],
            );
        }

        // Test connection by calling OMDb API with a simple query
        // Using tt0111161 (The Shawshank Redemption) as a test movie
        $startTime = microtime(true);

        try {
            $url = 'https://www.omdbapi.com/?apikey=' . urlencode($apiKey) . '&i=tt0111161';
            $request = new \GuzzleHttp\Psr7\Request('GET', $url);

            $response = $this->httpClient->sendRequest($request);
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $body = Json::decode((string)$response->getBody());

                // Check if OMDb returned an error
                if (isset($body['Response']) && $body['Response'] === 'False') {
                    $errorMessage = $body['Error'] ?? 'Unknown error';
                    if (str_contains($errorMessage, 'Invalid API key')) {
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

                    return Response::create(
                        StatusCode::createOk(),
                        Json::encode([
                            'success' => false,
                            'message' => $errorMessage,
                            'status_code' => $statusCode,
                            'latency_ms' => $latencyMs,
                        ]),
                        [Header::createContentTypeJson()],
                    );
                }

                // Success - API returned valid data
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
                        'message' => 'Unauthorized - Invalid API key',
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
                    'message' => 'OMDb API returned error code: ' . $statusCode,
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                ]),
                [Header::createContentTypeJson()],
            );

        } catch (\Exception $e) {
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            $this->logger->warning('OMDb API test failed', [
                'error' => $e->getMessage(),
                // Do not log the API key
            ]);

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => false,
                    'message' => 'Network error: Unable to connect to OMDb API',
                    'latency_ms' => $latencyMs,
                ]),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * DELETE /api/admin/settings/tmdb - Delete TMDB API key
     * Note: CSRF protection not needed - Bearer token auth already prevents CSRF attacks
     */
    public function deleteTmdbApiKey(Request $request) : Response
    {
        // Check if key is set in environment file (cannot be deleted)
        if ($this->serverSettings->isTmdbApiKeySetInEnvironment()) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode([
                    'success' => false,
                    'error' => 'Cannot delete TMDB API key configured via environment file',
                ]),
                [Header::createContentTypeJson()],
            );
        }

        $currentUser = $this->authenticationService->getCurrentUser();
        $userId = $currentUser?->getId();

        try {
            $this->serverSettings->deleteTmdbApiKey();

            $this->logger->info('TMDB API key deleted by admin', [
                'user_id' => $userId,
            ]);

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'TMDB API key deleted successfully',
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete TMDB API key', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to delete API key']),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * DELETE /api/admin/settings/omdb - Delete OMDb API key
     * Note: CSRF protection not needed - Bearer token auth already prevents CSRF attacks
     */
    public function deleteOmdbApiKey(Request $request) : Response
    {
        // Check if key is set in environment file (cannot be deleted)
        if ($this->serverSettings->isOmdbApiKeySetInEnvironment()) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode([
                    'success' => false,
                    'error' => 'Cannot delete OMDb API key configured via environment file',
                ]),
                [Header::createContentTypeJson()],
            );
        }

        $currentUser = $this->authenticationService->getCurrentUser();
        $userId = $currentUser?->getId();

        try {
            $this->serverSettings->deleteOmdbApiKey();

            $this->logger->info('OMDb API key deleted by admin', [
                'user_id' => $userId,
            ]);

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'success' => true,
                    'message' => 'OMDb API key deleted successfully',
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete OMDb API key', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to delete API key']),
                [Header::createContentTypeJson()],
            );
        }
    }
}
