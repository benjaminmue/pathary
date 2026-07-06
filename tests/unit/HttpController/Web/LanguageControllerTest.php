<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\HttpController\Web\LanguageController;
use Movary\Service\Translation\Translator;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageController::class)]
class LanguageControllerTest extends TestCase
{
    private SessionWrapper&MockObject $sessionWrapper;

    private Translator&MockObject $translator;

    private Authentication&MockObject $authenticationService;

    private UserApi&MockObject $userApi;

    private LanguageController $subject;

    protected function setUp() : void
    {
        $this->sessionWrapper = $this->createMock(SessionWrapper::class);
        $this->translator = $this->createMock(Translator::class);
        $this->authenticationService = $this->createMock(Authentication::class);
        $this->userApi = $this->createMock(UserApi::class);

        $this->subject = new LanguageController(
            $this->sessionWrapper,
            $this->translator,
            $this->authenticationService,
            $this->userApi,
        );
    }

    public function testSwitchPersistsLanguageForAuthenticatedUser() : void
    {
        $this->translator->method('isSupported')->with('en')->willReturn(true);
        $this->authenticationService->method('isUserAuthenticatedWithCookie')->willReturn(true);
        $this->authenticationService->method('getCurrentUserId')->willReturn(37);

        $this->sessionWrapper->expects(self::once())->method('set')->with('locale', 'en');
        $this->userApi->expects(self::once())->method('updateLanguage')->with(37, 'en');

        $response = $this->subject->switchLanguage($this->request('en'));

        self::assertSame(303, $response->getStatusCode()->getCode());
    }

    public function testSwitchDoesNotPersistForGuest() : void
    {
        $this->translator->method('isSupported')->with('en')->willReturn(true);
        $this->authenticationService->method('isUserAuthenticatedWithCookie')->willReturn(false);

        $this->sessionWrapper->expects(self::once())->method('set')->with('locale', 'en');
        $this->userApi->expects(self::never())->method('updateLanguage');

        $this->subject->switchLanguage($this->request('en'));
    }

    public function testUnsupportedLocaleIsIgnored() : void
    {
        $this->translator->method('isSupported')->with('fr')->willReturn(false);

        $this->sessionWrapper->expects(self::never())->method('set');
        $this->userApi->expects(self::never())->method('updateLanguage');

        $response = $this->subject->switchLanguage($this->request('fr'));

        self::assertSame(303, $response->getStatusCode()->getCode());
    }

    private function request(string $locale) : Request&MockObject
    {
        $request = $this->createMock(Request::class);
        $request->method('getRouteParameters')->willReturn(['locale' => $locale]);
        $request->method('getHttpReferer')->willReturn(null);

        return $request;
    }
}
