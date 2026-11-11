<?php

declare(strict_types=1);

namespace test\Integration\Orders;

use Orders\MonthlyRecord;
use Orders\RecordUpdater;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Orders\RecordUpdater: stamping `orders.dateStockOut` by tracking number and reading the
 * rows back as MonthlyRecords.
 *
 * Order numbers here are 'ZZTEST' + hex suffix WITHOUT the '-' of the fixture prefix: getRecordsByTrackingNum()
 * feeds them through Record::setOrderNum(), which rejects anything but [a-zA-Z0-9\s].
 */
final class RecordUpdaterTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    private function orderNum(string $tag): string
    {
        return 'ZZTEST' . $this->suffix . $tag;
    }

    private function trackingNum(string $tag): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    private function monthlyRecord(string $trackingNum, string $dateStockOut): MonthlyRecord
    {
        $m = new MonthlyRecord();
        $m->setTrackingNum($trackingNum);
        $m->setDateStockOut($dateStockOut);

        return $m;
    }

    /** @return array<int, string|null> dateStockOut of the given order ids, in id order */
    private function dateStockOutOf(array $orderIds): array
    {
        $out = [];
        foreach ($orderIds as $id) {
            $rows = $this->fetchAll('SELECT dateStockOut FROM orders WHERE id = ?', 'i', $id);
            $out[] = $rows[0]['dateStockOut'];
        }

        return $out;
    }

    // ------------------------------------------------------------------ update()

    public function testUpdate_StampsDateStockOutOnEveryLineWithTheTrackingNumOnly(): void
    {
        $t1a = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1')]);
        $t1b = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1')]);
        $t2 = $this->insertOrder(['orderNum' => $this->orderNum('B'), 'trackingNum' => $this->trackingNum('T2')]);
        $records = [$this->monthlyRecord($this->trackingNum('T1'), '2024-02-01')];

        (new RecordUpdater())->update($this->con, $records);

        $this->assertSame(['2024-02-01', '2024-02-01', null], $this->dateStockOutOf([$t1a, $t1b, $t2]));
    }

    public function testUpdate_AcceptsDayMonthYearInputAndStoresIsoDate(): void
    {
        $id = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1')]);
        // MonthlyRecord::setDateStockOut() normalises '15/02/2024' to '15-Feb-2024'; update() re-formats to 'Y-m-d'
        $records = [$this->monthlyRecord($this->trackingNum('T1'), '15/02/2024')];

        (new RecordUpdater())->update($this->con, $records);

        $this->assertSame(['2024-02-15'], $this->dateStockOutOf([$id]));
    }

    public function testUpdate_OverwritesAnExistingDateStockOut(): void
    {
        $id = $this->insertOrder([
            'orderNum' => $this->orderNum('A'),
            'trackingNum' => $this->trackingNum('T1'),
            'dateStockOut' => '2024-01-01',
        ]);
        $records = [$this->monthlyRecord($this->trackingNum('T1'), '2024-02-01')];

        (new RecordUpdater())->update($this->con, $records);

        $this->assertSame(['2024-02-01'], $this->dateStockOutOf([$id]));
    }

    public function testUpdate_UnknownTrackingNum_ChangesNothingAndDoesNotThrow(): void
    {
        $id = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1')]);
        $records = [$this->monthlyRecord($this->trackingNum('NOPE'), '2024-02-01')];

        (new RecordUpdater())->update($this->con, $records);

        $this->assertSame([null], $this->dateStockOutOf([$id]));
    }

    /**
     * update() has no guard on an empty tracking number (RecordUpdater.php:43-56): `WHERE trackingNum = ''`
     * stamps EVERY order that has no tracking number yet. request_handler/RecordUpdater.php:81 passes the
     * unfiltered CSV list, so a blank first column in the upload reaches this statement.
     */
    public function testUpdate_BlankTrackingNum_StampsEveryOrderWithoutTrackingNumber_documentsCurrentBehaviour(): void
    {
        $blank1 = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => '']);
        $blank2 = $this->insertOrder(['orderNum' => $this->orderNum('B'), 'trackingNum' => '']);
        $tracked = $this->insertOrder(['orderNum' => $this->orderNum('C'), 'trackingNum' => $this->trackingNum('T9')]);
        $records = [$this->monthlyRecord('', '2024-02-01')];

        (new RecordUpdater())->update($this->con, $records);

        $this->assertSame(['2024-02-01', '2024-02-01', null], $this->dateStockOutOf([$blank1, $blank2, $tracked]));
    }

    /**
     * @group known-bug
     * RecordUpdater.php:43-56 - a MonthlyRecord with an empty tracking number must be skipped, not used as a filter.
     */
    public function testUpdate_BlankTrackingNum_IsSkipped(): void
    {
        $blank1 = $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => '']);
        $blank2 = $this->insertOrder(['orderNum' => $this->orderNum('B'), 'trackingNum' => '']);
        $records = [$this->monthlyRecord('', '2024-02-01')];

        (new RecordUpdater())->update($this->con, $records);

        $this->assertSame([null, null], $this->dateStockOutOf([$blank1, $blank2]));
    }

    // ------------------------------------------------------------------ getRecordsByTrackingNum()

    public function testGetRecordsByTrackingNum_ReturnsOneMonthlyRecordPerOrderLine(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1')]);
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1'), 'dateStockOut' => '2024-02-01']);
        $this->insertOrder(['orderNum' => $this->orderNum('B'), 'trackingNum' => $this->trackingNum('T2')]);
        $this->insertOrder(['orderNum' => $this->orderNum('C'), 'trackingNum' => $this->trackingNum('T3')]);

        $list = (new RecordUpdater())->getRecordsByTrackingNum($this->con, [$this->trackingNum('T1'), $this->trackingNum('T2')]);

        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(MonthlyRecord::class, $list);

        $lines = [];
        foreach ($list as $m) {
            // dateStockOut: NULL stays null, a date is re-formatted to Record::DATE_FORMAT ('d-M-Y')
            $lines[] = $m->orderNum . '|' . $m->trackingNum . '|' . ($m->dateStockOut === null ? 'NULL' : $m->dateStockOut);
        }
        sort($lines);
        $this->assertSame([
            $this->orderNum('A') . '|' . $this->trackingNum('T1') . '|01-Feb-2024',
            $this->orderNum('A') . '|' . $this->trackingNum('T1') . '|NULL',
            $this->orderNum('B') . '|' . $this->trackingNum('T2') . '|NULL',
        ], $lines);
    }

    public function testGetRecordsByTrackingNum_EmptyInput_ReturnsEmptyArrayWithoutQuerying(): void
    {
        $this->assertSame([], (new RecordUpdater())->getRecordsByTrackingNum($this->con, []));
    }

    public function testGetRecordsByTrackingNum_UnknownNumbers_ReturnsEmptyArray(): void
    {
        $this->insertOrder(['orderNum' => $this->orderNum('A'), 'trackingNum' => $this->trackingNum('T1')]);

        $list = (new RecordUpdater())->getRecordsByTrackingNum($this->con, [$this->trackingNum('NOPE'), $this->trackingNum('NADA')]);

        $this->assertSame([], $list);
    }
}
