<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\Movie\History\Location\MovieHistoryLocationApi;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\Util\Json;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

class LocationController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly MovieHistoryLocationApi $locationApi,
        private readonly UserApi $userApi,
    ) {
    }

    public function createLocation(Request $request) : Response
    {
        $requestData = Json::decode($request->getBody());

        // Create system-wide location (user_id = NULL)
        $this->locationApi->createLocation(
            null,
            $requestData['name'],
            empty($requestData['isCinema']) === false,
        );

        return Response::createOk();
    }

    public function deleteLocation(Request $request) : Response
    {
        $locationId = (int)$request->getRouteParameters()['locationId'];

        $location = $this->locationApi->findLocationById($locationId);

        if ($location === null) {
            return Response::createOk();
        }

        // Only allow deleting system-wide locations (user_id = NULL)
        if ($location->getUserId() !== null) {
            return Response::createForbidden();
        }

        try {
            $this->locationApi->deleteLocation($locationId);
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            // Location is in use - cannot delete
            return Response::createJson(
                Json::encode(['error' => 'Cannot delete location - it is currently in use by one or more movie ratings or watch dates.']),
                StatusCode::createConflict()
            );
        } catch (\Exception $e) {
            // Catch any other database errors
            return Response::createJson(
                Json::encode(['error' => 'Cannot delete location - it may be in use. Error: ' . $e->getMessage()]),
                StatusCode::createConflict()
            );
        }

        return Response::createOk();
    }

    public function fetchLocations() : Response
    {
        // Fetch system-wide locations (user_id = NULL)
        $locations = $this->locationApi->findLocationsByUserId(null);

        return Response::createJson(Json::encode($locations));
    }

    public function fetchToggleFeature() : Response
    {
        $currentUser = $this->authenticationService->getCurrentUser();

        $isLocationsEnabled = $this->userApi->isLocationsEnabled($currentUser->getId());

        return Response::createJson(
            Json::encode(
                ['locationsEnabled' => $isLocationsEnabled],
            ),
        );
    }

    public function updateLocation(Request $request) : Response
    {
        $locationId = (int)$request->getRouteParameters()['locationId'];
        $requestData = Json::decode($request->getBody());

        $location = $this->locationApi->findLocationById($locationId);

        if ($location === null) {
            return Response::createOk();
        }

        // Only allow updating system-wide locations (user_id = NULL)
        if ($location->getUserId() !== null) {
            return Response::createForbidden();
        }

        $this->locationApi->updateLocation(
            $locationId,
            $requestData['name'],
            (bool)$requestData['isCinema'],
        );

        return Response::createOk();
    }

    public function updateToggleFeature(Request $request) : Response
    {
        $currentUser = $this->authenticationService->getCurrentUser();
        $requestData = Json::decode($request->getBody());

        $this->userApi->updateLocationsEnabled(
            $currentUser->getId(),
            $requestData['locationsEnabled'],
        );

        return Response::createNoContent();
    }
}
