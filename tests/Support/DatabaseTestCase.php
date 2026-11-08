<?php

declare(strict_types=1);

namespace test\Support;

use mysqli;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need the real MySQL schema.
 *
 * - one shared connection per PHP process (the legacy tests opened a fresh one per test and never closed it)
 * - every test runs inside a transaction that is rolled back in tearDown(), so fixtures inserted with the
 *   helpers below never leak between tests (requires InnoDB - guaranteed by db/upgrade.sql)
 * - skipped automatically when MYSQL_* is not configured (e.g. `composer run tests:unit` on the host)
 * - refuses to run against the production database name
 *
 * Classes under test that manage their own transaction (e.g. Database_Stock_Updater with autocommit(false))
 * must set `$useTransaction = false` and clean up by the `ZZTEST-` prefix in tearDown().
 */
abstract class DatabaseTestCase extends TestCase
{
    /** Prefix for every item code / sku / order number created by the fixture helpers. */
    const FIXTURE_PREFIX = 'ZZTEST-';

    /** @var mysqli|null */
    private static $sharedCon = null;

    /** @var mysqli */
    protected $con;

    /** @var bool */
    protected $useTransaction = true;

    public static function isDatabaseConfigured(): bool
    {
        return getenv('MYSQL_HOST') !== false && getenv('MYSQL_DB') !== false;
    }

    protected static function connection(): mysqli
    {
        if (self::$sharedCon === null) {
            $db = (string) getenv('MYSQL_DB');
            if ($db === 'cardeco' && getenv('CARDECO_ALLOW_PROD_DB') !== '1') {
                throw new RuntimeException('Refusing to run integration tests against the production database "cardeco".');
            }
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $con = new mysqli(
                (string) getenv('MYSQL_HOST'),
                (string) getenv('MYSQL_USER'),
                (string) getenv('MYSQL_PASSWORD'),
                $db
            );
            $con->set_charset('utf8mb4');
            self::$sharedCon = $con;
        }

        return self::$sharedCon;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::isDatabaseConfigured()) {
            $this->markTestSkipped('MYSQL_* environment not set - run `composer run tests:docker`.');
        }
        $this->con = self::connection();
        if ($this->useTransaction) {
            $this->con->begin_transaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->useTransaction && $this->con !== null) {
            $this->con->rollback();
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------------ fixture helpers

    /**
     * Inserts a stock_items row and its 1:1 `stock` quantity row. Returns stock_items.id.
     */
    protected function insertStockItem(string $itemCode, string $description, int $quantity, string $uom = 'UNIT', string $itemGroup = 'TEST'): int
    {
        $stmt = $this->con->prepare('INSERT INTO stock_items (item_code, description, uom, item_group, image) VALUES (?, ?, ?, ?, \'\')');
        $stmt->bind_param('ssss', $itemCode, $description, $uom, $itemGroup);
        $stmt->execute();
        $id = (int) $this->con->insert_id;
        $stmt->close();

        $stmt = $this->con->prepare('INSERT INTO stock (id, quantity) VALUES (?, ?)');
        $stmt->bind_param('ii', $id, $quantity);
        $stmt->execute();
        $stmt->close();

        return $id;
    }

    protected function insertSellerSku(string $itemCode, string $sku): int
    {
        $stmt = $this->con->prepare('INSERT INTO seller_sku (itemCode, sku) VALUES (?, ?)');
        $stmt->bind_param('ss', $itemCode, $sku);
        $stmt->execute();
        $id = (int) $this->con->insert_id;
        $stmt->close();

        return $id;
    }

    protected function insertBigSellerSkuMap(string $bigsellerSku, int $itemId): int
    {
        $stmt = $this->con->prepare('INSERT INTO bigseller_sku_map (bigseller_sku, item_id) VALUES (?, ?)');
        $stmt->bind_param('si', $bigsellerSku, $itemId);
        $stmt->execute();
        $id = (int) $this->con->insert_id;
        $stmt->close();

        return $id;
    }

    protected function insertLzdProduct(string $sellerSku, float $weight, float $length, float $width, float $height, string $lzdSku = ''): int
    {
        if ($lzdSku === '') {
            $lzdSku = self::FIXTURE_PREFIX . 'LZD-' . $sellerSku;
        }
        $name = 'test product ' . $sellerSku;
        $stmt = $this->con->prepare(
            'INSERT INTO lzd_products (lzd_sku, seller_sku, name, price, color, image1, image2, image3, image4, image5, weight, length, width, height, status) '
            . 'VALUES (?, ?, ?, 0, \'\', \'\', \'\', \'\', \'\', \'\', ?, ?, ?, ?, 1)'
        );
        $stmt->bind_param('sssdddd', $lzdSku, $sellerSku, $name, $weight, $length, $width, $height);
        $stmt->execute();
        $id = (int) $this->con->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Inserts a row directly into `orders` (bypassing RecordInserter). Returns orders.id.
     *
     * @param array<string, mixed> $overrides column => value
     */
    protected function insertOrder(array $overrides = []): int
    {
        $row = array_merge([
            'insertLogId' => 0,
            'orderNum' => self::FIXTURE_PREFIX . 'ORDER-' . uniqid(),
            'date' => '2024-01-15',
            'sku' => self::FIXTURE_PREFIX . 'SKU',
            'trackingNum' => '',
            'sellingPrice' => 10.00,
            'status' => 'COMPLETED',
            'shippingFee' => 0.00,
            'shippingFeeByCust' => 0.00,
            'shippingState' => '',
            'shippingWeight' => 0.00,
            'voucher' => 0.00,
            'platformCharges' => 'Lazada',
        ], $overrides);

        $columns = array_keys($row);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO orders (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $this->con->prepare($sql);
        $types = str_repeat('s', count($columns));
        $stmt->bind_param($types, ...array_values($row));
        $stmt->execute();
        $id = (int) $this->con->insert_id;
        $stmt->close();

        return $id;
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchAll(string $sql, string $types = '', ...$params): array
    {
        $stmt = $this->con->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}
