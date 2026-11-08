<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\Service\WebhookUrlBuilder;
use Movary\Util\Json;
use Movary\ValueObject\Http\Response;

class EmbyController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
        private readonly WebhookUrlBuilder $webhookUrlBuilder,
    ) {
    }

    public function deleteEmbyWebhookUrl() : Response
    {
        $this->userApi->deleteEmbyWebhookId($this->authenticationService->getCurrentUserId());

        return Response::createOk();
    }

    public function regenerateEmbyWebhookUrl() : Response
    {
        $webhookId = $this->userApi->regenerateEmbyWebhookId($this->authenticationService->getCurrentUserId());

        return Response::createJson(Json::encode(['url' => $this->webhookUrlBuilder->buildEmbyWebhookUrl($webhookId)]));
    }
}
