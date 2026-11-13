<?php

declare(strict_types=1);

namespace test\Smoke;

use PHPUnit\Framework\TestCase;
use test\Support\HttpRequests;

/**
 * HTTP smoke test over every page linked from the navigation (all four routing tiers).
 *
 * Runs only when APP_BASE_URL is set (the Docker test container starts Apache and sets
 * APP_BASE_URL=http://localhost). Catches Linux case-sensitivity problems, missing includes and
 * fatal errors on page load that no unit test can see.
 */
final class RouteSmokeTest extends TestCase
{
    use HttpRequests;

    /** Routes linked from inc/html/nav.html plus the default route. */
    const NAV_ROUTES = [
        '/',
        '/index',
        '/lazada',
        '/bigseller',
        '/shopee',
        '/tiktok',
        '/liststock',
        '/updateStock',
        '/ItemManager',
        '/bookmarks',
        '/image_export',
        '/stockreport',
        '/counting',
        '/catalog',
        '/LzdItemManager',
        '/RecordInserter',
        '/RecordInserterCSV',
        '/RecordEditor',
        '/RecordUpdater',
        '/SkuManager',
        '/Cashsale',
        '/CashSaleSqlImport',
        '/ShippedItemReport',
        '/fee_statement',
    ];

    /** @var string */
    private $baseUrl = '';

    protected function setUp(): void
    {
        parent::setUp();
        $baseUrl = getenv('APP_BASE_URL');
        if ($baseUrl === false || $baseUrl === '') {
            $this->markTestSkipped('APP_BASE_URL not set - run via `composer run tests:docker`.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** @return array<string, array<int, string>> */
    public function navRouteProvider(): array
    {
        $cases = [];
        foreach (self::NAV_ROUTES as $route) {
            $cases[$route] = [$route];
        }

        return $cases;
    }

    /**
     * @dataProvider navRouteProvider
     */
    public function testGet_NavRoute_Returns200WithoutPhpErrors(string $route): void
    {
        $response = $this->get($route);

        // display_errors is on in the dev/test image; Apache prints the messages as HTML (<b>Warning</b>: ...)
        $this->assertNoPhpErrors($response['body'], "GET {$route}");
        $this->assertSame(200, $response['status'], "GET {$route} returned HTTP {$response['status']}");
        $this->assertStringContainsString('<nav', $response['body'], "GET {$route}: layout (nav) missing");
    }

    public function testGet_UnknownRoute_Returns404(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $this->assertSame(404, $response['status']);
    }

    public function testGet_PathTraversal_Returns404(): void
    {
        // encoded "..": must never resolve to a file outside request_handler/
        $response = $this->get('/%2e%2e/db/conn_staff');

        $this->assertContains($response['status'], [400, 404], 'traversal attempt must not be served');
    }

    public function testGet_AjaxEndpoint_ServedDirectlyByApache(): void
    {
        $response = $this->get('/ajax/stock_items/getItemCode.php?itemCode=ZZ');

        $this->assertSame(200, $response['status']);
        $this->assertNotNull(json_decode($response['body'], true), 'ajax endpoint must return JSON');
    }

    /**
     * @return array{status: int, body: string}
     */
    private function get(string $route): array
    {
        $ch = curl_init($this->baseUrl . $route);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => false,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->fail("GET {$route} failed: {$error}");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $body];
    }
}
