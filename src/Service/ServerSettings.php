<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;
use Movary\ValueObject\Config;
use Movary\ValueObject\Exception\ConfigNotSetException;

class ServerSettings
{
    private const string APPLICATION_TIMEZONE = 'TIMEZONE';

    private const string TOTP_ISSUER = 'TOTP_ISSUER';

    private const string JELLYFIN_DEVICE_ID = 'JELLYFIN_DEVICE_ID';

    private const string PLEX_APP_NAME = 'PLEX_APP_NAME';

    private const string JELLYFIN_APP_NAME = 'JELLYFIN_APP_NAME';

    private const string PLEX_IDENTIFIER = 'PLEX_IDENTIFIER';

    private const string APPLICATION_NAME = 'APPLICATION_NAME';

    private const string APPLICATION_URL = 'APPLICATION_URL';

    private const string APPLICATION_VERSION = 'APPLICATION_VERSION';

    private const string SMTP_HOST = 'SMTP_HOST';

    private const string SMTP_SENDER_ADDRESS = 'SMTP_SENDER_ADDRESS';

    private const string SMTP_PASSWORD = 'SMTP_PASSWORD';

    private const string SMTP_PORT = 'SMTP_PORT';

    private const string SMTP_USER = 'SMTP_USER';

    private const string SMTP_FROM_ADDRESS = 'SMTP_FROM_ADDRESS';

    private const string SMTP_FROM_DISPLAY_NAME = 'SMTP_FROM_DISPLAY_NAME';

    private const string SMTP_ENCRYPTION = 'SMTP_ENCRYPTION';

    private const string SMTP_WITH_AUTH = 'SMTP_WITH_AUTH';

    private const string EMAIL_AUTH_MODE = 'EMAIL_AUTH_MODE';

    private const string TMDB_API_KEY = 'TMDB_API_KEY';

    public function __construct(
        private readonly Config $config,
        private readonly Connection $dbConnection,
    ) {
    }

    public function getApplicationName() : ?string
    {
        return $this->getByKey(self::APPLICATION_NAME);
    }

    public function getApplicationTimezone() : ?string
    {
        return $this->getByKey(self::APPLICATION_TIMEZONE);
    }

    public function getApplicationUrl() : ?string
    {
        return $this->getByKey(self::APPLICATION_URL);
    }

    public function getApplicationVersion() : string
    {
        return $this->getByKey(self::APPLICATION_VERSION) ?? 'unknown';
    }

    public function getFromAddress() : ?string
    {
        return $this->getByKey(self::SMTP_FROM_ADDRESS);
    }

    public function getFromDisplayName() : ?string
    {
        return $this->getByKey(self::SMTP_FROM_DISPLAY_NAME);
    }

    public function getJellyfinAppName() : string
    {
        return $this->getByKey(self::JELLYFIN_APP_NAME) ?? 'Pathary';
    }

    public function getJellyfinDeviceId() : ?string
    {
        return $this->getByKey(self::JELLYFIN_DEVICE_ID);
    }

    public function getPlexAppName() : string
    {
        return $this->getByKey(self::PLEX_APP_NAME) ?? 'Pathary';
    }

    public function getPlexIdentifier() : ?string
    {
        return $this->getByKey(self::PLEX_IDENTIFIER);
    }

    public function getSmtpEncryption() : ?string
    {
        return $this->getByKey(self::SMTP_ENCRYPTION);
    }

    public function getSmtpHost() : ?string
    {
        return $this->getByKey(self::SMTP_HOST);
    }

    public function getSmtpPassword() : ?string
    {
        return $this->getByKey(self::SMTP_PASSWORD);
    }

    public function getSmtpPort() : ?int
    {
        return (int)$this->getByKey(self::SMTP_PORT);
    }

    public function getSmtpSenderAddress() : ?string
    {
        return $this->getByKey(self::SMTP_SENDER_ADDRESS);
    }

    public function getSmtpUser() : ?string
    {
        return $this->getByKey(self::SMTP_USER);
    }

    public function getSmtpWithAuthentication() : ?bool
    {
        return (bool)$this->getByKey(self::SMTP_WITH_AUTH);
    }

