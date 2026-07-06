<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Service\Translation\Translator;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;

class LanguageController
{
    public function __construct(
        private readonly SessionWrapper $sessionWrapper,
        private readonly Translator $translator,
    ) {
    }

    public function switchLanguage(Request $request) : Response
    {
        $locale = (string)($request->getRouteParameters()['locale'] ?? '');
        if ($this->translator->isSupported($locale) === true) {
            $this->sessionWrapper->set('locale', $locale);
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
        if (is_string($path) === false || str_starts_with($path, '/') === false) {
            return '/';
        }

        $query = parse_url($referer, PHP_URL_QUERY);

        return $path . (is_string($query) === true && $query !== '' ? '?' . $query : '');
    }
}
