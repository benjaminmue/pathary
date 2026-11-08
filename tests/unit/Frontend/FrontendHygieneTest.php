<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Frontend;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Static checks over templates and front-end scripts for regressions the
 * review found: unescaped HTML sinks, hard-coded document language, CDN assets
 * and drifting translation catalogs.
 */
#[CoversNothing]
class FrontendHygieneTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../../..';

    /**
     * Interpolations that are safe inside an HTML template literal: escaped
     * values, numeric ids, translated constants rendered by Twig with
     * json_encode, and fixed class names chosen in code.
     */
    private const array SAFE_INTERPOLATIONS = [
        '/^escapeHtml\(.*\)$/s',
        '/^[A-Z][A-Z0-9_]*$/',
        '/^\w*I18N\.\w+$/i',
        '/^[\w.]*\.id$/',
        '/^(currentPage [+-] 1)$/',
        '/^\w+ \? \'[\w ]*\' : \'[\w ]*\'$/',
        '/^(alertClass|iconClass|iconTypeClass|userName|toastId)$/',
        '/^getEventType(BadgeColor|Icon)\(event\.event_type\)$/',
        '/^opts\.getKeyUrl$/',
    ];

    // Swiss German spelling: the sharp s never appears in the German catalog.
    private const string SHARP_S = "\u{00DF}";

    public function testHtmlSinksOnlyInterpolateEscapedValues() : void
    {
        $violations = [];

        foreach ($this->frontendFiles() as $file) {
            $source = (string)file_get_contents($file);
            preg_match_all('/(?:innerHTML|outerHTML)\s*\+?=\s*`(.*?)`|insertAdjacentHTML\([^,]+,\s*`(.*?)`/s', $source, $sinks, PREG_SET_ORDER);

            foreach ($sinks as $sink) {
                $template = $sink[1] !== '' ? $sink[1] : ($sink[2] ?? '');
                preg_match_all('/\$\{((?:[^{}]|\{[^{}]*\})*)\}/', $template, $expressions);

                foreach ($expressions[1] as $expression) {
                    if ($this->isSafeInterpolation(trim($expression)) === false) {
                        $violations[] = $this->relative($file) . ': ${' . trim($expression) . '}';
                    }
                }
            }
        }

        self::assertSame([], $violations, "Escape these values with escapeHtml() or build the DOM with textContent:\n" . implode("\n", $violations));
    }

    public function testNoHtmlSinkConcatenatesRuntimeValues() : void
    {
        $violations = [];

        foreach ($this->frontendFiles() as $file) {
            foreach (file($file) ?: [] as $number => $line) {
                if (preg_match('/(?:innerHTML|outerHTML)\s*\+?=\s*(.+)$/', $line, $match) !== 1 || str_contains($match[1], '+') === false) {
                    continue;
                }

                // Only string literals and translated constants may be joined into markup.
                $rest = (string)preg_replace(['/\'[^\']*\'/', '/"[^"]*"/', '/\b\w*I18N\.\w+\b/i'], '', $match[1]);
                if (preg_match('/[A-Za-z_$][\w.$]*/', $rest) === 1) {
                    $violations[] = $this->relative($file) . ':' . ($number + 1) . ': ' . trim($line);
                }
            }
        }

        self::assertSame([], $violations, "Build these nodes with textContent instead:\n" . implode("\n", $violations));
    }

    public function testEveryDocumentDeclaresTheCurrentLocale() : void
    {
        foreach ($this->files(self::ROOT . '/templates', 'twig') as $file) {
            $source = (string)file_get_contents($file);
            if (str_contains($file, '/email/') === true || preg_match_all('/<html\b[^>]*>/i', $source, $tags) === 0) {
                continue;
            }

            foreach ($tags[0] as $tag) {
                self::assertStringContainsString('lang="{{ locale() }}"', $tag, $this->relative($file));
            }
        }
    }

    public function testNoAssetsAreLoadedFromCdns() : void
    {
        foreach ($this->files(self::ROOT . '/templates', 'twig') as $file) {
            self::assertDoesNotMatchRegularExpression(
                '#(src|href)="https://(cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com|fonts\.googleapis\.com)#',
                (string)file_get_contents($file),
                $this->relative($file) . ' loads an asset from a CDN; self-host it under public/.',
            );
        }
    }

    public function testTranslationCatalogsHaveTheSameKeys() : void
    {
        /** @var array<string, string> $english */
        $english = require __DIR__ . '/../../../resources/translations/en.php';
        /** @var array<string, string> $german */
        $german = require __DIR__ . '/../../../resources/translations/de.php';

        self::assertSame([], array_keys(array_diff_key($english, $german)), 'Missing in de.php');
        self::assertSame([], array_keys(array_diff_key($german, $english)), 'Missing in en.php');
        self::assertSame([], array_keys(array_filter($german, static fn(string $text) : bool => str_contains($text, self::SHARP_S))));
    }

    /**
     * @return list<string>
     */
    private function frontendFiles() : array
    {
        $scripts = array_filter(
            array_merge($this->files(self::ROOT . '/public/js', 'js'), $this->files(self::ROOT . '/public/assets/js', 'js')),
            // Vendored libraries are not ours to lint.
            static fn(string $file) : bool => preg_match('/(\.min\.js|jquery-[\d.]+\.js|swagger-ui-bundle-[\d.]+\.js)$/', $file) !== 1,
        );

        return array_merge(array_values($scripts), $this->files(self::ROOT . '/templates', 'twig'));
    }

    /**
     * @return list<string>
     */
    private function files(string $directory, string $extension) : array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() === true && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function isSafeInterpolation(string $expression) : bool
    {
        foreach (self::SAFE_INTERPOLATIONS as $pattern) {
            if (preg_match($pattern, $expression) === 1) {
                return true;
            }
        }

        return false;
    }

    private function relative(string $file) : string
    {
        return str_replace(self::ROOT . '/', '', $file);
    }
}
