<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Domain\User;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Movary\Domain\User\UserRepository;
use Movary\ValueObject\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserRepository::class)]
class UserRepositoryAuthTokenTest extends TestCase
{
    private Connection $connection;

    private UserRepository $subject;

    protected function setUp() : void
    {
        // Schema after the DropPlaintextAuthToken migration: only the hash is stored.
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE user_auth_token (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash VARCHAR(64) NULL,
                expiration_date DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL,
                last_used_at DATETIME NULL,
                device_name VARCHAR(256) NOT NULL,
                user_agent TEXT NOT NULL
            )',
        );

        $this->subject = new UserRepository($this->connection);
    }

    public function testTokenIsStoredOnlyAsHash() : void
    {
        $this->createToken(1, 'secret-token');

        $storedRow = $this->connection->fetchAssociative('SELECT * FROM user_auth_token');

        self::assertIsArray($storedRow);
        self::assertSame(hash('sha256', 'secret-token'), $storedRow['token_hash']);
        self::assertNotContains('secret-token', $storedRow);
    }

    public function testTokenIsFoundByItsPlaintextValue() : void
    {
        $this->createToken(7, 'secret-token');

        self::assertSame(7, $this->subject->findUserIdByAuthToken('secret-token'));
        self::assertNotNull($this->subject->findAuthTokenExpirationDate('secret-token'));
        self::assertNull($this->subject->findUserIdByAuthToken('other-token'));
    }

    public function testHashValueItselfIsNotAValidToken() : void
    {
        $this->createToken(7, 'secret-token');

        // Someone who reads the hash from the database must not be able to replay it.
        self::assertNull($this->subject->findUserIdByAuthToken(hash('sha256', 'secret-token')));
    }

    public function testDeleteAuthTokenRemovesOnlyThatToken() : void
    {
        $this->createToken(7, 'token-a');
        $this->createToken(7, 'token-b');

        $this->subject->deleteAuthToken('token-a');

        self::assertNull($this->subject->findUserIdByAuthToken('token-a'));
        self::assertSame(7, $this->subject->findUserIdByAuthToken('token-b'));
    }

    public function testRevokingSessionsKeepsTheCurrentOneAndOtherUsers() : void
    {
        $this->createToken(7, 'current-session');
        $this->createToken(7, 'stolen-session');
        $this->createToken(8, 'other-user-session');

        $this->subject->deleteAuthTokensOfUser(7, 'current-session');

        self::assertSame(7, $this->subject->findUserIdByAuthToken('current-session'));
        self::assertNull($this->subject->findUserIdByAuthToken('stolen-session'));
        self::assertSame(8, $this->subject->findUserIdByAuthToken('other-user-session'));
    }

    public function testRevokingWithoutCurrentSessionRemovesAllOfThatUser() : void
    {
        $this->createToken(7, 'session-a');
        $this->createToken(7, 'session-b');
        $this->createToken(8, 'other-user-session');

        // E.g. an admin resetting another user's password: the admin's own token is not the user's.
        $this->subject->deleteAuthTokensOfUser(7);

        self::assertNull($this->subject->findUserIdByAuthToken('session-a'));
        self::assertNull($this->subject->findUserIdByAuthToken('session-b'));
        self::assertSame(8, $this->subject->findUserIdByAuthToken('other-user-session'));
    }

    private function createToken(int $userId, string $token) : void
    {
        $this->subject->createAuthToken($userId, $token, 'Pathary Web', 'phpunit', DateTime::createFromString('2099-01-01 00:00:00'));
    }
}
