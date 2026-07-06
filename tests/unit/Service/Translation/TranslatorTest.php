<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service\Translation;

use Movary\Service\Translation\Translator;
use Movary\Util\SessionWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Translator::class)]
class TranslatorTest extends TestCase
{
    public function testTranslatesToGermanByDefault() : void
    {
        self::assertSame('Alle Filme', $this->translatorWithSessionLocale(null)->trans('nav.all_movies'));
    }

    public function testTranslatesToEnglishWhenSessionLocaleIsEnglish() : void
    {
        self::assertSame('All Movies', $this->translatorWithSessionLocale('en')->trans('nav.all_movies'));
    }

    public function testUnsupportedSessionLocaleFallsBackToDefault() : void
    {
        self::assertSame('de', $this->translatorWithSessionLocale('fr')->getLocale());
    }

    public function testMissingKeyReturnsTheKeyItself() : void
    {
        self::assertSame('nav.does_not_exist', $this->translatorWithSessionLocale('en')->trans('nav.does_not_exist'));
    }

    public function testReplacementsAreApplied() : void
    {
        $directory = sys_get_temp_dir() . '/translator_test_' . uniqid('', true);
        mkdir($directory);
        file_put_contents($directory . '/en.php', "<?php return ['greeting' => 'Hello {name}'];");

        try {
            $session = $this->createMock(SessionWrapper::class);
            $session->method('find')->willReturn('en');
            $translator = new Translator($session, $directory);

            self::assertSame('Hello Ben', $translator->trans('greeting', ['name' => 'Ben']));
        } finally {
            unlink($directory . '/en.php');
            rmdir($directory);
        }
    }

    private function translatorWithSessionLocale(?string $sessionLocale) : Translator
    {
        $session = $this->createMock(SessionWrapper::class);
        $session->method('find')->with('locale')->willReturn($sessionLocale);

        return new Translator($session);
    }
}
