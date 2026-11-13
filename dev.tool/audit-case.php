<?php

/**
 * WAMP (case-insensitive NTFS) vs LAMP (case-sensitive ext4/overlayfs) audit - TODO.md 1.4, AGENTS.md section 2.
 *
 *   php dev.tool/audit-case.php             exit 1 when a reference only resolves on a case-insensitive filesystem
 *   php dev.tool/audit-case.php --verbose   also list targets that exist in no casing at all, and dynamic includes
 *
 * Compared against the exact on-disk casing (works the same on Windows and Linux):
 *   A) every static include / require target
 *   B) every local URL-ish reference in PHP / HTML / JS (href, src, action, Location:, fetch(), $.get(), url: ...)
 *      - a path with a file extension must be a physical file
 *      - an extension-less path is a route and is resolved like App\Routing\RouteResolver:
 *        Tier 1 src/Handler/<Ucfirst>Handler.php, Tier 2 request_handler/, Tier 3 legacy_pages/request_handler/
 *
 * Written in PHP 7.3 syntax on purpose: it must also run on the WAMP production host.
 */

if (PHP_SAPI !== 'cli') {
    // physical *.php files are reachable over HTTP (see .htaccess); this one is a command line tool only
    http_response_code(404);
    exit;
}

chdir(dirname(__DIR__));

$verbose = in_array('--verbose', $argv, true);

/** Not application code: third-party, uploads, dev tools and their output, and tests (fixture markup that is never served). */
$excludedPrefixes = array('vendor/', 'node_modules/', 'public/', 'upload/', '.git/', 'tests/', 'js/vendor/', 'dev.tool/', 'e2e/', 'analysis/');

/**
 * @return string[] tracked + untracked-but-not-ignored paths; a directory walk when git is unavailable
 */
function audit_files()
{
    $devNull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $raw = @shell_exec('git ls-files -z --cached --others --exclude-standard 2>' . $devNull);
    if (is_string($raw) && $raw !== '') {
        return array_values(array_filter(explode("\0", $raw), 'strlen'));
    }

    $files = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), 1)), '/');
        }
    }

    return $files;
}

/**
 * @param string $path
 * @return string the path without empty, "." and ".." segments, forward slashes, no leading slash
 */
function audit_normalize($path)
{
    $segments = array();
    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

/**
 * Resolves $path segment by segment against real directory listings, so the answer does not depend on
 * whether the filesystem itself is case-sensitive.
 *
 * @param string $path normalized, relative to the project root
 * @return array{0: string, 1: string} status ('ok' | 'case' | 'missing') and the path as it is spelled on disk
 */
function audit_resolve($path)
{
    static $listings = array();

    $current = '.';
    $onDisk = array();
    $status = 'ok';
    foreach (explode('/', $path) as $segment) {
        if (!isset($listings[$current])) {
            $entries = is_dir($current) ? scandir($current) : false;
            $listings[$current] = $entries === false ? array() : $entries;
        }
        $actual = null;
        if (in_array($segment, $listings[$current], true)) {
            $actual = $segment;
        } else {
            foreach ($listings[$current] as $entry) {
                if (strcasecmp($entry, $segment) === 0) {
                    $actual = $entry;
                    $status = 'case';
                    break;
                }
            }
        }
        if ($actual === null) {
            return array('missing', $path);
        }
        $onDisk[] = $actual;
        $current .= '/' . $actual;
    }

    return array($status, implode('/', $onDisk));
}

/**
 * @param string[] $candidates
 * @return array{0: string, 1: string} best status over all candidates and a description for the report
 */
function audit_classify(array $candidates)
{
    $caseHit = null;
    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        list($status, $onDisk) = audit_resolve($candidate);
        if ($status === 'ok') {
            return array('ok', $candidate);
        }
        if ($status === 'case' && $caseHit === null) {
            $caseHit = $candidate . '  (on disk: ' . $onDisk . ')';
        }
    }
    if ($caseHit !== null) {
        return array('case', $caseHit);
    }

    return array('missing', implode(' | ', $candidates));
}

/**
 * @param string $route normalized extension-less path, e.g. "RecordEditor" or "my/new/page"
 * @return string[] the files App\Routing\RouteResolver would accept for it, in tier order
 */
function audit_route_candidates($route)
{
    $segments = array_map('ucfirst', explode('/', $route));

    return array(
        'src/Handler/' . implode('/', $segments) . 'Handler.php',
        'request_handler/' . $route . '.php',
        'legacy_pages/request_handler/' . $route . '.php',
    );
}

// ---------------------------------------------------------------------------------------------------------------

$report = array('case' => array(), 'missing' => array(), 'dynamic' => array());
$stats = array('files' => 0, 'includes' => 0, 'urls' => 0);

