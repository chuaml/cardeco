<?php

/**
 * TODO.md 1.2: dual-target PHP compatibility scan, exported to audit-php83-blockers.txt
 * (the file is gitignored through `*.txt`; regenerate it at any time).
 *
 *   composer run audit:php83        (php dev.tool/audit-php83.php)
 *
 * Section 1 - BLOCKERS: PHPCompatibility findings for PHP 7.3 - 8.3, the range the code base must support.
 *             The same findings fail `composer run lint`. Exit code 1 when there is at least one.
 * Section 2 - HEADROOM: what an open-ended scan ("7.3-") reports on top of that, i.e. what PHP 8.4 and later
 *             deprecate. Informational only.
 *
 * Runtime deprecations (null passed to string functions, ...) cannot be found statically; the PHPUnit suites
 * catch them because phpunit.xml.dist converts deprecations to failures (`composer run tests:matrix`).
 *
 * Written in PHP 7.3 syntax on purpose.
 */

if (PHP_SAPI !== 'cli') {
    // physical *.php files are reachable over HTTP (see .htaccess); this one is a command line tool only
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
chdir($root);

$reportFile = 'audit-php83-blockers.txt';
$phpcs = 'vendor/bin/phpcs';
if (!is_file($phpcs)) {
    fwrite(STDERR, "vendor/bin/phpcs not found - run `composer install` first\n");
    exit(2);
}

/**
 * @param string $phpcs
 * @param string $testVersion PHPCompatibility testVersion, e.g. "7.3-8.3"
 * @return array<int, array{file: string, line: int, type: string, message: string, source: string}>
 */
function audit_scan($phpcs, $testVersion)
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpcs)
        . ' --standard=phpcs.xml.dist --report=json -q --no-colors'
        . ' --runtime-set testVersion ' . escapeshellarg($testVersion);
    $output = array();
    $code = 0;
    exec($command, $output, $code);
    $json = json_decode(implode("\n", $output), true);
    if (!is_array($json) || !isset($json['files'])) {
        fwrite(STDERR, "phpcs did not return a JSON report for testVersion {$testVersion} (exit {$code}):\n");
        fwrite(STDERR, implode("\n", array_slice($output, 0, 20)) . "\n");
        exit(2);
    }

    $findings = array();
    $prefix = str_replace('\\', '/', getcwd()) . '/';
    foreach ($json['files'] as $file => $info) {
        $file = str_replace('\\', '/', $file);
        if (strpos($file, $prefix) === 0) {
            $file = substr($file, strlen($prefix));
        }
        foreach ($info['messages'] as $message) {
            $findings[] = array(
                'file' => $file,
                'line' => (int) $message['line'],
                'type' => (string) $message['type'],
                'message' => (string) $message['message'],
                'source' => (string) $message['source'],
            );
        }
    }
    usort($findings, function ($a, $b) {
        return strcmp($a['file'], $b['file']) ?: $a['line'] - $b['line'];
    });

    return $findings;
}

/**
 * @param array<int, array{file: string, line: int, type: string, message: string, source: string}> $findings
 * @return string
 */
function audit_format(array $findings)
{
    if (count($findings) === 0) {
        return "none\n";
    }
    $files = array();
    $errors = 0;
    $lines = '';
    foreach ($findings as $finding) {
        $files[$finding['file']] = true;
        if ($finding['type'] === 'ERROR') {
            $errors++;
        }
        $lines .= sprintf(
            "%s:%d  [%s] %s (%s)\n",
            $finding['file'],
            $finding['line'],
            $finding['type'],
            $finding['message'],
            $finding['source']
        );
    }

    return sprintf(
        "%d error(s), %d warning(s) in %d file(s)\n%s",
        $errors,
        count($findings) - $errors,
        count($files),
        $lines
    );
}

$versionOutput = array();
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpcs) . ' --version', $versionOutput);

$blockers = audit_scan($phpcs, '7.3-8.3');
$openEnded = audit_scan($phpcs, '7.3-');

// headroom = open-ended findings that the 7.3 - 8.3 scan does not already report
$known = array();
foreach ($blockers as $finding) {
    $known[$finding['file'] . ':' . $finding['line'] . ':' . $finding['source']] = true;
}
$headroom = array();
foreach ($openEnded as $finding) {
    if (!isset($known[$finding['file'] . ':' . $finding['line'] . ':' . $finding['source']])) {
        $headroom[] = $finding;
    }
}

$report = "Cardeco PHP compatibility audit (TODO.md 1.2)\n"
    . 'generated ' . date('Y-m-d H:i:s T') . ' with PHP ' . PHP_VERSION . ', '
    . (isset($versionOutput[0]) ? $versionOutput[0] : 'PHP_CodeSniffer') . "\n"
    . "ruleset phpcs.xml.dist (PHPCompatibility); regenerate with `composer run audit:php83`\n"
    . "\n== 1. BLOCKERS: must work on every PHP version from 7.3 to 8.3 ==\n"
    . audit_format($blockers)
    . "\n== 2. HEADROOM: additionally reported for PHP 8.4 and later (informational) ==\n"
    . audit_format($headroom);

file_put_contents($reportFile, $report);
echo $report;
echo "\nwritten to {$reportFile}\n";

exit(count($blockers) === 0 ? 0 : 1);
