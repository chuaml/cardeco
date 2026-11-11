<?php

declare(strict_types=1);

namespace test\Support;

use PHPUnit\Framework\Assert;

/**
 * Golden-file assertions for HTML produced by the order processors.
 *
 * HTML is normalised (whitespace between tags collapsed, one table row per line) so diffs stay readable.
 * Regenerate goldens on purpose with:  CARDECO_UPDATE_GOLDEN=1 composer run tests:docker -- --filter Golden
 */
final class GoldenFile
{
    public static function assertHtmlMatches(string $goldenPath, string $actualHtml, string $message = ''): void
    {
        $normalised = self::normaliseHtml($actualHtml);

        if (getenv('CARDECO_UPDATE_GOLDEN') === '1') {
            if (!is_dir(dirname($goldenPath))) {
                mkdir(dirname($goldenPath), 0775, true);
            }
            file_put_contents($goldenPath, $normalised . "\n");
        }

        if (!is_file($goldenPath)) {
            Assert::fail(
                "Golden file missing: {$goldenPath}\n"
                . 'Create it with CARDECO_UPDATE_GOLDEN=1 (review the generated HTML before committing).'
            );
        }

        $expected = rtrim((string) file_get_contents($goldenPath), "\n");
        Assert::assertSame($expected, $normalised, $message !== '' ? $message : 'HTML differs from ' . basename($goldenPath));
    }

    public static function normaliseHtml(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        // one row / section per line for readable diffs
        $html = preg_replace('#(</tr>|</thead>|</tbody>|</tfoot>|<tbody>|<thead>|<tfoot>)#', "$1\n", $html);
        $html = preg_replace("/\n+/", "\n", $html);

        return trim($html);
    }
}
