<?php

declare(strict_types=1);

namespace test\Unit\Routing;

use App\Routing\Route;
use App\Routing\RouteResolver;
use PHPUnit\Framework\TestCase;

/**
 * The routing precedence of router.php (AGENTS.md section 3), checked against a scratch project tree.
 */
final class RouteResolverTest extends TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/cardeco-router-' . uniqid();
        mkdir($this->root . '/request_handler/sub', 0777, true);
        mkdir($this->root . '/legacy_pages/request_handler', 0777, true);
        file_put_contents($this->root . '/request_handler/RecordEditor.php', '<?php');
        file_put_contents($this->root . '/request_handler/sub/index.php', '<?php');
        file_put_contents($this->root . '/legacy_pages/request_handler/liststock.php', '<?php');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    private function resolver(): RouteResolver
    {
        return new RouteResolver($this->root);
    }

    /** @return array<string, array{0: mixed}> */
    public function defaultPathProvider(): array
    {
        return ['slash' => ['/'], 'empty' => [''], 'null' => [null], 'false' => [false]];
    }

    /**
     * @dataProvider defaultPathProvider
     */
    public function testResolve_DefaultRoute_IsLazadaHandler($path): void
    {
        $route = $this->resolver()->resolve($path);

        $this->assertSame(Route::TIER_HANDLER, $route->tier);
        $this->assertSame('App\\Handler\\LazadaHandler', $route->target);
        $this->assertTrue($route->isHandler());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function tier1Provider(): array
    {
        return [
            'lazada' => ['/lazada', 'App\\Handler\\LazadaHandler'],
            'index' => ['/index', 'App\\Handler\\IndexHandler'],
            'bigseller (ucfirst)' => ['/bigseller', 'App\\Handler\\BigsellerHandler'],
            'tiktok' => ['/tiktok', 'App\\Handler\\TiktokHandler'],
            'shopee' => ['/shopee', 'App\\Handler\\ShopeeHandler'],
            'ShippedItemReport (case kept)' => ['/ShippedItemReport', 'App\\Handler\\ShippedItemReportHandler'],
        ];
    }

    /**
     * @dataProvider tier1Provider
     */
    public function testResolve_ExistingHandlerClass_IsTier1(string $path, string $class): void
    {
        $route = $this->resolver()->resolve($path);

        $this->assertSame(Route::TIER_HANDLER, $route->tier, $path);
        $this->assertSame($class, $route->target);
    }

    public function testResolve_HandlerClassWinsOverExistingScript(): void
    {
        // a Tier-1 class AND a Tier-2 file both exist for /lazada in the real project history: class wins
        file_put_contents($this->root . '/request_handler/lazada.php', '<?php');

        $route = $this->resolver()->resolve('/lazada');

        $this->assertSame(Route::TIER_HANDLER, $route->tier);
    }

    public function testResolve_ScriptUnderRequestHandler_IsTier2(): void
    {
        $route = $this->resolver()->resolve('/RecordEditor');

        $this->assertSame(Route::TIER_SCRIPT, $route->tier);
        $this->assertSame('request_handler/RecordEditor.php', $route->target);
    }

    public function testResolve_IsCaseSensitiveLikeLinux(): void
    {
        $route = $this->resolver()->resolve('/recordeditor');

        $this->assertSame(Route::TIER_NOT_FOUND, $route->tier, 'request_handler/recordeditor.php does not exist on a case-sensitive FS');
    }

    public function testResolve_DirectoryWithTrailingSlash_UsesIndexPhp(): void
    {
        $route = $this->resolver()->resolve('/sub/');

        $this->assertSame(Route::TIER_SCRIPT, $route->tier);
        $this->assertSame('request_handler/sub/index.php', $route->target);
    }

    public function testResolve_DirectoryWithoutTrailingSlash_DocumentsLegacyQuirk(): void
    {
        // legacy behaviour: 'request_handler/sub' . 'index.php' => "subindex.php" (no separator) -> 404
        $route = $this->resolver()->resolve('/sub');

        $this->assertSame(Route::TIER_NOT_FOUND, $route->tier);
        $this->assertSame('request_handler/subindex.php', $route->target);
    }

    public function testResolve_LegacyPage_IsTier3(): void
    {
        $route = $this->resolver()->resolve('/liststock');

        $this->assertSame(Route::TIER_LEGACY, $route->tier);
        $this->assertSame('legacy_pages/request_handler/liststock.php', $route->target);
    }

    public function testResolve_ScriptWinsOverLegacyPage(): void
    {
        file_put_contents($this->root . '/request_handler/liststock.php', '<?php');

        $route = $this->resolver()->resolve('/liststock');

        $this->assertSame(Route::TIER_SCRIPT, $route->tier);
    }

    public function testResolve_Unknown_IsNotFoundWithLookedUpPath(): void
    {
        $route = $this->resolver()->resolve('/nope');

        $this->assertSame(Route::TIER_NOT_FOUND, $route->tier);
        $this->assertSame('request_handler/nope.php', $route->target, 'used in the 404 message');
        $this->assertFalse($route->isHandler());
    }

    /** @return array<string, array{0: string}> */
    public function traversalProvider(): array
    {
        return [
            'dot-dot' => ['/../db/conn_staff'],
            'nested dot-dot' => ['/x/../../vendor/autoload'],
            'nul byte' => ["/lazada\0"],
        ];
    }

    /**
     * @dataProvider traversalProvider
     */
    public function testResolve_PathTraversal_IsNotFound(string $path): void
    {
        $route = $this->resolver()->resolve($path);

        $this->assertSame(Route::TIER_NOT_FOUND, $route->tier);
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public function classNameProvider(): array
    {
        return [
            'single segment' => ['/bigseller', 'App\\Handler\\BigsellerHandler'],
            'nested' => ['/my/new/page', 'App\\Handler\\My\\New\\PageHandler'],
            'trailing slash ignored' => ['/shopee/', 'App\\Handler\\ShopeeHandler'],
            'dash is not a class name' => ['/fee-statement', null],
            'dot is not a class name' => ['/x.php', null],
            'digit first is not a class name' => ['/1abc', null],
        ];
    }

    /**
     * @dataProvider classNameProvider
     */
    public function testHandlerClassFor_DerivesPsr4ClassName(string $path, $expected): void
    {
        $this->assertSame($expected, RouteResolver::handlerClassFor($path));
    }
}
