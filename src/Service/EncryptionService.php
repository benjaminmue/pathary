<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Encryption service for securing sensitive data (OAuth tokens, client secrets)
 *
 * New values are encrypted with AES-256-GCM (authenticated: tampering is
 * detected on decrypt) and marked with a "v2:" prefix on the ciphertext.
 * Values written before that used AES-256-CBC without a MAC; they carry no
 * prefix and are still decrypted, and get re-encrypted as v2 whenever they are
 * saved again (e.g. reconnecting OAuth).
 *
 * The key comes from ENCRYPTION_KEY (preferred) or, for installs that used
 * "Generate Encryption Key" in the admin panel, from server_setting. A key in
 * the database sits next to the data it protects, so using it logs a warning;
 * move it to ENCRYPTION_KEY (docs/security/encryption-key.md).
 *
 * @security CRITICAL - Handles sensitive OAuth credentials
 */
class EncryptionService
{
    private const string LEGACY_CIPHER_ALGORITHM = 'aes-256-cbc';
    private const string CIPHER_ALGORITHM = 'aes-256-gcm';
    private const string CIPHERTEXT_V2_PREFIX = 'v2:';
    private const int KEY_LENGTH = 32; // 256 bits
    private const int NONCE_LENGTH = 12; // 96 bits, the GCM standard nonce size
    private const int TAG_LENGTH = 16;
    private const string DB_KEY_NAME = 'encryptionKey';
    private const string ENV_KEY_NAME = 'ENCRYPTION_KEY';

    private ?string $encryptionKey = null;

    public function __construct(
        private readonly Connection $dbConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Encrypt a plaintext value with AES-256-GCM
     *
     * @param string $plaintext The value to encrypt
     * @return array{encrypted: string, iv: string} "v2:"-prefixed base64 ciphertext+tag and base64 nonce
     * @throws RuntimeException If encryption fails or key is missing
     */
    public function encrypt(string $plaintext) : array
    {
        if (trim($plaintext) === '') {
            throw new RuntimeException('Cannot encrypt empty string');
        }

        $key = $this->getEncryptionKey();
        $nonce = $this->generateRandomBytes(self::NONCE_LENGTH);

        $tag = '';
        $encrypted = openssl_encrypt($plaintext, self::CIPHER_ALGORITHM, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LENGTH);
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'encrypted' => self::CIPHERTEXT_V2_PREFIX . base64_encode($encrypted . $tag),
            'iv' => base64_encode($nonce),
        ];
    }

    /**
     * Decrypt a value produced by encrypt(), current (v2, GCM) or legacy (CBC)
     *
     * @param string $encryptedBase64 Ciphertext as returned in "encrypted"
     * @param string $ivBase64 Nonce/IV as returned in "iv"
     * @return string Decrypted plaintext
     * @throws RuntimeException If decryption or authentication fails, or the key is missing
     */
    public function decrypt(string $encryptedBase64, string $ivBase64) : string
    {
        if (trim($encryptedBase64) === '' || trim($ivBase64) === '') {
            throw new RuntimeException('Cannot decrypt: encrypted value or IV is empty');
        }

        $isV2 = str_starts_with($encryptedBase64, self::CIPHERTEXT_V2_PREFIX);
        $encrypted = base64_decode($isV2 === true ? substr($encryptedBase64, strlen(self::CIPHERTEXT_V2_PREFIX)) : $encryptedBase64, true);
        $iv = base64_decode($ivBase64, true);

        if ($encrypted === false || $iv === false) {
            throw new RuntimeException('Invalid base64 encoding in encrypted data or IV');
        }

        $key = $this->getEncryptionKey();

        if ($isV2 === true) {
            if (strlen($encrypted) < self::TAG_LENGTH || strlen($iv) !== self::NONCE_LENGTH) {
                throw new RuntimeException('Decryption failed: malformed ciphertext');
            }

            $tag = substr($encrypted, -self::TAG_LENGTH);
            $ciphertext = substr($encrypted, 0, -self::TAG_LENGTH);
            $decrypted = openssl_decrypt($ciphertext, self::CIPHER_ALGORITHM, $key, OPENSSL_RAW_DATA, $iv, $tag);
        } else {
            $decrypted = openssl_decrypt($encrypted, self::LEGACY_CIPHER_ALGORITHM, $key, OPENSSL_RAW_DATA, $iv);
        }

        if ($decrypted === false) {
            // Deliberately vague: wrong key and tampered data look the same.
            throw new RuntimeException('Decryption failed: wrong key or corrupted data');
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
     * Generate and store a new encryption key in the database, for installs
     * without ENCRYPTION_KEY
     *
     * @return string The generated key (base64 encoded)
     * @throws RuntimeException If any key is already configured: replacing it
     *     would make every stored secret undecryptable
     */
    public function generateAndStoreKey() : string
    {
        if ($this->isKeyInEnvironment() === true || $this->isKeyInDatabase() === true) {
            throw new RuntimeException(
                'An encryption key is already configured. ' .
                'Generating a new key would make existing encrypted data unreadable; see docs/security/encryption-key.md to rotate it.'
            );
        }

        $key = $this->generateRandomBytes(self::KEY_LENGTH);
        $this->storeKeyInDatabase($key);

        // Clear cached key to force reload
        $this->encryptionKey = null;

        return base64_encode($key);
    }

    /**
     * Validate encryption key format and strength
     *
     * @param string $encodedKey 32 random bytes, base64 (openssl rand -base64 32) or hex (openssl rand -hex 32)
     */
    public function validateKey(string $encodedKey) : bool
    {
        return $this->decodeKey($encodedKey) !== null;
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

        $encodedKey = getenv(self::ENV_KEY_NAME);
        if ($encodedKey !== false && trim($encodedKey) !== '') {
            $key = $this->decodeKey($encodedKey);
            if ($key === null) {
                throw new RuntimeException(
                    'Invalid encryption key in environment. ' .
                    'ENCRYPTION_KEY must be 32 random bytes, base64 or hex encoded. ' .
                    'Generate with: openssl rand -base64 32'
                );
            }

            return $this->encryptionKey = $key;
        }

        $keyFromDb = $this->fetchKeyFromDatabase();
        if ($keyFromDb !== null) {
            $key = $this->decodeKey($keyFromDb);
            if ($key === null) {
                throw new RuntimeException('Corrupted encryption key in database');
            }

            $this->logger->warning(
                'Encryption key is read from the database, next to the data it protects. ' .
                'Copy it into the ENCRYPTION_KEY environment variable (docs/security/encryption-key.md).',
            );

            return $this->encryptionKey = $key;
        }

        throw new RuntimeException(
            'Encryption key not configured. ' .
            'Set ENCRYPTION_KEY environment variable or generate a key via admin panel. ' .
            'Generate with: openssl rand -base64 32'
        );
    }

    /**
     * Accepts hex first: a 64 character hex string is also valid base64, but
     * would decode to 48 bytes there and be rejected.
     *
     * @return string|null Binary key, null if the value is not 32 bytes in either encoding
     */
    private function decodeKey(string $encodedKey) : ?string
    {
        $encodedKey = trim($encodedKey);

        $key = preg_match('/^[0-9a-f]{64}$/i', $encodedKey) === 1 ? hex2bin($encodedKey) : base64_decode($encodedKey, true);

        return $key !== false && strlen($key) === self::KEY_LENGTH ? $key : null;
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
     * @param int<1, max> $length
     * @throws RuntimeException If random_bytes fails
     */
    private function generateRandomBytes(int $length) : string
    {
        try {
            return random_bytes($length);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate secure random bytes: ' . $e->getMessage());
        }
    }
}
