<?php

declare(strict_types=1);

namespace test\Support;

use CURLFile;

/**
 * Minimal HTTP client + assertions for tests that talk to the Apache inside the test container
 * (APP_BASE_URL, see compose.test.yml and dev.tool/run-tests.sh). For use in PHPUnit TestCase classes.
 */
trait HttpRequests
{
    /**
     * PHP messages displayed in a response body. display_errors is on in the dev/test image, so a warning, notice
     * or deprecation raised by any PHP version ends up in the page - in one of three formats:
     *
     *   html_errors=1, the default under Apache    <b>Warning</b>:  message in <b>/file.php</b> on line <b>3</b>
     *   html_errors=0 (CLI)                        Warning: message in /file.php on line 3
     *   Xdebug "develop" mode (the dev container)  ( ! )</span> Warning: message in /file.php on line <i>3</i>
     *
     * Looking for the plain-text form only ('Warning:') misses everything Apache prints.
     *
     * @return array<int, string> one entry per message, tags stripped
     */
    protected static function findPhpErrors(string $body): array
    {
        $found = [];
        $pattern = '~(?:<b>)?\b(Fatal error|Parse error|Warning|Notice|Deprecated)(?:</b>)?:\s+([^\n]{0,240})~';
        if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found[] = $match[1] . ': ' . trim(strip_tags($match[2]));
            }
        }
        foreach (['Uncaught', 'Stack trace:'] as $marker) {
            if (strpos($body, $marker) !== false) {
                $found[] = "the page contains '{$marker}'";
            }
        }

        return $found;
    }

    /** Marks the test skipped when no web server is configured; otherwise the base URL without trailing slash. */
    protected function baseUrlOrSkip(): string
    {
        $baseUrl = getenv('APP_BASE_URL');
        if ($baseUrl === false || $baseUrl === '') {
            $this->markTestSkipped('APP_BASE_URL not set - run via `composer run tests:docker`.');
        }

        return rtrim((string) $baseUrl, '/');
    }

    /**
     * @return array{status: int, body: string}
     */
    protected function httpGet(string $url): array
    {
        return $this->httpSend($url, []);
    }

    /**
     * multipart/form-data POST with one uploaded file, like the application's `<input type="file">` forms.
     *
     * @param array<string, string> $fields additional plain form fields
     * @return array{status: int, body: string}
     */
    protected function httpPostFile(string $url, string $fileField, string $path, string $uploadName, array $fields = []): array
    {
        $fields[$fileField] = new CURLFile($path, 'application/octet-stream', $uploadName);

        return $this->httpSend($url, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields]);
    }

    protected function assertNoPhpErrors(string $body, string $context): void
    {
        $found = self::findPhpErrors($body);

        $this->assertSame([], $found, "{$context}: PHP reported " . count($found) . ' problem(s) in the page');
    }

    /**
     * @param array<int, mixed> $options extra curl options
     * @return array{status: int, body: string}
     */
    private function httpSend(string $url, array $options): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADER => false,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $this->fail("request to {$url} failed: " . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return ['status' => $status, 'body' => (string) $body];
    }
}
