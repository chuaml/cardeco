<?php

declare(strict_types=1);

namespace test\Integration\Orders;

use Orders\RecordDeleter;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Orders\RecordDeleter: deleting an import batch by its orders_insert_log id and listing the
 * latest import logs.
 */
final class RecordDeleterTest extends DatabaseTestCase
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
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $tag;
    }

    /** Inserts an orders_insert_log row with unique hashes and returns its id. */
    private function insertLogRow(string $fileName): int
    {
        $md5 = md5(self::FIXTURE_PREFIX . uniqid('', true));
        $sha1 = sha1(self::FIXTURE_PREFIX . uniqid('', true));
        $stmt = $this->con->prepare('INSERT INTO orders_insert_log (md5, sha1, fileName, date) VALUES (?, ?, ?, CURDATE())');
        $stmt->bind_param('sss', $md5, $sha1, $fileName);
        $stmt->execute();
        $id = (int) $this->con->insert_id;
        $stmt->close();

        return $id;
    }

    /** @return array<int, array<string, mixed>> */
    private function ordersOfLog(int $insertLogId): array
    {
        return $this->fetchAll('SELECT id, orderNum FROM orders WHERE insertLogId = ? ORDER BY id', 'i', $insertLogId);
    }

    /** @return array<int, array<string, mixed>> */
    private function logRows(int $id): array
    {
        return $this->fetchAll('SELECT id, fileName FROM orders_insert_log WHERE id = ?', 'i', $id);
    }

    // ------------------------------------------------------------------ deleteByInsertedId()

    public function testDeleteByInsertedId_RemovesTheBatchOrdersAndItsLogRow(): void
    {
        $logId = $this->insertLogRow('ZZTEST-batch.csv');
        $this->insertOrder(['insertLogId' => $logId, 'orderNum' => $this->orderNum('O1')]);
        $this->insertOrder(['insertLogId' => $logId, 'orderNum' => $this->orderNum('O2')]);
        $this->assertCount(2, $this->ordersOfLog($logId));

        (new RecordDeleter())->deleteByInsertedId($this->con, $logId);

        $this->assertCount(0, $this->ordersOfLog($logId));
        $this->assertCount(0, $this->logRows($logId));
    }

    public function testDeleteByInsertedId_LeavesOtherBatchesUntouched(): void
    {
        $logId = $this->insertLogRow('ZZTEST-batch.csv');
        $otherLogId = $this->insertLogRow('ZZTEST-other.csv');
        $this->insertOrder(['insertLogId' => $logId, 'orderNum' => $this->orderNum('O1')]);
        $otherOrderId = $this->insertOrder(['insertLogId' => $otherLogId, 'orderNum' => $this->orderNum('O2')]);

        (new RecordDeleter())->deleteByInsertedId($this->con, $logId);

        $other = $this->ordersOfLog($otherLogId);
        $this->assertCount(1, $other);
        $this->assertSame($otherOrderId, (int) $other[0]['id']);
        $this->assertCount(1, $this->logRows($otherLogId));
    }

    public function testDeleteByInsertedId_UnknownId_IsANoOp(): void
    {
        $logId = $this->insertLogRow('ZZTEST-batch.csv');
        $this->insertOrder(['insertLogId' => $logId, 'orderNum' => $this->orderNum('O1')]);
        $unknown = $this->fetchAll('SELECT MAX(id) + 1000 AS id FROM orders_insert_log');

        (new RecordDeleter())->deleteByInsertedId($this->con, (int) $unknown[0]['id']);

        $this->assertCount(1, $this->ordersOfLog($logId));
        $this->assertCount(1, $this->logRows($logId));
    }

    // ------------------------------------------------------------------ selectAllOrdersInsertLog()

    public function testSelectAllOrdersInsertLog_ReturnsNewestLogFirstWithIdAndFileNameOnly(): void
    {
        $olderId = $this->insertLogRow('ZZTEST-older.csv');
        $newestId = $this->insertLogRow('ZZTEST-newest.csv');

        $list = (new RecordDeleter())->selectAllOrdersInsertLog($this->con);

        $this->assertSame(['id', 'fileName'], array_keys($list[0]));
        $this->assertSame($newestId, (int) $list[0]['id']);
        $this->assertSame('ZZTEST-newest.csv', $list[0]['fileName']);
        $this->assertSame($olderId, (int) $list[1]['id']);
        $this->assertSame('ZZTEST-older.csv', $list[1]['fileName']);
    }

    public function testSelectAllOrdersInsertLog_IsCappedAtTheFiftyNewestLogs(): void
    {
        $ids = [];
        for ($i = 0; $i < 51; ++$i) {
            $ids[] = $this->insertLogRow('ZZTEST-' . $this->suffix . '-' . $i . '.csv');
        }

        $list = (new RecordDeleter())->selectAllOrdersInsertLog($this->con);

        $this->assertCount(50, $list);
        $returnedIds = array_map('intval', array_column($list, 'id'));
        $this->assertSame(array_reverse(array_slice($ids, 1)), $returnedIds, 'the oldest of the 51 rows falls off the end');
        foreach ($list as $row) {
            $this->assertStringStartsWith('ZZTEST-' . $this->suffix . '-', $row['fileName']);
        }
    }
}
