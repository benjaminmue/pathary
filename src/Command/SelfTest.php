<?php declare(strict_types=1);

namespace Movary\Command;

use Movary\Api\Tmdb\TmdbClient;
use Movary\Factory;
use Movary\Service\EncryptionService;
use Movary\Service\Health\RuntimeHealthService;
use Movary\Service\ServerSettings;
use Movary\ValueObject\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Functional self-test of a running installation. Meant for operators after a
 * deployment and for the e2e smoke test:
 *
 *   docker exec -u www-data <container> php bin/console.php selftest [--online]
 *
 * Run it as www-data, the web server user, so the storage check tests the
 * permissions the application actually has. Exits non-zero if any check fails;
 * warnings point at configuration that works but should be fixed.
 */
#[AsCommand(
    name: 'selftest',
    description: 'Check database, migrations, encryption, storage, cron and PHP runtime of this installation.',
    hidden: false,
)]
class SelfTest extends Command
{
    // Oldest PHP release with the fix for CVE-2026-109618/-109619 (see Dockerfile).
    private const string MIN_PHP_VERSION = '8.4.27';

    private const array REQUIRED_EXTENSIONS = ['pdo', 'mbstring', 'json', 'openssl', 'curl', 'intl', 'gd'];

    private const string CRON_FILE = '/etc/cron.d/pathary-sync';

    private const array CRON_JOBS = ['sync:scheduled', 'maintenance:prune', 'oauth:monitor'];

    private const string OK = 'OK';

    private const string WARN = 'WARN';

    private const string FAIL = 'FAIL';

    private int $failures = 0;

