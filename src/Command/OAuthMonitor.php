<?php declare(strict_types=1);

namespace Movary\Command;

use Movary\Service\Email\OAuthMonitoringService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Checks the OAuth email connection (token refresh) and sends expiry alerts.
 *
 * Runs from cron (docker/cron/pathary-sync) instead of on web requests, so no
 * visitor ever waits for an outbound token refresh.
 */
#[AsCommand(
    name: 'oauth:monitor',
    description: 'Check the OAuth email token health and send expiry alerts.',
    hidden: false,
)]
class OAuthMonitor extends Command
{
    public function __construct(
        private readonly OAuthMonitoringService $oauthMonitoringService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        try {
            $result = $this->oauthMonitoringService->runMonitoring();
        } catch (Throwable $t) {
            $this->logger->error('OAuth monitoring failed', ['exception' => $t]);
            $this->generateOutput($output, 'ERROR: OAuth monitoring failed, see the application log');

            return Command::FAILURE;
        }

        $this->generateOutput($output, sprintf('OAuth monitoring: alert level %s - %s', $result['alert_level'], $result['message']));

        // A failed refresh is a monitoring result (stored and alerted on), not a
        // failure of this command.
        return Command::SUCCESS;
    }
}
