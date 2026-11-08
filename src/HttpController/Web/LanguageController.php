<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\Service\Translation\Translator;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;

class LanguageController
{
    public function __construct(
        private readonly SessionWrapper $sessionWrapper,
        private readonly Translator $translator,
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
    ) {
    }

    public function switchLanguage(Request $request) : Response
    {
        $locale = (string)($request->getRouteParameters()['locale'] ?? '');
        if ($this->translator->isSupported($locale) === true) {
            $this->sessionWrapper->set('locale', $locale);

            // Persist the choice for logged-in users so it survives new sessions
            // and follows them across devices.
            if ($this->authenticationService->isUserAuthenticatedWithCookie() === true) {
                $this->userApi->updateLanguage($this->authenticationService->getCurrentUserId(), $locale);
            }
        }

        return Response::createSeeOther($this->resolveRedirectTarget($request));
    }

    private function resolveRedirectTarget(Request $request) : string
    {
        // Return to the page the switch was triggered from, but only ever as a
        // same-site relative path (drop scheme/host) to avoid an open redirect.
        $referer = $request->getHttpReferer();
        if ($referer === null || $referer === '') {
            return '/';
        }

        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        if (is_string($path) === false) {
            return '/';
        }

        $target = $path . (is_string($query) === true && $query !== '' ? '?' . $query : '');

        return $this->isSameOriginPath($target) === true ? $target : '/';
    }

    /**
     * Browsers treat "//host" and "/\host" as protocol-relative URLs, so a path is only
     * safe as a redirect target if it starts with a single "/" and has no backslash.
     * (parse_url() already replaced control characters such as tabs with "_".)
     */
    private function isSameOriginPath(string $path) : bool
    {
        return str_starts_with($path, '/') === true
            && str_starts_with($path, '//') === false
            && str_contains($path, '\\') === false;
    }
}
