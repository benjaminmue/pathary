<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Service\Health\RuntimeHealthService;
use Movary\Util\Json;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

/**
 * Unauthenticated liveness/readiness probe for the container HEALTHCHECK, the
 * e2e smoke test and external monitors. Reports only pass/fail per check, no
 * versions, hostnames or error messages.
 */
class HealthzController
{
    public function __construct(
        private readonly RuntimeHealthService $runtimeHealthService,
    ) {
    }

    public function check() : Response
    {
        $databaseOk = $this->runtimeHealthService->isDatabaseReachable();

        $checks = [
            'database' => $databaseOk,
            // Without a database the migration state is unknown, not "ok".
            'migrations' => $databaseOk === true && $this->runtimeHealthService->areMigrationsUpToDate(),
            'storage' => $this->runtimeHealthService->isStorageWritable(),
        ];

        $healthy = $checks['database'] && $checks['migrations'] && $checks['storage'];

        return Response::createJson(
            Json::encode([
                'status' => $healthy ? 'ok' : 'fail',
                'checks' => array_map(fn(bool $ok) : string => $ok ? 'ok' : 'fail', $checks),
            ]),
            $healthy ? StatusCode::createOk() : StatusCode::createServiceUnavailable(),
        );
    }
}
