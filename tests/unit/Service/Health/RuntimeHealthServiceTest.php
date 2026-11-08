<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service\Health;

use Doctrine\DBAL\Connection;
use Movary\Service\Health\RuntimeHealthService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RuntimeHealthService::class)]
class RuntimeHealthServiceTest extends TestCase
{
    private string $directory;

    protected function setUp() : void
    {
        $this->directory = sys_get_temp_dir() . '/pathary-health-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/migrations', 0777, true);
        mkdir($this->directory . '/storage');

        touch($this->directory . '/migrations/20260101000000_first.php');
        touch($this->directory . '/migrations/20260202000000_second.php');
        touch($this->directory . '/migrations/README.md');
    }

    protected function tearDown() : void
    {
        array_map('unlink', glob($this->directory . '/migrations/*') ?: []);
        rmdir($this->directory . '/migrations');
        rmdir($this->directory . '/storage');
        rmdir($this->directory);
    }

    public function testReportsMigrationFilesMissingFromPhinxlog() : void
    {
        $subject = $this->createSubject(['20260101000000']);

        self::assertSame(['20260202000000'], $subject->findPendingMigrationVersions());
        self::assertFalse($subject->areMigrationsUpToDate());
    }

    public function testMigrationsAreUpToDateWhenAllVersionsAreApplied() : void
    {
        $subject = $this->createSubject([20260101000000, 20260202000000]);

        self::assertSame([], $subject->findPendingMigrationVersions());
        self::assertTrue($subject->areMigrationsUpToDate());
    }

    public function testUnreadablePhinxlogCountsAsNotUpToDate() : void
    {
        $dbConnection = $this->createStub(Connection::class);
        $dbConnection->method('fetchFirstColumn')->willThrowException(new RuntimeException('no table'));

        $subject = new RuntimeHealthService($dbConnection, $this->directory . '/migrations', $this->directory . '/storage/');

        self::assertFalse($subject->areMigrationsUpToDate());
    }

    public function testStorageProbeLeavesNoFileBehind() : void
    {
        $subject = $this->createSubject([]);

        self::assertTrue($subject->isStorageWritable());
        self::assertSame(['.', '..'], scandir($this->directory . '/storage'));
    }

    public function testMissingStorageDirectoryIsNotWritable() : void
    {
        $dbConnection = $this->createStub(Connection::class);
        $subject = new RuntimeHealthService($dbConnection, $this->directory . '/migrations', $this->directory . '/missing/');

        self::assertFalse($subject->isStorageWritable());
    }

    /** @param list<int|string> $appliedVersions */
    private function createSubject(array $appliedVersions) : RuntimeHealthService
    {
        $dbConnection = $this->createStub(Connection::class);
        $dbConnection->method('fetchFirstColumn')->willReturn($appliedVersions);

        return new RuntimeHealthService($dbConnection, $this->directory . '/migrations', $this->directory . '/storage/');
    }
}
