<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Frontend;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reads the colour tokens straight from the stylesheets and checks that every
 * text token reaches WCAG 2.2 AA (4.5:1) against every surface it is used on,
 * in both themes. Changing a token to a value that fails breaks the build.
 */
#[CoversNothing]
class DesignTokenContrastTest extends TestCase
{
    private const float MIN_TEXT_CONTRAST = 4.5;

    private const string THEME_CSS = __DIR__ . '/../../../public/assets/css/theme.css';

    private const string GLOBAL_CSS = __DIR__ . '/../../../public/css/global.css';

    /**
     * @return array<string, array{string, string, list<string>, list<string>}>
     */
    public static function provideTokenSets() : array
    {
        $pdsText = ['--pds-text', '--pds-muted', '--pds-dim', '--pds-gold-text'];
        $pdsSurfaces = ['--pds-bg', '--pds-surface', '--pds-surface-2'];
        $scText = ['--sc-text', '--sc-muted', '--sc-dim', '--sc-gold-text'];
        $scSurfaces = ['--sc-bg', '--sc-surface', '--sc-surface-2'];

        return [
            'page tokens, dark' => [self::THEME_CSS, 'dark', $pdsText, $pdsSurfaces],
            'page tokens, light' => [self::THEME_CSS, 'light', $pdsText, $pdsSurfaces],
            'showcase tokens, dark' => [self::GLOBAL_CSS, 'dark', $scText, $scSurfaces],
            'showcase tokens, light' => [self::GLOBAL_CSS, 'light', $scText, $scSurfaces],
        ];
    }

    /**
     * @param list<string> $textTokens
     * @param list<string> $surfaceTokens
     */
    #[DataProvider('provideTokenSets')]
    public function testTextTokensReachAaOnTheirSurfaces(string $cssFile, string $theme, array $textTokens, array $surfaceTokens) : void
    {
        $tokens = $this->loadTokens($cssFile, $theme);

        foreach ($textTokens as $textToken) {
            foreach ($surfaceTokens as $surfaceToken) {
                $ratio = self::contrastRatio($tokens[$textToken], $tokens[$surfaceToken]);

                self::assertGreaterThanOrEqual(
                    self::MIN_TEXT_CONTRAST,
                    round($ratio, 2),
                    sprintf('%s %s on %s is %.2f:1 (%s)', $textToken, $tokens[$textToken], $surfaceToken, $ratio, $theme),
                );
            }
        }
    }

    public function testFocusRingIsVisibleInBothThemes() : void
    {
        // Non-text contrast (WCAG 1.4.11) needs 3:1 against the page background.
        foreach (['dark' => '--pds-bg', 'light' => '--pds-bg'] as $theme => $background) {
            $tokens = $this->loadTokens(self::THEME_CSS, $theme);

            self::assertGreaterThanOrEqual(3.0, self::contrastRatio($tokens['--focus-ring'], $tokens[$background]), $theme);
        }
    }

    public function testContrastFormulaMatchesKnownValues() : void
    {
        self::assertEqualsWithDelta(21.0, self::contrastRatio('#000000', '#FFFFFF'), 0.01);
        self::assertEqualsWithDelta(1.71, self::contrastRatio('#FBBC09', '#FFFFFF'), 0.01);
    }

    private static function contrastRatio(string $foreground, string $background) : float
    {
        $lighter = max(self::relativeLuminance($foreground), self::relativeLuminance($background));
        $darker = min(self::relativeLuminance($foreground), self::relativeLuminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relativeLuminance(string $hex) : float
    {
        if (preg_match('/^#([0-9a-f]{6})$/i', $hex, $match) !== 1) {
            throw new RuntimeException('Expected a #RRGGBB colour, got: ' . $hex);
        }

        $channels = array_map(
            static function (string $pair) : float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($match[1], 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Collects custom properties from :root and the scoped token blocks, then, for
     * the light theme, applies the [data-bs-theme="light"] overrides on top.
     * var() references are resolved.
     *
     * @return array<string, string>
     */
    private function loadTokens(string $cssFile, string $theme) : array
    {
        $css = (string)preg_replace('#/\*.*?\*/#s', '', file_get_contents($cssFile) . file_get_contents(self::THEME_CSS));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);

        $layers = ['base' => [], 'light' => []];
        foreach ($blocks as $block) {
            $selector = trim($block[1]);
            $layer = match ($selector) {
                ':root', '.pds-root', '.showcase-home' => 'base',
                '[data-bs-theme="light"]', '[data-bs-theme="light"] .pds-root', '[data-bs-theme="light"] .showcase-home' => 'light',
                default => null,
            };
            if ($layer === null) {
                continue;
            }

            preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $block[2], $declarations, PREG_SET_ORDER);
            foreach ($declarations as $declaration) {
                $layers[$layer][$declaration[1]] = trim($declaration[2]);
            }
        }

        $tokens = $theme === 'light' ? array_merge($layers['base'], $layers['light']) : $layers['base'];

        return array_map(fn(string $value) : string => $this->resolve($value, $tokens), $tokens);
    }

    /**
     * @param array<string, string> $tokens
     */
    private function resolve(string $value, array $tokens, int $depth = 0) : string
    {
        if ($depth > 5 || preg_match('/^var\((--[\w-]+)\)$/', $value, $match) !== 1) {
            return strtoupper($value);
        }

        return $this->resolve($tokens[$match[1]] ?? $value, $tokens, $depth + 1);
    }
}
