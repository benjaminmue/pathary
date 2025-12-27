<?php declare(strict_types=1);

namespace Movary\HttpController\Api;

use Movary\Domain\User\Repository\SecurityAuditRepository;
use Movary\Util\Json;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

class AdminEventsController
{
    public function __construct(
        private readonly SecurityAuditRepository $securityAuditRepository,
    ) {
    }

    /**
     * GET /api/admin/events - Get filtered security events
     */
    public function getEvents(Request $request) : Response
    {
        // Parse query parameters
        $queryParams = $request->getGetParameters();

        $eventType = $queryParams['eventType'] ?? null;
        $searchQuery = $queryParams['searchQuery'] ?? null;
        $dateFrom = $queryParams['dateFrom'] ?? null;
        $dateTo = $queryParams['dateTo'] ?? null;
        $userId = isset($queryParams['userId']) ? (int)$queryParams['userId'] : null;
        $ipAddress = $queryParams['ipAddress'] ?? null;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
        $offset = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;

        // Validate limit and offset
        $limit = max(1, min($limit, 100)); // Max 100 per page
        $offset = max(0, $offset);

        try {
            // Get filtered events
            $events = $this->securityAuditRepository->findWithFilters(
                $eventType,
                $searchQuery,
                $dateFrom,
                $dateTo,
                $userId,
                $ipAddress,
                $limit,
                $offset
            );

            // Get total count with same filters
            $total = $this->securityAuditRepository->countWithFilters(
                $eventType,
                $searchQuery,
                $dateFrom,
                $dateTo,
                $userId,
                $ipAddress
            );

            return Response::create(
                StatusCode::createOk(),
                Json::encode([
                    'events' => $events,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ]),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to fetch events']),
                [Header::createContentTypeJson()],
            );
        }
    }

    /**
     * GET /api/admin/events/{id} - Get event details by ID
     */
    public function getEventById(Request $request) : Response
    {
        $eventId = (int)$request->getRouteParameters()['id'];

        if ($eventId <= 0) {
            return Response::create(
                StatusCode::createBadRequest(),
                Json::encode(['error' => 'Invalid event ID']),
                [Header::createContentTypeJson()],
            );
        }

        try {
            $event = $this->securityAuditRepository->findById($eventId);

            if ($event === null) {
                return Response::create(
                    StatusCode::createNotFound(),
                    Json::encode(['error' => 'Event not found']),
                    [Header::createContentTypeJson()],
                );
            }

            return Response::create(
                StatusCode::createOk(),
                Json::encode($event),
                [Header::createContentTypeJson()],
            );
        } catch (\Exception $e) {
            return Response::create(
                StatusCode::createInternalServerError(),
                Json::encode(['error' => 'Failed to fetch event']),
                [Header::createContentTypeJson()],
            );
        }
    }
}
