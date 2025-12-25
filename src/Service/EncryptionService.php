<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;
use Movary\ValueObject\Exception\ConfigNotSetException;
use RuntimeException;

/**
 * Encryption service for securing sensitive data (OAuth tokens, client secrets)
 *
 * Uses AES-256-CBC with random IVs for maximum security.
 * Key management supports environment variable (production) or database fallback (development).
 *
 * @security CRITICAL - Handles sensitive OAuth credentials
 */
class EncryptionService
{
    private const string CIPHER_ALGORITHM = 'AES-256-CBC';
    private const int KEY_LENGTH = 32; // 256 bits
    private const int IV_LENGTH = 16;  // 128 bits for AES
    private const string DB_KEY_NAME = 'encryptionKey';
    private const string ENV_KEY_NAME = 'ENCRYPTION_KEY';

    private ?string $encryptionKey = null;

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    /**
     * Encrypt a plaintext value using AES-256-CBC
     *
     * @param string $plaintext The value to encrypt
     * @return array{encrypted: string, iv: string} Base64-encoded encrypted value and IV
     * @throws RuntimeException If encryption fails or key is missing
     */
    public function encrypt(string $plaintext) : array
    {
        if (trim($plaintext) === '') {
            throw new RuntimeException('Cannot encrypt empty string');
        }

        $key = $this->getEncryptionKey();
        $iv = $this->generateIV();

        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'encrypted' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
        ];
    }

    /**
     * Decrypt a previously encrypted value using AES-256-CBC
     *
     * @param string $encryptedBase64 Base64-encoded encrypted value
     * @param string $ivBase64 Base64-encoded initialization vector
     * @return string Decrypted plaintext
     * @throws RuntimeException If decryption fails or key is missing
     */
    public function decrypt(string $encryptedBase64, string $ivBase64) : string
    {
        if (trim($encryptedBase64) === '' || trim($ivBase64) === '') {
            throw new RuntimeException('Cannot decrypt: encrypted value or IV is empty');
        }

        $key = $this->getEncryptionKey();
        $encrypted = base64_decode($encryptedBase64, true);
        $iv = base64_decode($ivBase64, true);

        if ($encrypted === false || $iv === false) {
            throw new RuntimeException('Invalid base64 encoding in encrypted data or IV');
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Check if encryption key is configured
     *
     * @return bool True if key exists in environment or database
     */
    public function isEncryptionKeyConfigured() : bool
    {
        try {
            $this->getEncryptionKey();
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get the encryption key source for audit/debugging
     *
     * @return string 'environment' or 'database' or 'not_configured'
     */
    public function getEncryptionKeySource() : string
    {
        if ($this->isKeyInEnvironment()) {
            return 'environment';
        }

        if ($this->isKeyInDatabase()) {
            return 'database';
        }

        return 'not_configured';
    }

    /**
     * Generate and store a new encryption key in database (development only)
     *
     * @return string The generated key (base64 encoded)
     * @throws RuntimeException If key already exists in environment (safety check)
     */
    public function generateAndStoreKey() : string
    {
        if ($this->isKeyInEnvironment()) {
            throw new RuntimeException(
                'Encryption key already set in environment. ' .
                'Cannot generate new key - this would invalidate existing encrypted data.'
            );
        }

        $key = $this->generateSecureKey();
        $this->storeKeyInDatabase($key);

        // Clear cached key to force reload
        $this->encryptionKey = null;

        return base64_encode($key);
    }

    /**
     * Validate encryption key format and strength
     *
     * @param string $keyBase64 Base64-encoded encryption key
     * @return bool True if valid
     */
    public function validateKey(string $keyBase64) : bool
    {
        $key = base64_decode($keyBase64, true);

        if ($key === false) {
            return false;
        }

        if (strlen($key) !== self::KEY_LENGTH) {
            return false;
        }

        return true;
    }

    /**
     * Get the encryption key (from environment or database)
     * Caches the key in memory after first retrieval
     *
     * @return string Binary encryption key (32 bytes)
     * @throws RuntimeException If key not found or invalid
     */
    private function getEncryptionKey() : string
    {
        if ($this->encryptionKey !== null) {
            return $this->encryptionKey;
        }

        // Try environment first (production)
        $keyBase64 = getenv(self::ENV_KEY_NAME);
        if ($keyBase64 !== false && trim($keyBase64) !== '') {
            $key = base64_decode($keyBase64, true);

            if ($key === false || strlen($key) !== self::KEY_LENGTH) {
                throw new RuntimeException(
                    'Invalid encryption key in environment. ' .
                    'Key must be 32 bytes (256 bits) base64-encoded. ' .
                    'Generate with: openssl rand -base64 32'
                );
            }

            $this->encryptionKey = $key;
            return $key;
        }

        // Fallback to database (development)
        $keyFromDb = $this->fetchKeyFromDatabase();
        if ($keyFromDb !== null) {
            $key = base64_decode($keyFromDb, true);

            if ($key === false || strlen($key) !== self::KEY_LENGTH) {
                throw new RuntimeException('Corrupted encryption key in database');
            }

            $this->encryptionKey = $key;
            return $key;
        }

        throw new RuntimeException(
            'Encryption key not configured. ' .
            'Set ENCRYPTION_KEY environment variable or generate a key via admin panel. ' .
            'Generate with: openssl rand -base64 32'
        );
    }

    /**
     * Check if encryption key exists in environment
     */
    private function isKeyInEnvironment() : bool
    {
        $keyBase64 = getenv(self::ENV_KEY_NAME);
        return $keyBase64 !== false && trim($keyBase64) !== '';
    }

    /**
     * Check if encryption key exists in database
     */
    private function isKeyInDatabase() : bool
    {
        return $this->fetchKeyFromDatabase() !== null;
    }

    /**
     * Fetch encryption key from database
     *
     * @return string|null Base64-encoded key or null if not found
     */
    private function fetchKeyFromDatabase() : ?string
    {
        $value = $this->dbConnection->fetchFirstColumn(
            'SELECT value FROM `server_setting` WHERE `key` = ?',
            [self::DB_KEY_NAME],
        );

        return isset($value[0]) && trim((string)$value[0]) !== '' ? (string)$value[0] : null;
    }

    /**
     * Store encryption key in database
     *
     * @param string $key Binary encryption key (32 bytes)
     */
    private function storeKeyInDatabase(string $key) : void
    {
        $keyBase64 = base64_encode($key);

        $this->dbConnection->prepare('DELETE FROM `server_setting` WHERE `key` = ?')
            ->executeStatement([self::DB_KEY_NAME]);

        $this->dbConnection->prepare('INSERT INTO `server_setting` (value, `key`) VALUES (?, ?)')
            ->executeStatement([$keyBase64, self::DB_KEY_NAME]);
    }

    /**
     * Generate a cryptographically secure encryption key
     *
     * @return string Binary key (32 bytes)
     * @throws RuntimeException If random_bytes fails
     */
    private function generateSecureKey() : string
    {
        try {
            return random_bytes(self::KEY_LENGTH);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate secure random key: ' . $e->getMessage());
        }
    }

    /**
     * Generate a cryptographically secure initialization vector
     *
     * @return string Binary IV (16 bytes)
     * @throws RuntimeException If random_bytes fails
     */
    private function generateIV() : string
    {
        try {
            return random_bytes(self::IV_LENGTH);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate secure random IV: ' . $e->getMessage());
        }
    }
}
