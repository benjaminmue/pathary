<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\Domain\User\Repository\RecoveryCodeRepository;

class RecoveryCodeService
{
    private const RECOVERY_CODE_COUNT = 10;
    private const RECOVERY_CODE_LENGTH = 10;

    public function __construct(
        private readonly RecoveryCodeRepository $recoveryCodeRepository,
    ) {
    }

    public function generateRecoveryCodes(int $userId) : array
    {
        // Delete existing recovery codes
        $this->recoveryCodeRepository->deleteAllByUserId($userId);

        $codes = [];

        // Generate 10 new recovery codes
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = $this->generateRandomCode();
            // Normalize before hashing (removes dashes, uppercase)
            $normalizedCode = $this->normalizeCode($code);
            $codeHash = password_hash($normalizedCode, PASSWORD_DEFAULT);

            $this->recoveryCodeRepository->create($userId, $codeHash);

            // Return code with dashes for display
            $codes[] = $code;
        }

        return $codes;
    }

    public function verifyRecoveryCode(int $userId, string $code) : bool
    {
        // Normalize input code (trim, remove dashes/spaces, uppercase)
        $normalizedCode = $this->normalizeCode($code);

        $recoveryCodes = $this->recoveryCodeRepository->findUnusedByUserId($userId);

        foreach ($recoveryCodes as $recoveryCode) {
            $codeHash = $recoveryCode['code_hash'];

            // Try normalized version first (NEW codes - without dashes)
            if (password_verify($normalizedCode, $codeHash) === true) {
                $this->recoveryCodeRepository->markAsUsed((int)$recoveryCode['id']);
                return true;
            }

            // Backward compatibility: Try legacy format with dashes (OLD codes)
            // Format: XXXX-XXXX-XX (10 chars + 2 dashes)
            if (strlen($normalizedCode) === 10) {
                $legacyFormat = substr($normalizedCode, 0, 4) . '-' . substr($normalizedCode, 4, 4) . '-' . substr($normalizedCode, 8, 2);
                if (password_verify($legacyFormat, $codeHash) === true) {
                    $this->recoveryCodeRepository->markAsUsed((int)$recoveryCode['id']);
                    return true;
                }
            }
        }

        return false;
    }

    public function getRemainingCodeCount(int $userId) : int
    {
        return $this->recoveryCodeRepository->countUnusedByUserId($userId);
    }

    public function hasRecoveryCodes(int $userId) : bool
    {
        return $this->getRemainingCodeCount($userId) > 0;
    }

    private function generateRandomCode() : string
    {
        // Generate random code in format XXXX-XXXX-XX (example: "XN3J-8MLH-5C")
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing characters (0, O, 1, I)
        $code = '';

        for ($i = 0; $i < self::RECOVERY_CODE_LENGTH; $i++) {
            if ($i > 0 && $i % 4 === 0) {
                $code .= '-';
            }
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }

    public function deleteAllRecoveryCodes(int $userId) : void
    {
        $this->recoveryCodeRepository->deleteAllByUserId($userId);
    }

    /**
     * Normalize recovery code for consistent hashing and verification
     * - Trim whitespace
     * - Remove dashes and spaces
     * - Convert to uppercase
     */
    private function normalizeCode(string $code) : string
    {
        $code = trim($code);
        $code = str_replace(['-', ' '], '', $code);
        $code = strtoupper($code);

        return $code;
    }
}
