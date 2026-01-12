<?php declare(strict_types=1);

namespace Movary\Api\Omdb;

use GuzzleHttp\Psr7\Request;
use Movary\Api\Omdb\Exception\OmdbAuthorizationError;
use Movary\Api\Omdb\Exception\OmdbResourceNotFound;
use Movary\Service\ServerSettings;
use Movary\Util\Json;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

class OmdbClient
{
    private const string BASE_URL = 'https://www.omdbapi.com/';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly ServerSettings $serverSettingsService,
    ) {
    }

    public function get(array $getParameters = []) : array
    {
        $url = $this->buildUrl($getParameters);

        $request = new Request('GET', $url);

        $response = $this->httpClient->sendRequest($request);

        $statusCode = $response->getStatusCode();

        match (true) {
            $statusCode === 401 => throw OmdbAuthorizationError::create(),
            $statusCode === 404 => throw OmdbResourceNotFound::create($url),
            $statusCode !== 200 => throw new RuntimeException('OMDb API error. Response status code: ' . $statusCode),
            default => true
        };

        $responseData = Json::decode((string)$response->getBody());

        // OMDb returns {"Response":"False","Error":"..."} on errors
        if (isset($responseData['Response']) && $responseData['Response'] === 'False') {
            if (isset($responseData['Error']) && str_contains($responseData['Error'], 'Invalid API key')) {
                throw OmdbAuthorizationError::create();
            }
            if (isset($responseData['Error']) && str_contains($responseData['Error'], 'not found')) {
                throw OmdbResourceNotFound::create($url);
            }
            throw new RuntimeException('OMDb API error: ' . ($responseData['Error'] ?? 'Unknown error'));
        }

        return $responseData;
    }

    private function buildUrl(array $getParameters) : string
    {
        $getParametersRendered = '?';

        foreach ($getParameters as $name => $getParameter) {
            $getParametersRendered .= $name . '=' . urlencode((string)$getParameter) . '&';
        }

        $apiKey = $this->serverSettingsService->getOmdbApiKey();
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('OMDb API key not configured');
        }

        $getParametersRendered .= 'apikey=' . $apiKey;

        return self::BASE_URL . $getParametersRendered;
    }
}
