<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service;

use Movary\Service\EncryptionService;
use Movary\Service\SmtpPasswordCipher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SmtpPasswordCipher::class)]
class SmtpPasswordCipherTest extends TestCase
{
    private EncryptionService&MockObject $encryptionService;

    private SmtpPasswordCipher $subject;

    protected function setUp() : void
    {
        $this->encryptionService = $this->createMock(EncryptionService::class);
        $this->subject = new SmtpPasswordCipher($this->encryptionService);
    }

    public function testEncryptsAndDecryptsRoundTripWhenKeyConfigured() : void
    {
        $this->encryptionService->method('isEncryptionKeyConfigured')->willReturn(true);
        $this->encryptionService->method('encrypt')->with('s3cret')->willReturn([
            'encrypted' => 'CIPHER',
            'iv' => 'IVDATA',
        ]);

        $stored = $this->subject->encryptForStorage('s3cret');

        self::assertSame('enc:v1:IVDATA:CIPHER', $stored);

        $this->encryptionService->method('decrypt')->with('CIPHER', 'IVDATA')->willReturn('s3cret');

        self::assertSame('s3cret', $this->subject->decryptFromStorage($stored));
    }

    public function testStoresPlaintextWhenNoKeyConfigured() : void
    {
        $this->encryptionService->method('isEncryptionKeyConfigured')->willReturn(false);
        $this->encryptionService->expects(self::never())->method('encrypt');

        self::assertSame('s3cret', $this->subject->encryptForStorage('s3cret'));
    }

    public function testEmptyPasswordIsNeverEncrypted() : void
    {
        $this->encryptionService->method('isEncryptionKeyConfigured')->willReturn(true);
        $this->encryptionService->expects(self::never())->method('encrypt');

        self::assertSame('', $this->subject->encryptForStorage(''));
    }

    public function testDecryptReturnsLegacyPlaintextUnchanged() : void
    {
        $this->encryptionService->expects(self::never())->method('decrypt');

        self::assertSame('legacy-plaintext', $this->subject->decryptFromStorage('legacy-plaintext'));
    }

    public function testDecryptFallsBackToRawValueWhenDecryptionFails() : void
    {
        $this->encryptionService->method('decrypt')->willThrowException(new RuntimeException('key missing'));

        $enveloped = 'enc:v1:IVDATA:CIPHER';

        self::assertSame($enveloped, $this->subject->decryptFromStorage($enveloped));
    }
}
