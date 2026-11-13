<?php

declare(strict_types=1);

namespace test\Smoke;

use test\Support\CsvFixture;
use test\Support\DatabaseTestCase;
use test\Support\HttpRequests;

/**
 * HTTP test of the /RecordUpdater upload (Tier 2 script request_handler/RecordUpdater.php): a header-less
 * "trackingNum,dateStockOut" CSV stamps orders.dateStockOut by tracking number.
 *
 * The script's own getMRecordsToOverWrite() cannot be unit-tested (it is declared inside the request script).
 * It is where the NULL dateStockOut of an order that is not stocked out yet - the normal case - was passed to
 * trim(): deprecated since PHP 8.1, so on PHP 8.3 only the page showed "Deprecated: ..." and answered HTTP 500.
 *
 * Needs Apache (APP_BASE_URL) AND the database. Apache reads through its own connection, so the fixtures are
 * committed ($useTransaction = false) and deleted again in tearDown().
 */
final class RecordUpdaterUploadTest extends DatabaseTestCase
{
    use HttpRequests;

    /** @var bool */
    protected $useTransaction = false;

    /** @var string */
    private $baseUrl = '';
    /** @var string */
    private $suffix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = $this->baseUrlOrSkip();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    protected function tearDown(): void
    {
        if ($this->con !== null && $this->suffix !== '') {
            $this->con->query("DELETE FROM orders WHERE orderNum LIKE 'ZZTEST" . $this->suffix . "%'");
        }
        parent::tearDown();
    }

    /** Order numbers must match [a-zA-Z0-9\s] (Record::setOrderNum), so no '-' here. */
    private function orderNum(string $tag): string
    {
        return 'ZZTEST' . $this->suffix . $tag;
    }

    private function trackingNum(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    private function dateStockOutOf(int $orderId): ?string
    {
        return $this->fetchAll('SELECT dateStockOut FROM orders WHERE id = ?', 'i', $orderId)[0]['dateStockOut'];
    }

    /**
     * @param array<int, array<int, string>> $rows trackingNum, dateStockOut
     * @return array{status: int, body: string}
     */
    private function upload(array $rows): array
    {
        return $this->httpPostFile($this->baseUrl . '/RecordUpdater', 'stockOut', CsvFixture::write($rows), 'zztest-stockout.csv');
    }

    public function testUpload_OrderNotStockedOutYet_IsStampedAndThePageShowsNoPhpErrors(): void
    {
        $id = $this->insertOrder([
            'orderNum' => $this->orderNum('A'),
            'date' => '1999-01-02',
            'trackingNum' => $this->trackingNum('T1'),
        ]);
        $this->assertNull($this->dateStockOutOf($id), 'fixture: dateStockOut starts as NULL');

        $response = $this->upload([[$this->trackingNum('T1'), '1999-01-20']]);

        $this->assertNoPhpErrors($response['body'], 'POST /RecordUpdater');
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Done. date stock out updated.', $response['body']);
        $this->assertSame('1999-01-20', $this->dateStockOutOf($id));
    }

    public function testUpload_OrderAlreadyStockedOutOnAnotherDate_IsRefusedAndNothingChanges(): void
    {
        $open = $this->insertOrder([
            'orderNum' => $this->orderNum('A'),
            'date' => '1999-01-02',
            'trackingNum' => $this->trackingNum('T1'),
        ]);
        $done = $this->insertOrder([
            'orderNum' => $this->orderNum('B'),
            'date' => '1999-01-02',
            'trackingNum' => $this->trackingNum('T2'),
            'dateStockOut' => '1999-01-05',
        ]);

        $response = $this->upload([
            [$this->trackingNum('T1'), '1999-01-20'],
            [$this->trackingNum('T2'), '1999-01-20'],
        ]);

        $this->assertNoPhpErrors($response['body'], 'POST /RecordUpdater');
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Some records have dateStockOut set already', $response['body']);
        $this->assertStringContainsString($this->orderNum('B'), $response['body']);
        $this->assertStringNotContainsString('Done. date stock out updated.', $response['body']);
        $this->assertNull($this->dateStockOutOf($open), 'the whole upload is refused');
        $this->assertSame('1999-01-05', $this->dateStockOutOf($done));
    }
}
