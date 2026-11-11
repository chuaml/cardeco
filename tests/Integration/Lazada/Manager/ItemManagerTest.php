<?php

declare(strict_types=1);

namespace test\Integration\Lazada\Manager;

use Lazada\Item;
use Lazada\Manager\ItemManager;
use mysqli_sql_exception;
use test\Support\DatabaseTestCase;

/**
 * Characterization of Lazada\Manager\ItemManager (src/Legacy/Lazada/Manager/ItemManager.php):
 * CRUD over lzd_products, fed by /LzdItemManager uploads and read by Orders\Lazada\AutoFilling.
 */
final class ItemManagerTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;
    /** @var ItemManager */
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->manager = new ItemManager($this->con);
    }

    private function lzdSku(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-LZD-' . $letter;
    }

    private function sellerSku(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    /**
     * Lazada\Item's 12-argument constructor - note the order is ... weight, WIDTH, LENGTH, height ... (Item.php:18-22).
     *
     * @param array<int, string>|null $images
     */
    private function item(?int $id, string $letter, string $name, float $price, ?array $images, float $weight, float $width, float $length, float $height, int $status = 1): Item
    {
        return new Item($id, $this->lzdSku($letter), $this->sellerSku($letter), $name, $price, 'Chrome', $images, $weight, $width, $length, $height, $status);
    }

    // ------------------------------------------------------------------ insert + select

    public function testInsert_ThenSelectBySellerSku_RoundTripsEveryColumn(): void
    {
        $this->manager->insert([$this->item(null, 'A', 'Product A', 99.9, ['a1.jpg', 'a2.jpg'], 2.5, 10.0, 125.0, 7.25)]);

        $rows = $this->manager->selectBySellerSku([$this->sellerSku('A')]);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertGreaterThan(0, $row['id']);
        unset($row['id']);
        $this->assertSame(
            [
                'lzd_sku' => $this->lzdSku('A'),
                'seller_sku' => $this->sellerSku('A'),
                'name' => 'Product A',
                'price' => '99.90',
                'color' => 'Chrome',
                'image1' => 'a1.jpg',
                'image2' => 'a2.jpg',
                'image3' => '',
                'image4' => '',
                'image5' => '',
                'weight' => '2.50',
                'length' => '125.00',
                'width' => '10.00',
                'height' => '7.25',
                'status' => 1,
            ],
            $row,
            'decimals come back as strings; missing images are stored as empty strings'
        );
    }

    public function testInsert_NullImages_StoresEmptyStringsForAllFiveSlots(): void
    {
        $this->manager->insert([$this->item(null, 'A', 'Product A', 1.0, null, 1.0, 1.0, 1.0, 1.0)]);

        $row = $this->manager->selectBySellerSku([$this->sellerSku('A')])[0];
        $this->assertSame(['', '', '', '', ''], [$row['image1'], $row['image2'], $row['image3'], $row['image4'], $row['image5']]);
    }

    public function testInsert_SeveralItems_InsertsAllOfThemInOneStatement(): void
    {
        $this->manager->insert([
            $this->item(null, 'A', 'Product A', 1.0, [], 1.0, 1.0, 1.0, 1.0),
            $this->item(null, 'B', 'Product B', 2.0, [], 1.0, 1.0, 1.0, 1.0, 0),
        ]);

        $rows = $this->manager->selectBySellerSku([$this->sellerSku('A'), $this->sellerSku('B')]);
        $statusByName = array_column($rows, 'status', 'name');
        ksort($statusByName);
        $this->assertSame(['Product A' => 1, 'Product B' => 0], $statusByName);
    }

    public function testInsert_DuplicateLzdSku_ThrowsBecauseLzdSkuIsUnique(): void
    {
        $this->manager->insert([$this->item(null, 'A', 'Product A', 1.0, [], 1.0, 1.0, 1.0, 1.0)]);

        $this->expectException(mysqli_sql_exception::class);
        $this->expectExceptionCode(1062);

        $this->manager->insert([$this->item(null, 'A', 'Product A again', 1.0, [], 1.0, 1.0, 1.0, 1.0)]);
    }

    public function testSelectBySellerSku_EmptyList_ReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->manager->selectBySellerSku([]));
    }

    public function testSelectBySellerSku_UnknownSku_ReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->manager->selectBySellerSku([$this->sellerSku('NOPE')]));
    }

    public function testSelectBySellerSku_TwoProductsSharingASellerSku_ReturnsBoth(): void
    {
        // seller_sku is NOT unique (only lzd_sku is): variants of one listing share it
        $this->insertLzdProduct($this->sellerSku('A'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('A1'));
        $this->insertLzdProduct($this->sellerSku('A'), 2.0, 1.0, 1.0, 1.0, $this->lzdSku('A2'));

        $rows = $this->manager->selectBySellerSku([$this->sellerSku('A')]);

        $this->assertCount(2, $rows);
        $lzdSkus = array_column($rows, 'lzd_sku');
        sort($lzdSkus);
        $this->assertSame([$this->lzdSku('A1'), $this->lzdSku('A2')], $lzdSkus);
    }

    public function testSelectById_ReturnsSingleElementListOrEmptyList(): void
    {
        $id = $this->insertLzdProduct($this->sellerSku('A'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('A'));

        $rows = $this->manager->selectById($id);

        $this->assertCount(1, $rows);
        $this->assertSame($id, $rows[0]['id']);
        $this->assertSame($this->lzdSku('A'), $rows[0]['lzd_sku']);
        $this->assertSame([], $this->manager->selectById(2147483647));
    }

    public function testSelectAll_ColumnAndLimit_ReturnsOnlyThatColumn(): void
    {
        $this->insertLzdProduct($this->sellerSku('A'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('A'));

        $rows = $this->manager->selectAll('lzd_sku', 0, 1);

        $this->assertCount(1, $rows);
        $this->assertSame(['lzd_sku'], array_keys($rows[0]), '$column is interpolated verbatim into the SELECT list');
    }

    // ------------------------------------------------------------------ updates

    public function testUpdateById_RewritesEveryColumnOfTheRow(): void
    {
        $id = $this->insertLzdProduct($this->sellerSku('A'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('A'));
        $untouched = $this->insertLzdProduct($this->sellerSku('B'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('B'));

        $this->manager->updateById([$this->item($id, 'A2', 'Renamed A', 12.5, ['n1.jpg'], 3.0, 20.0, 30.0, 40.0, 0)]);

        $row = $this->manager->selectById($id)[0];
        $this->assertSame($this->lzdSku('A2'), $row['lzd_sku'], 'updateById may change the lzd_sku');
        $this->assertSame($this->sellerSku('A2'), $row['seller_sku']);
        $this->assertSame('Renamed A', $row['name']);
        $this->assertSame('12.50', $row['price']);
        $this->assertSame(['n1.jpg', '', '', '', ''], [$row['image1'], $row['image2'], $row['image3'], $row['image4'], $row['image5']]);
        $this->assertSame(['3.00', '30.00', '20.00', '40.00'], [$row['weight'], $row['length'], $row['width'], $row['height']]);
        $this->assertSame(0, $row['status']);
        $this->assertSame('test product ' . $this->sellerSku('B'), $this->manager->selectById($untouched)[0]['name'], 'other rows untouched');
    }

    public function testUpdateByLzdSku_MatchesOnLzdSkuAndKeepsIt(): void
    {
        $id = $this->insertLzdProduct($this->sellerSku('A'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('A'));

        $item = new Item(null, $this->lzdSku('A'), $this->sellerSku('A-NEW'), 'Renamed by lzd sku', 5.0, 'Black', ['x.jpg'], 1.5, 2.0, 3.0, 4.0, 1);
        $this->manager->updateByLzdSku([$item]);

        $row = $this->manager->selectById($id)[0];
        $this->assertSame($this->lzdSku('A'), $row['lzd_sku']);
        $this->assertSame($this->sellerSku('A-NEW'), $row['seller_sku']);
        $this->assertSame('Renamed by lzd sku', $row['name']);
        $this->assertSame('Black', $row['color']);
        $this->assertSame('5.00', $row['price']);
        $this->assertSame(['1.50', '3.00', '2.00', '4.00'], [$row['weight'], $row['length'], $row['width'], $row['height']]);
    }

    public function testUpdateByLzdSku_UnknownLzdSku_ChangesNothingAndDoesNotThrow(): void
    {
        $id = $this->insertLzdProduct($this->sellerSku('A'), 1.0, 1.0, 1.0, 1.0, $this->lzdSku('A'));

        $this->manager->updateByLzdSku([$this->item(null, 'NOPE', 'ghost', 1.0, [], 1.0, 1.0, 1.0, 1.0)]);

        $this->assertSame('test product ' . $this->sellerSku('A'), $this->manager->selectById($id)[0]['name']);
    }
}
