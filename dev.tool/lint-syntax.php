<?php

/**
 * Real `php -l` gate (TODO.md 5.1 / 5.2). Lints with the PHP binary that runs this script, so the same file
 * checks every runtime:
 *
 *   php dev.tool/lint-syntax.php                 first-party code (*.php + PHP-bearing *.html views, per git)
 *   php dev.tool/lint-syntax.php --with-vendor   ... plus the production (non-dev) Composer packages
 *   php7.3 dev.tool/lint-syntax.php --with-vendor        inside the dev container (composer run lint:php73)
 *   composer run lint:docker:73                          from the host, in the PHP 7.3 test image
 *
 * First-party files fail on a syntax error AND on any compile-time warning/deprecation.
 * Vendor files fail on syntax errors only.
 *
 * Written in PHP 7.3 syntax on purpose: it must also run on the WAMP production host.
 */

if (PHP_SAPI !== 'cli') {
    // physical *.php files are reachable over HTTP (see .htaccess); this one is a command line tool only
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
chdir($root);

$withVendor = in_array('--with-vendor', $argv, true);

/**
 * Directories that never contain first-party code. The tool-output directories matter when git is not available
 * (production image, a container that does not trust the bind-mounted repository) and the tree is walked instead.
 */
$excludedPrefixes = array('vendor/', 'node_modules/', 'public/', 'upload/', '.git/', 'analysis/', 'tests/codeCoverage/');

/**
 * Files that are PHP 8 only BY DESIGN and are never loaded by an older PHP (the package guards the
 * `require` with a PHP_VERSION_ID check). Only relevant when linting with PHP < 8.0.
 */
$versionGuardedVendorFiles = array(
    '#/symfony/polyfill-[a-z0-9-]+/bootstrap80\.php$#',
);

/**
 * @return string[]|null tracked + untracked-but-not-ignored paths relative to the project root,
 *                       or null when git is unavailable (e.g. inside the production image)
 */
function lint_git_files()
{
    $devNull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $raw = @shell_exec('git ls-files -z --cached --others --exclude-standard 2>' . $devNull);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    return array_values(array_filter(explode("\0", $raw), 'strlen'));
}

/**
 * @param string $dir path relative to the current directory ('.' for the root)
 * @return string[] every file below $dir, relative paths with forward slashes
 */
function lint_scan_files($dir)
{
    $files = array();
    if (!is_dir($dir)) {
        return $files;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $path = str_replace('\\', '/', $file->getPathname());
            $files[] = strpos($path, './') === 0 ? substr($path, 2) : $path;
        }
    }

    return $files;
}

/**
 * @param string $path
 * @param string[] $prefixes
 * @return bool
 */
function lint_has_prefix($path, array $prefixes)
{
    foreach ($prefixes as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * *.php always; *.html only when it embeds PHP (the views in view/ do, test fixtures do not).
 *
 * @param string $path
 * @return bool
 */
function lint_is_php_bearing($path)
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($extension === 'php') {
        return true;
    }
    if ($extension !== 'html') {
        return false;
    }
    $content = @file_get_contents($path);

    return is_string($content) && preg_match('/<\?(php|=)/', $content) === 1;
}

/**
 * @param string $file
 * @return array{0: int, 1: string} exit code and combined output of `php -l`
 */
function lint_run($file)
{
    $command = escapeshellarg(PHP_BINARY)
        . ' -d display_errors=1 -d error_reporting=-1 -d log_errors=0 -l '
        . escapeshellarg($file) . ' 2>&1';
    $output = array();
    $code = 1;
    exec($command, $output, $code);

    return array($code, trim(implode("\n", $output)));
}

/**
 * @return string[] *.php files of the production (non-dev) packages + Composer's own generated files
 */
function lint_vendor_files()
{
    $manifest = 'vendor/composer/installed.json';
    if (!is_file($manifest)) {
        fwrite(STDERR, "vendor/composer/installed.json not found - run `composer install` first\n");
        exit(2);
    }
    $installed = json_decode((string) file_get_contents($manifest), true);
    // Composer 2: {"packages": [...], "dev-package-names": [...]}; Composer 1: a plain list of packages
    $packages = isset($installed['packages']) ? $installed['packages'] : $installed;
    $devNames = isset($installed['dev-package-names']) ? $installed['dev-package-names'] : array();

    $files = array('vendor/autoload.php');
    foreach (glob('vendor/composer/*.php') ?: array() as $file) {
        $files[] = $file;
    }
    foreach ($packages as $package) {
        if (in_array($package['name'], $devNames, true)) {
            continue;
        }
        foreach (lint_scan_files('vendor/' . $package['name']) as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }
            // test suites and samples of a package are never loaded by the application
            if (preg_match('#/(tests?|samples|examples|docs)/#i', $file) === 1) {
                continue;
            }
            $files[] = $file;
        }
    }

    return $files;
}

// ---------------------------------------------------------------------------------------------------------------

$tracked = lint_git_files();
$candidates = $tracked !== null ? $tracked : lint_scan_files('.');

$firstParty = array();
foreach ($candidates as $path) {
    if (lint_has_prefix($path, $excludedPrefixes) || !is_file($path)) {
        continue;
    }
    if (lint_is_php_bearing($path)) {
        $firstParty[] = $path;
    }
}
sort($firstParty);

$failures = array();
foreach ($firstParty as $file) {
    list($code, $output) = lint_run($file);
    $hasDiagnostics = preg_match('/^(PHP )?(Deprecated|Warning|Notice|Fatal error|Parse error)/mi', $output) === 1;
    if ($code !== 0 || $hasDiagnostics) {
        $failures[] = $file . "\n    " . str_replace("\n", "\n    ", $output);
    }
}

$vendorCount = 0;
$skipped = array();
if ($withVendor) {
    foreach (lint_vendor_files() as $file) {
        if (PHP_VERSION_ID < 80000) {
            $isGuarded = false;
            foreach ($versionGuardedVendorFiles as $pattern) {
                if (preg_match($pattern, '/' . $file) === 1) {
                    $isGuarded = true;
                    break;
                }
            }
            if ($isGuarded) {
                $skipped[] = $file;
                continue;
            }
        }
        $vendorCount++;
        list($code, $output) = lint_run($file);
        if ($code !== 0) {
            $failures[] = $file . "\n    " . str_replace("\n", "\n    ", $output);
        }
    }
}

foreach ($failures as $failure) {
    echo 'FAIL ', $failure, "\n";
}
foreach ($skipped as $file) {
    echo 'skip ', $file, " (PHP 8 only by design, never loaded by PHP ", PHP_MAJOR_VERSION, '.', PHP_MINOR_VERSION, ")\n";
}
printf(
    "php -l with PHP %s: %d first-party file(s)%s, %d failed\n",
    PHP_VERSION,
    count($firstParty),
    $withVendor ? sprintf(' + %d vendor file(s)', $vendorCount) : '',
    count($failures)
);

exit(count($failures) === 0 ? 0 : 1);
