<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\ValueObject\DateTime;
use Doctrine\DBAL\Connection;

class UserInvitationService
{
    private const int TOKEN_LENGTH = 64;
    private const int EXPIRATION_HOURS = 72; // 3 days

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    /**
     * Generate a secure invitation token for a user
     */
    public function createInvitation(int $userId) : string
    {
        // Generate cryptographically secure random token
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));

        // Hash token for secure storage (defense against database compromise)
        $tokenHash = hash('sha256', $token);

        // Calculate expiration (3 days from now)
        $expiresAt = DateTime::create()->modify('+' . self::EXPIRATION_HOURS . ' hours');

        // Store HASHED token in database (not plaintext)
        $this->dbConnection->insert('user_invitation', [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => (string)$expiresAt,
            'created_at' => (string)DateTime::create(),
        ]);

        // Return plaintext token (only time it's available)
        return $token;
    }

    /**
     * Validate an invitation token and return the user ID if valid
     *
     * @return int|null User ID if token is valid, null otherwise
     */
    public function validateToken(string $token) : ?int
    {
        // Hash the token to compare with stored hash
        $tokenHash = hash('sha256', $token);

        $invitation = $this->dbConnection->fetchAssociative(
            'SELECT user_id, expires_at, used_at FROM user_invitation WHERE token_hash = ?',
            [$tokenHash]
        );

        if ($invitation === false) {
            return null; // Token not found
        }

        // Check if already used
        if ($invitation['used_at'] !== null) {
            return null; // Token already used
        }

        // Check if expired (current time is after expiration time)
        $expiresAt = DateTime::createFromString($invitation['expires_at']);
        $now = DateTime::create();
        if ($now->isAfter($expiresAt)) {
            return null; // Token expired
        }

        return (int)$invitation['user_id'];
    }

    /**
     * Mark an invitation token as used
     */
    public function markTokenAsUsed(string $token) : void
    {
        // Hash the token to find the record
        $tokenHash = hash('sha256', $token);

        $this->dbConnection->update(
            'user_invitation',
            ['used_at' => (string)DateTime::create()],
            ['token_hash' => $tokenHash]
        );
    }

    /**
     * Delete expired invitation tokens (cleanup)
     */
    public function deleteExpiredTokens() : void
    {
        $this->dbConnection->executeStatement(
            'DELETE FROM user_invitation WHERE expires_at < ?',
            [(string)DateTime::create()]
        );
    }
}
