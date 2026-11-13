<?php

declare(strict_types=1);

namespace test\Smoke;

use test\Support\DatabaseTestCase;
use test\Support\HttpRequests;

/**
 * HTTP smoke test of the listing pages WITH data: RouteSmokeTest only opens every page without parameters, which
 * for most of them renders an empty table. Here the pages render real rows of the seeded database, on every PHP
 * version of the test matrix, so that a value the fixtures never contain (NULL in a nullable column, an item
 * without images, ...) cannot hide a PHP 8-only warning, deprecation or TypeError.
 *
 * Read-only: only GET requests; the routes are derived from what the seed contains. Needs Apache (APP_BASE_URL)
 * and the database.
 */
final class SeedDataSmokeTest extends DatabaseTestCase
{
    use HttpRequests;

    /** @var string */
    private $baseUrl = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = $this->baseUrlOrSkip();
    }

    /**
     * @return string|null the single value of a one-row, one-column query aliased `v`
     */
    private function scalar(string $sql): ?string
    {
        $rows = $this->fetchAll($sql);
        if (count($rows) === 0 || $rows[0]['v'] === null) {
            return null;
        }

        return (string) $rows[0]['v'];
    }

    private function assertPageRenders(string $route, bool $hasLayout = true): void
    {
        $response = $this->httpGet($this->baseUrl . $route);

        $this->assertNoPhpErrors($response['body'], "GET {$route}");
        $this->assertSame(200, $response['status'], "GET {$route} returned HTTP {$response['status']}");
        if ($hasLayout) {
            $this->assertStringContainsString('<nav', $response['body'], "GET {$route}: layout (nav) missing");
        }
    }

    /** @return array<string, array<int, string>> */
    public function stockRouteProvider(): array
    {
        $cases = [];
        foreach ([
            '/liststock?txtInput=a',
            '/liststock?txtInput=a&chkShowImage=on',
            '/liststock?page=2',
            '/liststock?page=2&chkShowImage=on',
            '/catalog?submit=Search&txtDescription=a',
            '/ItemManager?itemCode=a',
        ] as $route) {
            $cases[$route] = [$route];
        }

        return $cases;
    }

    /**
     * @dataProvider stockRouteProvider
     */
    public function testGet_StockAndCatalogSearches_RenderSeedRowsWithoutPhpErrors(string $route): void
    {
        $this->assertPageRenders($route);
    }

    public function testGet_ItemDetail_OfASeedItem_RendersWithoutPhpErrors(): void
    {
        $id = $this->scalar('SELECT MIN(id) AS v FROM stock_items');
        if ($id === null) {
            $this->markTestSkipped('the seed has no stock items');
        }

        $this->assertPageRenders('/itemdetail?id=' . $id);
    }

    public function testGet_Bookmark_OfTheSeed_RendersItsItemsWithoutPhpErrors(): void
    {
        $id = $this->scalar('SELECT MIN(bookmark_id) AS v FROM bookmarks_items');
        if ($id === null) {
            $this->markTestSkipped('the seed has no bookmarked items');
        }

        $this->assertPageRenders('/bookmarks?id=' . $id);
    }

    public function testGet_RecordEditor_NewestMonthOfTheSeed_RendersWithoutPhpErrors(): void
    {
        $month = $this->newestOrderMonth();

        $this->assertPageRenders('/RecordEditor?RecordMonth=' . $month);
        $this->assertPageRenders('/RecordEditor?RecordMonth=' . $month . '&platformCharges=Lazada');
    }

    public function testGet_RecordEditorCsvExports_OfTheNewestMonth_DownloadWithoutPhpErrors(): void
    {
        $month = $this->newestOrderMonth();

        // process/RecordEditor/exportCSV.php and exportCSV_sqlImport.php answer with a file, not a page
        $this->assertPageRenders('/RecordEditor?RecordMonth=' . $month . '&exportCSV%5Bdefault%5D=1', false);
        $this->assertPageRenders('/RecordEditor?RecordMonth=' . $month . '&exportCSV%5BsqlImport%5D=1', false);
    }

    public function testGet_RecordUpdater_StockOutSummaryOfTheNewestDate_RendersWithoutPhpErrors(): void
    {
        $date = $this->scalar('SELECT MAX(dateStockOut) AS v FROM orders');
        if ($date === null) {
            $this->markTestSkipped('no order of the seed has a dateStockOut');
        }

        $this->assertPageRenders('/RecordUpdater?dateStockOut=' . $date);
    }

    private function newestOrderMonth(): string
    {
        $month = $this->scalar("SELECT DATE_FORMAT(MAX(`date`), '%Y-%m') AS v FROM orders");
        if ($month === null) {
            $this->markTestSkipped('the seed has no orders');
        }

        return (string) $month;
    }
}
