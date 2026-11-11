<?php

namespace App\Routing;

/**
 * Maps a request path to one of the routing tiers described in AGENTS.md section 3, with exactly the
 * precedence router.php has always used:
 *
 *   1. `/` (or empty)                          -> App\Handler\LazadaHandler
 *   2. App\Handler\{Ucfirst segments}Handler  -> Tier 1 class, if it exists   (/my/new/page -> My\New\PageHandler)
 *   3. request_handler{path}.php               -> Tier 2 script                (directory -> index.php)
 *   4. legacy_pages/request_handler{path}.php  -> Tier 3 legacy page
 *   5. otherwise                               -> 404
 *
 * Pure function of (path, filesystem, class table) so it can be unit-tested without HTTP or a database.
 */
final class RouteResolver
{
    const DEFAULT_HANDLER = 'App\\Handler\\LazadaHandler';

    /** @var string absolute project root, without trailing slash */
    private $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
    }

    /**
     * @param string|null $path the path component of the request URI (parse_url(..., PHP_URL_PATH))
     */
    public function resolve($path): Route
    {
        if ($path === null || $path === false || $path === '' || $path === '/') {
            return Route::handler(self::DEFAULT_HANDLER);
        }
        $path = (string) $path;

        // hardening: never let a path escape request_handler/ (Apache already rejects most of these)
        if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
            return Route::notFound('request_handler' . $path);
        }

        $script = 'request_handler' . $path;
        if (is_dir($this->rootDir . '/' . $script)) {
            $script .= 'index.php';
        } else {
            $script .= '.php';
        }

        $handlerClass = self::handlerClassFor($path);
        if ($handlerClass !== null && class_exists($handlerClass)) {
            return Route::handler($handlerClass);
        }

        if (is_file($this->rootDir . '/' . $script)) {
            return Route::script($script);
        }

        $legacy = 'legacy_pages/' . $script;
        if (is_file($this->rootDir . '/' . $legacy)) {
            return Route::legacy($legacy);
        }

        return Route::notFound($script);
    }

    /**
     * `/bigseller` -> App\Handler\BigsellerHandler, `/my/new/page` -> App\Handler\My\New\PageHandler.
     * Returns null when a segment cannot be part of a PHP class name.
     *
     * @return string|null
     */
    public static function handlerClassFor(string $path)
    {
        $segments = explode('/', trim($path, '/'));
        foreach ($segments as $i => $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return null;
            }
            $segments[$i] = ucfirst($segment);
        }

        return 'App\\Handler\\' . implode('\\', $segments) . 'Handler';
    }
}
