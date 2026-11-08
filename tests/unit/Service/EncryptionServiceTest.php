<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service;

use Doctrine\DBAL\Connection;
use Movary\Service\EncryptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(EncryptionService::class)]
class EncryptionServiceTest extends TestCase
{
    // Test-only key material, never used outside this test.
    private const string ENV_VAR = 'ENCRYPTION_KEY';

    private const string KEY_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    private Connection&Stub $dbConnection;

    private string|false $previousEnvKey;

    protected function setUp() : void
    {
        $this->previousEnvKey = getenv(self::ENV_VAR);
        $this->dbConnection = $this->createStub(Connection::class);
    }

    protected function tearDown() : void
    {
        $this->setEnvKey($this->previousEnvKey === false ? null : $this->previousEnvKey);
    }

    public function testEncryptWritesVersionedCiphertextAndRoundTrips() : void
    {
        $subject = $this->createSubjectWithEnvKey(base64_encode((string)hex2bin(self::KEY_HEX)));

        $encrypted = $subject->encrypt('refresh-token-value');

        self::assertStringStartsWith('v2:', $encrypted['encrypted']);
        self::assertSame(12, strlen((string)base64_decode($encrypted['iv'], true)));
        self::assertSame('refresh-token-value', $subject->decrypt($encrypted['encrypted'], $encrypted['iv']));
    }

    public function testEncryptUsesAFreshNoncePerCall() : void
    {
        $subject = $this->createSubjectWithEnvKey(self::KEY_HEX);

        self::assertNotSame($subject->encrypt('same')['encrypted'], $subject->encrypt('same')['encrypted']);
    }

    public function testDecryptsLegacyCbcCiphertext() : void
    {
        $key = (string)hex2bin(self::KEY_HEX);
        $iv = str_repeat("\x01", 16);
        // Exactly what the previous implementation stored.
        $legacy = base64_encode((string)openssl_encrypt('client-secret', 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));

        $subject = $this->createSubjectWithEnvKey(base64_encode($key));

        self::assertSame('client-secret', $subject->decrypt($legacy, base64_encode($iv)));
    }

    public function testRejectsTamperedV2Ciphertext() : void
    {
        $subject = $this->createSubjectWithEnvKey(self::KEY_HEX);
        $encrypted = $subject->encrypt('secret');

        $raw = (string)base64_decode(substr($encrypted['encrypted'], 3), true);
        $raw[0] = chr(ord($raw[0]) ^ 1);

        $this->expectException(RuntimeException::class);
        $subject->decrypt('v2:' . base64_encode($raw), $encrypted['iv']);
    }

    public function testHexAndBase64EncodingsOfTheSameKeyAreInterchangeable() : void
    {
        $encrypted = $this->createSubjectWithEnvKey(self::KEY_HEX)->encrypt('secret');

        $subject = $this->createSubjectWithEnvKey(base64_encode((string)hex2bin(self::KEY_HEX)));

        self::assertSame('secret', $subject->decrypt($encrypted['encrypted'], $encrypted['iv']));
    }

    public function testValidateKeyAcceptsOnly32ByteKeys() : void
    {
        $subject = new EncryptionService($this->dbConnection, new NullLogger());

        self::assertTrue($subject->validateKey(self::KEY_HEX));
        self::assertTrue($subject->validateKey(base64_encode(str_repeat('k', 32))));
        self::assertFalse($subject->validateKey(base64_encode(str_repeat('k', 16))));
        self::assertFalse($subject->validateKey(substr(self::KEY_HEX, 2)));
        self::assertFalse($subject->validateKey('not a key'));
    }

    public function testInvalidEnvironmentKeyIsRejected() : void
    {
        $subject = $this->createSubjectWithEnvKey('too-short');

        $this->expectException(RuntimeException::class);
        $subject->encrypt('secret');
    }

    public function testGenerateRefusesWhenAKeyIsStoredInTheDatabase() : void
    {
        $this->setEnvKey(null);
        $dbConnection = $this->createMock(Connection::class);
        $dbConnection->method('fetchFirstColumn')->willReturn([base64_encode(str_repeat('k', 32))]);
        $dbConnection->expects(self::never())->method('prepare');

        $subject = new EncryptionService($dbConnection, new NullLogger());

        $this->expectException(RuntimeException::class);
        $subject->generateAndStoreKey();
    }

    public function testGenerateRefusesWhenAKeyIsSetInTheEnvironment() : void
    {
        $this->setEnvKey(self::KEY_HEX);
        $dbConnection = $this->createMock(Connection::class);
        $dbConnection->expects(self::never())->method('prepare');

        $subject = new EncryptionService($dbConnection, new NullLogger());

        $this->expectException(RuntimeException::class);
        $subject->generateAndStoreKey();
    }

    public function testDatabaseKeyFallbackWorksAndLogsAWarning() : void
    {
        $this->setEnvKey(null);
        $this->dbConnection->method('fetchFirstColumn')->willReturn([base64_encode((string)hex2bin(self::KEY_HEX))]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $subject = new EncryptionService($this->dbConnection, $logger);
        $encrypted = $subject->encrypt('secret');

        self::assertSame('secret', $subject->decrypt($encrypted['encrypted'], $encrypted['iv']));
        self::assertSame('database', $subject->getEncryptionKeySource());
    }

    private function createSubjectWithEnvKey(string $encodedKey) : EncryptionService
    {
        $this->setEnvKey($encodedKey);

        return new EncryptionService($this->dbConnection, new NullLogger());
    }

    /** Sets or, with null, unsets the encryption key environment variable. */
    private function setEnvKey(?string $value) : void
    {
        putenv($value === null ? self::ENV_VAR : self::ENV_VAR . '=' . $value);
    }
}