foreach (audit_files() as $file) {
    if (preg_match('/\.(php|html|js)$/i', $file) !== 1 || !is_file($file)) {
        continue;
    }
    $skip = false;
    foreach ($excludedPrefixes as $prefix) {
        if (strpos($file, $prefix) === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $stats['files']++;
    $source = (string) file_get_contents($file);
    $dir = dirname($file) === '.' ? '' : dirname($file);

    // ---- A) include / require ------------------------------------------------------------------------------
    if (preg_match('/\.(php|html)$/i', $file) === 1 && strpos($source, '<?') !== false) {
        $tokens = @token_get_all($source);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token) || !in_array($token[0], array(T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE), true)) {
                continue;
            }
            $line = $token[2];
            $isStatic = true;
            $usesDir = false;
            $literal = '';
            $dynamicParts = array();
            for ($k = $i + 1; $k < $count; $k++) {
                $part = $tokens[$k];
                if ($part === ';' || (is_array($part) && $part[0] === T_CLOSE_TAG)) {
                    break;
                }
                if (is_array($part)) {
                    if ($part[0] === T_WHITESPACE || $part[0] === T_COMMENT || $part[0] === T_DOC_COMMENT) {
                        continue;
                    }
                    if ($part[0] === T_DIR) {
                        $usesDir = true;
                        continue;
                    }
                    if ($part[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $literal .= substr($part[1], 1, -1);
                        continue;
                    }
                    $isStatic = false;
                    $dynamicParts[] = $part[1];
                } elseif ($part !== '(' && $part !== ')' && $part !== '.') {
                    $isStatic = false;
                    $dynamicParts[] = $part;
                }
            }
            $stats['includes']++;
            if (!$isStatic || $literal === '') {
                $report['dynamic'][] = $file . ':' . $line . '  include ' . trim($literal . ' ' . implode('', $dynamicParts));
                continue;
            }
            // __DIR__-anchored paths are relative to the file; bare paths are tried against the project root
            // (router.php scope) and against the including file's directory (PHP's own fallback)
            $candidates = $usesDir
                ? array(audit_normalize($dir . '/' . $literal))
                : array(audit_normalize($literal), audit_normalize($dir . '/' . $literal));
            list($status, $info) = audit_classify(array_values(array_unique($candidates)));
            if ($status !== 'ok') {
                $report[$status][] = $file . ':' . $line . "  include '" . $literal . "'  => " . $info;
            }
        }
    }

    // ---- B) URL-ish references ------------------------------------------------------------------------------
    $references = array();
    $patterns = array(
        '/\b(?:href|src|action|formaction)\s*=\s*(["\'])([^"\'<>{}$]+)\1/i' => 2,
        '/Location:\s*([^"\'<>{}$\s;]+)/i' => 1,
        '/\b(?:fetch|open|\$\.get|\$\.post|\$\.getJSON|load)\s*\(\s*(["\'`])([^"\'`<>{}$]+)\1/' => 2,
        '/\burl\s*:\s*(["\'`])([^"\'`<>{}$]+)\1/' => 2,
        '/location(?:\.href)?\s*=\s*(["\'`])([^"\'`<>{}$]+)\1/' => 2,
    );
    foreach ($patterns as $pattern => $group) {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[$group] as $match) {
                $references[] = $match;
            }
        }
    }
    foreach ($references as $reference) {
        $url = trim($reference[0]);
        if ($url === '' || $url[0] === '#' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url) === 1) {
            continue; // anchors, absolute URLs, mailto:, javascript:, data:, ...
        }
        $path = (string) preg_replace('/[?#].*$/', '', $url);
        if ($path === '' || $path === '/' || $path === '.' || $path === './') {
            continue;
        }
        $stats['urls']++;
        $line = substr_count($source, "\n", 0, $reference[1]) + 1;
        $normalized = audit_normalize($path);
        $candidates = array($normalized);
        if ($path[0] !== '/' && $dir !== '' && preg_match('#^(process|ajax|css|js)(/|$)#', $dir) === 1) {
            // direct endpoints (Tier 4) and assets resolve relative URLs against their own directory
            $candidates[] = audit_normalize($dir . '/' . $path);
        }
        if (preg_match('/\.[A-Za-z0-9]{1,5}$/', $normalized) !== 1) {
            $candidates = array_merge($candidates, audit_route_candidates($normalized));
        }
        list($status, $info) = audit_classify(array_values(array_unique($candidates)));
        if ($status === 'case') {
            $report['case'][] = $file . ':' . $line . "  url '" . $url . "'  => " . $info;
        } elseif ($status === 'missing') {
            $report['missing'][] = $file . ':' . $line . "  url '" . $url . "'";
        }
    }
}

foreach ($report as $kind => $rows) {
    $report[$kind] = array_values(array_unique($rows));
}

printf(
    "case audit: %d files, %d include/require statements, %d local URL references\n",
    $stats['files'],
    $stats['includes'],
    $stats['urls']
);
printf("  case mismatches (work on Windows, break on Linux): %d\n", count($report['case']));
foreach ($report['case'] as $row) {
    echo '    ', $row, "\n";
}
printf("  targets found in no casing (e.g. the untracked legacy process/_*.php partials): %d\n", count($report['missing']));
printf("  dynamic includes (not checkable statically): %d\n", count($report['dynamic']));
if ($verbose) {
    foreach (array('missing', 'dynamic') as $kind) {
        foreach ($report[$kind] as $row) {
            echo '    [', $kind, '] ', $row, "\n";
        }
    }
} elseif (count($report['missing']) + count($report['dynamic']) > 0) {
    echo "  (run with --verbose to list them)\n";
}

exit(count($report['case']) === 0 ? 0 : 1);
