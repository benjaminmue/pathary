<?php declare(strict_types=1);

namespace Movary\Command;

use Movary\Service\DataRetentionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'maintenance:prune',
    description: 'Delete login/password-setup attempts older than ' . DataRetentionService::ATTEMPT_RETENTION_DAYS . ' days, '
        . 'expired sessions and trusted devices, and security events older than ' . DataRetentionService::AUDIT_LOG_RETENTION_DAYS . ' days.',
    hidden: false,
)]
class MaintenancePrune extends Command
{
    public function __construct(
        private readonly DataRetentionService $dataRetentionService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        try {
            $deleted = $this->dataRetentionService->prune();
        } catch (Throwable $t) {
            $this->logger->error('Data retention cleanup failed', ['exception' => $t]);
            $this->generateOutput($output, 'ERROR: data retention cleanup failed, see the application log');

            return Command::FAILURE;
        }

        foreach ($deleted as $table => $count) {
            $this->generateOutput($output, sprintf('Pruned %s: %s', $table, $count === null ? 'done' : $count . ' rows'));
        }

        return Command::SUCCESS;
    }
}
