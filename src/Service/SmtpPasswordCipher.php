<?php declare(strict_types=1);

namespace Movary\Service;

use RuntimeException;

/**
 * Encrypts the SMTP password for at-rest storage in the generic server_setting
 * value column, without a schema change.
 *
 * Encrypted values are wrapped in a self-describing envelope
 * ("enc:v1:<base64 iv>:<base64 ciphertext>") so that reads can tell an
 * encrypted value apart from a legacy plaintext one (or an env-provided
 * password, which is never encrypted). When no encryption key is configured the
 * password is stored as plaintext, matching the previous behaviour, so a
 * basic-SMTP setup without an encryption key keeps working.
 */
class SmtpPasswordCipher
{
    private const string ENVELOPE_PREFIX = 'enc:v1:';

    public function __construct(
        private readonly EncryptionService $encryptionService,
    ) {
    }

    public function encryptForStorage(string $plaintext) : string
    {
        // EncryptionService::encrypt() rejects an empty string, and without a
        // key we cannot encrypt at all - keep the previous plaintext behaviour.
        if ($plaintext === '' || $this->encryptionService->isEncryptionKeyConfigured() === false) {
            return $plaintext;
        }

        $encrypted = $this->encryptionService->encrypt($plaintext);

        return self::ENVELOPE_PREFIX . $encrypted['iv'] . ':' . $encrypted['encrypted'];
    }

    public function decryptFromStorage(string $storedValue) : string
    {
        // Legacy plaintext values and env-provided passwords carry no envelope.
        if (str_starts_with($storedValue, self::ENVELOPE_PREFIX) === false) {
            return $storedValue;
        }

        $parts = explode(':', substr($storedValue, strlen(self::ENVELOPE_PREFIX)), 2);
        if (count($parts) !== 2) {
            return $storedValue;
        }

        [$iv, $ciphertext] = $parts;

        try {
            return $this->encryptionService->decrypt($ciphertext, $iv);
        } catch (RuntimeException) {
            // Missing/rotated key or corrupted payload: return the raw stored
            // value rather than breaking the email flow with an exception.
            return $storedValue;
        }
    }
}
