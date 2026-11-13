<?php

declare(strict_types=1);

namespace test\Unit\Support;

use PHPUnit\Framework\TestCase;
use test\Support\HttpRequests;

/**
 * The HTTP smoke tests (tests/Smoke) decide "the page rendered without PHP errors" by looking for PHP's messages
 * in the response body. That check must recognise every format PHP can print them in: looking for the plain-text
 * form only ('Warning:') silently misses everything Apache prints (`<b>Warning</b>:`), on every PHP version.
 */
final class HttpRequestsTest extends TestCase
{
    use HttpRequests;

    public function testFindPhpErrors_CleanPage_FindsNothing(): void
    {
        $body = '<html><body><nav></nav><table><tr><td>ZZTEST-001</td><td>Warning lamp, deprecated model</td></tr></table>'
            . '<p>Notice the price.</p></body></html>';

        $this->assertSame([], self::findPhpErrors($body));
    }

    public function testFindPhpErrors_ApacheHtmlFormat_IsFoundForEverySeverity(): void
    {
        // html_errors=1 is the default outside the CLI: this is what Apache prints
        $body = "<nav></nav><br />\n<b>Deprecated</b>:  trim(): Passing null to parameter #1 (\$string) of type string is deprecated"
            . " in <b>/var/www/html/request_handler/RecordUpdater.php</b> on line <b>31</b><br />\n"
            . "<br />\n<b>Warning</b>:  Undefined variable \$x in <b>/var/www/html/a.php</b> on line <b>1</b><br />\n"
            . "<br />\n<b>Notice</b>:  Undefined index: id in <b>/var/www/html/b.php</b> on line <b>2</b><br />\n";

        $this->assertSame([
            'Deprecated: trim(): Passing null to parameter #1 ($string) of type string is deprecated'
                . ' in /var/www/html/request_handler/RecordUpdater.php on line 31',
            'Warning: Undefined variable $x in /var/www/html/a.php on line 1',
            'Notice: Undefined index: id in /var/www/html/b.php on line 2',
        ], self::findPhpErrors($body));
    }

    public function testFindPhpErrors_PlainTextFormat_IsFound(): void
    {
        // html_errors=0 (CLI, or switched off in php.ini)
        $body = "<nav></nav>\nWarning: number_format() expects parameter 1 to be float, string given in /var/www/html/a.php on line 66\n";

        $this->assertSame(
            ['Warning: number_format() expects parameter 1 to be float, string given in /var/www/html/a.php on line 66'],
            self::findPhpErrors($body)
        );
    }

    public function testFindPhpErrors_XdebugDevelopFormat_IsFound(): void
    {
        // the dev container runs Apache with xdebug.mode=develop, which replaces PHP's own error display
        $body = "<table class='xdebug-error xe-deprecated'><tr><th align='left' colspan=\"5\"><span style='font-size: x-large;'>( ! )</span>"
            . " Deprecated: strlen(): Passing null to parameter #1 (\$string) of type string is deprecated"
            . " in /var/www/html/a.php on line <i>163</i></th></tr></table>";

        $found = self::findPhpErrors($body);

        $this->assertCount(1, $found);
        $this->assertStringStartsWith('Deprecated: strlen(): Passing null to parameter #1', $found[0]);
    }

    public function testFindPhpErrors_UncaughtException_IsFound(): void
    {
        $body = "<br />\n<b>Fatal error</b>:  Uncaught TypeError: number_format(): Argument #1 (\$num) must be of type float, string given"
            . " in /var/www/html/a.php:66\nStack trace:\n#0 /var/www/html/a.php(66): number_format('', 2, '.', ',')\n";

        $found = self::findPhpErrors($body);

        $this->assertStringStartsWith('Fatal error: Uncaught TypeError: number_format()', $found[0]);
        $this->assertContains("the page contains 'Uncaught'", $found);
        $this->assertContains("the page contains 'Stack trace:'", $found);
    }
}
