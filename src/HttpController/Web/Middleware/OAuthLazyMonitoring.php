<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Service\Email\OAuthLazyMonitoringService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Psr\Log\LoggerInterface;

/**
 * Middleware that triggers lazy OAuth monitoring on page loads
 *
 * Runs OAuth health checks automatically when users visit the site,
 * without requiring cron jobs or scheduled tasks.
 *
 * This middleware is non-blocking and will not affect page load times.
 * It uses database locking to prevent concurrent runs and tracks
 * last run time to avoid excessive monitoring (runs at most every 6 hours).
 */
class OAuthLazyMonitoring implements MiddlewareInterface
{
    public function __construct(
        private readonly OAuthLazyMonitoringService $lazyMonitoringService,
        private readonly LoggerInterface $logger,
    ) {
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function __invoke(Request $request) : ?Response
    {
        try {
            // Trigger monitoring if needed (non-blocking, uses database lock)
            // This will only run if:
            // 1. At least 6 hours have passed since last run
            // 2. No other request is currently running monitoring (lock check)
            $this->lazyMonitoringService->triggerIfNeeded();
        } catch (\Throwable $e) {
            // Log error but don't interrupt the request
            $this->logger->error('OAuth lazy monitoring middleware failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Always continue the request chain
        return null;
    }
}