    public function getEmailAuthMode() : string
    {
        return $this->getByKey(self::EMAIL_AUTH_MODE) ?? 'smtp_password';
    }

    public function getTmdbApiKey() : ?string
    {
        return (string)$this->getByKey(self::TMDB_API_KEY);
    }

    public function getTotpIssuer() : string
    {
        return $this->getByKey(self::TOTP_ISSUER) ?? 'Pathary';
    }

    public function isApplicationNameSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::APPLICATION_NAME);
    }

    public function isApplicationTimezoneSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::APPLICATION_TIMEZONE);
    }

    public function isApplicationUrlSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::APPLICATION_URL);
    }

    public function isSmtpEncryptionSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_ENCRYPTION);
    }

    public function isSmtpFromAddressSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_FROM_ADDRESS);
    }

    public function isSmtpHostSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_HOST);
    }

    public function isSmtpPasswordSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_PASSWORD);
    }

    public function isSmtpPortSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_PORT);
    }

    public function isSmtpUserSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_USER);
    }

    public function isSmtpWithAuthenticationSetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::SMTP_WITH_AUTH);
    }

    public function isTmdbApiKeySetInEnvironment() : bool
    {
        return $this->isSetInEnvironment(self::TMDB_API_KEY);
    }

    public function requireApplicationUrl() : string
    {
        $value = $this->getByKey(self::APPLICATION_URL, true);
        if ($value === null) {
            throw ConfigNotSetException::create(self::APPLICATION_URL);
        }

        return $value;
    }

    public function requireJellyfinDeviceId() : ?string
    {
        $value = $this->getByKey(self::JELLYFIN_DEVICE_ID, true);
        if ($value === null) {
            throw ConfigNotSetException::create(self::JELLYFIN_DEVICE_ID);
        }

        return $value;
    }

    public function requirePlexIdentifier() : string
    {
        $value = $this->getByKey(self::PLEX_IDENTIFIER, true);
        if ($value === null) {
            throw ConfigNotSetException::create(self::PLEX_IDENTIFIER);
        }

        return $value;
    }

    public function setApplicationName(string $applicationName) : void
    {
        $this->updateValue(self::APPLICATION_NAME, $applicationName);
    }

    public function setApplicationTimezone(string $applicationTimezone) : void
    {
        $this->updateValue(self::APPLICATION_TIMEZONE, $applicationTimezone);
    }

    public function setApplicationUrl(string $applicationUrl) : void
    {
        $this->updateValue(self::APPLICATION_URL, $applicationUrl);
    }

    public function setSmtpEncryption(string $smtpEncryption) : void
    {
        if ($smtpEncryption === '') {
            $smtpEncryption = null;
        }

        $this->updateValue(self::SMTP_ENCRYPTION, $smtpEncryption);
    }

    public function setSmtpFromAddress(string $smtpFromAddress) : void
    {
        $this->updateValue(self::SMTP_FROM_ADDRESS, $smtpFromAddress);
    }

    public function setSmtpFromDisplayName(string $displayName) : void
    {
        // Sanitize to prevent header injection attacks
        $sanitized = $this->sanitizeDisplayName($displayName);
        $this->updateValue(self::SMTP_FROM_DISPLAY_NAME, $sanitized);
    }

    public function setSmtpFromWithAuthentication(bool $smtpFromWithAuthentication) : void
    {
        $this->updateValue(self::SMTP_WITH_AUTH, $smtpFromWithAuthentication);
    }

    public function setSmtpHost(string $smtpHost) : void
    {
        $this->updateValue(self::SMTP_HOST, $smtpHost);
    }

    public function setSmtpPassword(string $smtpPassword) : void
    {
        $this->updateValue(self::SMTP_PASSWORD, $smtpPassword);
    }

    public function setSmtpPort(int $smtpPort) : void
    {
        $this->updateValue(self::SMTP_PORT, $smtpPort);
    }

    public function setSmtpUser(string $smtpUser) : void
    {
        $this->updateValue(self::SMTP_USER, $smtpUser);
    }

    public function setEmailAuthMode(string $emailAuthMode) : void
    {
        if (!in_array($emailAuthMode, ['smtp_password', 'smtp_oauth'], true)) {
            throw new \InvalidArgumentException("Invalid email auth mode: {$emailAuthMode}. Must be 'smtp_password' or 'smtp_oauth'.");
        }

        $this->updateValue(self::EMAIL_AUTH_MODE, $emailAuthMode);
    }

    public function setTmdbApiKey(string $tmdbApiKey) : void
    {
        $this->updateValue(self::TMDB_API_KEY, $tmdbApiKey);
    }

    public function saveTmdbApiKeyWithMetadata(string $tmdbApiKey, ?int $userId = null) : void
    {
        // Save the key using existing method
        $this->setTmdbApiKey($tmdbApiKey);

        // Update metadata
        $dbKey = $this->convertEnvironmentKeyToDatabaseKey(self::TMDB_API_KEY);
        $now = date('Y-m-d H:i:s');

        // Delete existing metadata
        $this->dbConnection->prepare('DELETE FROM `server_setting_metadata` WHERE `key` = ?')
            ->executeStatement([$dbKey]);

        // Insert new metadata
        $this->dbConnection->prepare(
            'INSERT INTO `server_setting_metadata` (`key`, `updated_at`, `updated_by_user_id`) VALUES (?, ?, ?)'
        )->executeStatement([$dbKey, $now, $userId]);
    }

    public function getTmdbApiKeyMetadata() : ?array
    {
        $dbKey = $this->convertEnvironmentKeyToDatabaseKey(self::TMDB_API_KEY);

        $result = $this->dbConnection->fetchAssociative(
            'SELECT updated_at, updated_by_user_id FROM `server_setting_metadata` WHERE `key` = ?',
            [$dbKey]
        );

        return $result !== false ? $result : null;
    }

    public function isTmdbApiKeyConfigured() : bool
    {
        $key = $this->getTmdbApiKey();
        return $key !== null && $key !== '';
    }

    private function convertEnvironmentKeyToDatabaseKey(string $environmentKey) : string
    {
        return lcfirst(str_replace('_', '', ucwords(strtolower($environmentKey), '_')));
    }

    private function fetchValueFromDatabase(string $environmentKey) : ?string
    {
        $value = $this->dbConnection->fetchFirstColumn(
            'SELECT value FROM `server_setting` WHERE `key` = ?',
            [$this->convertEnvironmentKeyToDatabaseKey($environmentKey)],
        );

        return isset($value[0]) === false ? null : (string)$value[0];
    }

    private function getByKey(string $key, bool $required = false) : ?string
    {
        try {
            $value = $this->config->getAsString($key);
        } catch (ConfigNotSetException $e) {
            $value = $this->fetchValueFromDatabase($key);

            if (empty($value) === true && $required === true) {
                throw $e;
            }
        }

        return (string)$value === '' ? null : (string)$value;
    }

    private function isSetInEnvironment(string $key) : bool
    {
        try {
            $this->config->getAsString($key);
        } catch (ConfigNotSetException) {
            return false;
        }

        return true;
    }

    private function sanitizeDisplayName(string $name) : string
    {
        // Trim whitespace
        $name = trim($name);

        // Remove control characters (including CR, LF, TAB) to prevent header injection
        $sanitized = preg_replace('/[\r\n\t\x00-\x1F\x7F]/', '', $name);
        if ($sanitized === null) {
            // preg_replace failed, return empty string
            return '';
        }

        // Limit length to 100 characters
        if (strlen($sanitized) > 100) {
            $sanitized = substr($sanitized, 0, 100);
        }

        return $sanitized;
    }

    private function updateValue(string $environmentKey, mixed $value) : void
    {
        $key = $this->convertEnvironmentKeyToDatabaseKey($environmentKey);

        $this->dbConnection->prepare('DELETE FROM `server_setting` WHERE `key` = ?')->executeStatement([$key]);
        $this->dbConnection->prepare('INSERT INTO `server_setting` (value, `key`) VALUES (?, ?)')->executeStatement([(string)$value, $key]);
    }
}
