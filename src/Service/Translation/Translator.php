<?php declare(strict_types=1);

namespace Movary\Service\Translation;

use Movary\Util\SessionWrapper;

/**
 * Lightweight message translator backed by flat PHP catalogs in
 * resources/translations/<locale>.php.
 *
 * The active locale is resolved lazily (once per request) so templates can call
 * t(...) without every route wiring in a locale middleware. Etappe 1 resolves
 * from the session (set by the language switcher); a later stage layers the
 * per-user profile preference on top.
 */
class Translator
{
    private const string DEFAULT_LOCALE = 'de';

    private const array SUPPORTED_LOCALES = ['de', 'en'];

    private readonly string $translationsDirectory;

    /** @var array<string, array<string, string>> */
    private array $catalogs = [];

    private ?string $locale = null;

    public function __construct(
        private readonly SessionWrapper $sessionWrapper,
        ?string $translationsDirectory = null,
    ) {
        $this->translationsDirectory = $translationsDirectory ?? __DIR__ . '/../../../resources/translations';
    }

    /**
     * @param array<string, string|int> $replacements Values for {placeholder} tokens
     */
    public function trans(string $key, array $replacements = []) : string
    {
        $translation = $this->getCatalog($this->getLocale())[$key]
            ?? $this->getCatalog(self::DEFAULT_LOCALE)[$key]
            ?? $key;

        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace('{' . $placeholder . '}', (string)$value, $translation);
        }

        return $translation;
    }

    public function getLocale() : string
    {
        if ($this->locale !== null) {
            return $this->locale;
        }

        $sessionLocale = $this->sessionWrapper->find('locale');
        $this->locale = is_string($sessionLocale) && $this->isSupported($sessionLocale)
            ? $sessionLocale
            : self::DEFAULT_LOCALE;

        return $this->locale;
    }

    /**
     * @return list<string>
     */
    public function getSupportedLocales() : array
    {
        return self::SUPPORTED_LOCALES;
    }

    public function isSupported(string $locale) : bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    /**
     * @return array<string, string>
     */
    private function getCatalog(string $locale) : array
    {
        if (isset($this->catalogs[$locale]) === false) {
            $file = $this->translationsDirectory . '/' . $locale . '.php';
            $this->catalogs[$locale] = is_file($file) === true ? (array)require $file : [];
        }

        return $this->catalogs[$locale];
    }
}
