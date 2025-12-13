<?php declare(strict_types=1);

namespace Movary\ValueObject\Http;

use UnexpectedValueException;

class Request
{
    private array $routeParameters = [];

    private function __construct(
        private readonly string $path,
        private readonly array $getParameters,
        private readonly array $postParameters,
        private readonly string $body,
        private readonly array $filesParameters,
        private readonly array $headers,
        private readonly string $userAgent,
        private readonly ?string $httpHost,
        private readonly ?string $httpReferer,
    ) {
    }

    public static function createFromGlobals() : self
    {
        $uri = self::extractRequestUri();
        $path = self::extractPath($uri);

        $httpHost = self::getServerSetting('HTTP_HOST') ?? '';
        $httpReferer = self::getServerSetting('HTTP_REFERER') ?? '';

        $getParameters = self::extractGetParameter();
        $postParameters = self::extractPostParameter();
        $filesParameters = self::extractFilesParameter();
        $headers = self::extractHeaders();
        $userAgent = self::extractUserAgent();

        $body = (string)file_get_contents('php://input');

        return new self(
            $path,
            $getParameters,
            $postParameters,
            $body,
            $filesParameters,
            $headers,
            $userAgent,
            $httpHost,
            $httpReferer,
        );
    }

    private static function extractFilesParameter() : array
    {
        // phpcs:ignore MySource.PHP.GetRequestData
        return $_FILES;
    }

    private static function extractGetParameter() : array
    {
        $getParameters = filter_input_array(INPUT_GET) ?? [];

        if ($getParameters === false) {
            throw new UnexpectedValueException('Could not load GET parameters.');
        }

        return $getParameters;
    }

    private static function extractHeaders() : array
    {
        $headers = getallheaders();

        // Normalize header names to handle case-insensitivity
        // HTTP headers are case-insensitive per RFC 7230, but PHP array keys are not
        // This ensures headers work correctly behind reverse proxies that may alter casing
        $normalized = [];
        foreach ($headers as $name => $value) {
            // Store with original case
            $normalized[$name] = $value;
            // Also store common variations for known headers that may be case-altered by proxies
            if (strtolower($name) === 'x-movary-client') {
                $normalized['X-Movary-Client'] = $value;
            }
        }

        return $normalized;
    }

    private static function extractPath(string $uri) : string
    {
        if (strpos($uri, 'https://') === 0 || strpos($uri, 'http://') === 0) {
            $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        } else {
            $parsableUrl = sprintf('http://%s%s', 'fakehost', $uri);
            $path = parse_url($parsableUrl, PHP_URL_PATH) ?? '/';
        }

        if (false === $path) {
            return '/';
        }

        return $path;
    }

    private static function extractPostParameter() : array
    {
        $postParameters = filter_input_array(INPUT_POST) ?? [];

        if ($postParameters === false) {
            throw new UnexpectedValueException('Could not load POST parameters.');
        }

        return $postParameters;
    }

    private static function extractRequestUri() : string
    {
        return self::getServerSetting('REQUEST_URI') ?? '';
    }

    private static function extractUserAgent() : string
    {
        return self::getServerSetting('HTTP_USER_AGENT') ?? '';
    }

    private static function getServerSetting(string $key) : ?string
    {
        return empty($_SERVER[$key]) === false ? (string)$_SERVER[$key] : null;
    }

    public function addRouteParameters(array $routeParameters) : void
    {
        $this->routeParameters = array_merge($this->routeParameters, $routeParameters);
    }

    public function getBody() : string
    {
        return $this->body;
    }

    public function getFileParameters() : array
    {
        return $this->filesParameters;
    }

    public function getGetParameters() : array
    {
        return $this->getParameters;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function getHttpHost() : ?string
    {
        return $this->httpHost;
    }

    public function getHttpReferer() : ?string
    {
        return $this->httpReferer;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function getPostParameters() : array
    {
        return $this->postParameters;
    }

    public function getRouteParameters() : array
    {
        return $this->routeParameters;
    }

    public function getUserAgent() : string
    {
        return $this->userAgent;
    }
}