    public function __construct(
        private readonly RuntimeHealthService $runtimeHealthService,
        private readonly EncryptionService $encryptionService,
        private readonly ServerSettings $serverSettings,
        private readonly TmdbClient $tmdbClient,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure() : void
    {
        $this->addOption('online', null, InputOption::VALUE_NONE, 'Also call the TMDB API to verify the configured key.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->checkPhp($output);
        $this->checkDatabase($output);
        $this->checkEncryption($output);
        $this->checkStorage($output);
        $this->checkTmdb($output, (bool)$input->getOption('online'));
        $this->checkCron($output);

        $output->writeln('');
        $output->writeln($this->failures === 0 ? 'Self-test passed.' : sprintf('Self-test FAILED (%d check(s)).', $this->failures));

        return $this->failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkCron(OutputInterface $output) : void
    {
        if (is_file(self::CRON_FILE) === false) {
            // Only the production image installs it; dev setups and bare metal schedule jobs differently.
            $this->report($output, self::WARN, 'Cron jobs', self::CRON_FILE . ' not found (outside the production image, schedule ' . implode(', ', self::CRON_JOBS) . ' yourself)');

            return;
        }

        $crontab = (string)file_get_contents(self::CRON_FILE);
        $missing = array_filter(self::CRON_JOBS, fn(string $job) => preg_match('/^[^#].*\ssetpriv --reuid=www-data\s.*run-cron-job\.sh ' . preg_quote($job, '/') . '\b/m', $crontab) !== 1);
        if ($missing !== []) {
            $this->report($output, self::FAIL, 'Cron jobs', 'no www-data entry for: ' . implode(', ', $missing));

            return;
        }

        if ($this->isProcessRunning(['cron', 'crond']) === false) {
            $this->report($output, self::FAIL, 'Cron jobs', 'entries installed but no cron daemon is running');

            return;
        }

        $this->report($output, self::OK, 'Cron jobs', 'installed for www-data, daemon running');
    }

    private function checkDatabase(OutputInterface $output) : void
    {
        if ($this->runtimeHealthService->isDatabaseReachable() === false) {
            $this->report($output, self::FAIL, 'Database connection', 'SELECT 1 failed (mode: ' . Factory::getDatabaseMode($this->config) . ')');
            $this->report($output, self::FAIL, 'Migrations', 'skipped, no database connection');

            return;
        }

        $this->report($output, self::OK, 'Database connection', 'mode: ' . Factory::getDatabaseMode($this->config));

        try {
            $pending = $this->runtimeHealthService->findPendingMigrationVersions();
        } catch (Throwable) {
            $this->report($output, self::FAIL, 'Migrations', 'phinxlog table not readable, run database:migration:migrate');

            return;
        }

        $pending === []
            ? $this->report($output, self::OK, 'Migrations', 'up to date')
            : $this->report($output, self::FAIL, 'Migrations', 'pending: ' . implode(', ', $pending));
    }

    private function checkEncryption(OutputInterface $output) : void
    {
        $source = $this->encryptionService->getEncryptionKeySource();
        if ($source === 'not_configured') {
            $this->report($output, self::WARN, 'Encryption key', 'not configured: OAuth email is unavailable and the SMTP password is stored in plaintext');

            return;
        }

        try {
            $probe = 'selftest-' . bin2hex(random_bytes(8));
            $encrypted = $this->encryptionService->encrypt($probe);
            $roundTripOk = $this->encryptionService->decrypt($encrypted['encrypted'], $encrypted['iv']) === $probe;
        } catch (Throwable) {
            $roundTripOk = false;
        }

        if ($roundTripOk === false) {
            $this->report($output, self::FAIL, 'Encryption key', 'source: ' . $source . ', encrypt/decrypt round-trip failed (invalid key?)');

            return;
        }

        $source === 'environment'
            ? $this->report($output, self::OK, 'Encryption key', 'source: environment, round-trip ok')
            : $this->report($output, self::WARN, 'Encryption key', 'source: database (move it to ENCRYPTION_KEY, see docs/security/encryption-key.md), round-trip ok');
    }

    private function checkPhp(OutputInterface $output) : void
    {
        version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=') === true
            ? $this->report($output, self::OK, 'PHP version', PHP_VERSION)
            : $this->report($output, self::FAIL, 'PHP version', PHP_VERSION . ' is older than ' . self::MIN_PHP_VERSION);

        $required = self::REQUIRED_EXTENSIONS;
        $required[] = Factory::getDatabaseMode($this->config) === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite';

        $missing = array_values(array_filter($required, fn(string $extension) => extension_loaded($extension) === false));
        $missing === []
            ? $this->report($output, self::OK, 'PHP extensions', implode(', ', $required))
            : $this->report($output, self::FAIL, 'PHP extensions', 'missing: ' . implode(', ', $missing));
    }

    private function checkStorage(OutputInterface $output) : void
    {
        $user = function_exists('posix_geteuid') === true ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : 'unknown';

        if ($this->runtimeHealthService->isStorageWritable() === false) {
            $this->report($output, self::FAIL, 'Storage', $this->runtimeHealthService->getStorageDirectory() . ' is not writable for ' . $user);

            return;
        }

        $user === 'root'
            ? $this->report($output, self::WARN, 'Storage', 'writable, but checked as root; run with -u www-data to test the web server user')
            : $this->report($output, self::OK, 'Storage', 'writable for ' . $user);
    }

    private function checkTmdb(OutputInterface $output, bool $online) : void
    {
        if ((string)$this->serverSettings->getTmdbApiKey() === '') {
            $this->report($output, self::FAIL, 'TMDB API key', 'not configured (TMDB_API_KEY or Admin -> Server settings)');

            return;
        }

        if ($online === false) {
            $this->report($output, self::OK, 'TMDB API key', 'configured (use --online to verify it)');

            return;
        }

        try {
            $this->tmdbClient->get('/configuration');
            $this->report($output, self::OK, 'TMDB API key', 'accepted by api.themoviedb.org');
        } catch (Throwable $t) {
            // Only the exception class: messages can contain the request URL, which carries the key.
            $this->report($output, self::FAIL, 'TMDB API key', 'request failed (' . (new \ReflectionClass($t))->getShortName() . ')');
        }
    }

    /** @param list<string> $names */
    private function isProcessRunning(array $names) : bool
    {
        foreach (glob('/proc/[0-9]*/comm') ?: [] as $commFile) {
            // A process can exit between glob() and the read; is_readable() narrows that window.
            if (is_readable($commFile) === true && in_array(trim((string)file_get_contents($commFile)), $names, true) === true) {
                return true;
            }
        }

        return false;
    }

    private function report(OutputInterface $output, string $status, string $check, string $detail) : void
    {
        if ($status === self::FAIL) {
            $this->failures++;
        }

        $output->writeln(sprintf('[%-4s] %-20s %s', $status, $check, $detail));
    }
}
