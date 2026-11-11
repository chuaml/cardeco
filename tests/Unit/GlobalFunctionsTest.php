<?php

declare(strict_types=1);

namespace test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Characterization of the autoloaded `text()` helper (src/global.function.php:8-14), the HTML-escaping
 * function every view is expected to use. (dumpjson/dd/ddtext call header()+exit and are not unit-testable.)
 */
final class GlobalFunctionsTest extends TestCase
{
    public function testText_IsAutoloadedByComposer(): void
    {
        $this->assertTrue(function_exists('text'));
    }

    public function testText_Null_ReturnsEmptyString(): void
    {
        $this->assertSame('', text(null));
    }

    public function testText_EmptyString_ReturnsEmptyString(): void
    {
        $this->assertSame('', text(''));
    }

    /**
     * @dataProvider escapeProvider
     */
    public function testText_EscapesHtmlSpecialCharacters(string $raw, string $expected): void
    {
        $this->assertSame($expected, text($raw));
    }

    public function escapeProvider(): array
    {
        return [
            'angle brackets' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'ampersand' => ['Seat & Cover', 'Seat &amp; Cover'],
            'double quote' => ['5" mat', '5&quot; mat'],
            'single quote (ENT_QUOTES)' => ["O'Neil", 'O&#039;Neil'],
            'attribute breakout' => ['" onmouseover="x', '&quot; onmouseover=&quot;x'],
            'plain text untouched' => ['ZZTEST-001 / 12.90', 'ZZTEST-001 / 12.90'],
            'unicode untouched' => ['Kuala Lumpur – 吉隆坡', 'Kuala Lumpur – 吉隆坡'],
        ];
    }

    public function testText_AlreadyEscapedInput_IsDoubleEncoded(): void
    {
        $this->assertSame('&amp;amp; &amp;lt;', text('&amp; &lt;'));
    }

    public function testText_InvalidUtf8_IsReplacedBySubstitutionCharacter(): void
    {
        // ENT_SUBSTITUTE: a broken byte becomes U+FFFD instead of the whole string becoming ''
        $this->assertSame("bad \xEF\xBF\xBD byte", text("bad \xB1 byte"));
    }
}
