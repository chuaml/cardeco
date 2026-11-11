<?php

declare(strict_types=1);

namespace test\Unit;

use PHPUnit\Framework\TestCase;
use Shopee_Product_Info;

/**
 * Characterization of Shopee_Product_Info (src/Legacy/Shopee_Product_Info.php): parser for the legacy
 * Shopee "Product Info" order cell, e.g.
 *   "[1] Product Name:X; Variation Name:Y; Price:RM 12.90; Quantity:2; Parent SKU Reference No.:P; SKU Reference No.:S;"
 * Segments are split on ';', key/value on the first ':'; keys are matched with strpos() against the
 * six detail names and only EMPTY details are ever filled (first occurrence wins).
 *
 * Its two callers (Stock_Items_Checker / Stock_Items_Conclusion) always call resetAll() before setCell_Value().
 */
final class ShopeeProductInfoTest extends TestCase
{
    const CELL = '[1] Product Name:Test Seat Cover; Variation Name:Black; Price: RM 12.90; Quantity: 2; '
        . 'Parent SKU Reference No.: ZZTEST-001; SKU Reference No.: ZZTEST-001-BLK;';

    public function testConstructor_DefaultDetails_PriceIsFloatZeroAndQuantityIntZero(): void
    {
        // the constructor already runs gatherDetails() (:24), which casts the two numeric details
        $this->assertSame([
            'product name' => '',
            'variation name' => '',
            'price' => 0.0,
            'quantity' => 0,
            'parent sku reference no.' => '',
            'sku reference no.' => '',
        ], (new Shopee_Product_Info())->getDetails());
    }

    public function testResetAll_RestoresEmptyStringsForEveryDetail(): void
    {
        $info = new Shopee_Product_Info();
        $info->resetAll();
        $info->setCell_Value(self::CELL);

        $info->resetAll();

        $this->assertSame([
            'product name' => '',
            'variation name' => '',
            'price' => '',
            'quantity' => '',
            'parent sku reference no.' => '',
            'sku reference no.' => '',
        ], $info->getDetails());
    }

    public function testSetCellValue_AfterReset_ExtractsNameVariationPriceQuantityAndParentSku(): void
    {
        $details = self::parse(self::CELL);

        $this->assertSame('Test Seat Cover', $details['product name']);
        $this->assertSame('Black', $details['variation name']);
        $this->assertSame(12.9, $details['price']);
        $this->assertSame(2, $details['quantity']);
        $this->assertSame('ZZTEST-001', $details['parent sku reference no.']);
    }

    public function testSetCellValue_ChildSku_documentsCurrentBehaviour_IsShadowedByParentSku(): void
    {
        // :73 strpos('parent sku reference no.', 'sku reference no.') matches, so the parent segment fills BOTH
        // details and the later 'SKU Reference No.' segment is ignored (first occurrence wins, :69-70)
        $details = self::parse(self::CELL);

        $this->assertSame('ZZTEST-001', $details['sku reference no.']);
    }

    /**
     * @group known-bug
     */
    public function testSetCellValue_ChildSku_IsExtractedFromItsOwnSegment(): void
    {
        // Shopee_Product_Info.php:73 - key matching must be exact, otherwise the variation SKU is never seen
        $details = self::parse(self::CELL);

        $this->assertSame('ZZTEST-001-BLK', $details['sku reference no.']);
    }

    public function testSetCellValue_ChildSkuSegmentBeforeParentSegment_BothExtracted(): void
    {
        $details = self::parse('[1] Product Name:A; Variation Name:B; Price:RM 5; Quantity:3; '
            . 'SKU Reference No.: ZZTEST-003-BLK; Parent SKU Reference No.: ZZTEST-003;');

        $this->assertSame('ZZTEST-003-BLK', $details['sku reference no.']);
        $this->assertSame('ZZTEST-003', $details['parent sku reference no.']);
    }

    public function testSetCellValue_EmptyParentSku_ChildSkuIsExtracted(): void
    {
        $details = self::parse('[1] Product Name:A; Variation Name:; Price:RM 5.00; Quantity:1; '
            . 'Parent SKU Reference No.:; SKU Reference No.: ZZTEST-002;');

        $this->assertSame('', $details['parent sku reference no.']);
        $this->assertSame('ZZTEST-002', $details['sku reference no.']);
        $this->assertSame('', $details['variation name']);
    }

    /**
     * @dataProvider priceProvider
     */
    public function testSetCellValue_PriceLettersAreStrippedBeforeCast(string $rawPrice, float $expected): void
    {
        $details = self::parse('[1] Product Name:A; Variation Name:B; Price:' . $rawPrice . '; Quantity:1; '
            . 'Parent SKU Reference No.:; SKU Reference No.:ZZTEST-001;');

        $this->assertSame($expected, $details['price']);
    }

    public function priceProvider(): array
    {
        return [
            'RM with space' => ['RM 12.90', 12.9],
            'RM without space' => ['RM12.90', 12.9],
            'bare number' => ['7', 7.0],
            'thousands separator breaks the cast (documents current behaviour)' => ['RM1,299.00', 1.0],
            'empty' => ['', 0.0],
        ];
    }

    /**
     * @dataProvider quantityProvider
     */
    public function testSetCellValue_QuantityIsCastToInt(string $rawQuantity, int $expected): void
    {
        $details = self::parse('[1] Product Name:A; Variation Name:B; Price:RM 1; Quantity:' . $rawQuantity . '; '
            . 'Parent SKU Reference No.:; SKU Reference No.:ZZTEST-001;');

        $this->assertSame($expected, $details['quantity']);
    }

    public function quantityProvider(): array
    {
        return [
            'plain' => ['2', 2],
            'padded' => ['  10 ', 10],
            'empty' => ['', 0],
            'non numeric' => ['two', 0],
        ];
    }

    public function testSetCellValue_ValueContainingColon_IsKeptWhole(): void
    {
        // only the first ':' separates key and value (:62-66)
        $details = self::parse('[1] Product Name:Phone Holder 2:1 ratio; Variation Name:B; Price:RM 1; Quantity:1; '
            . 'Parent SKU Reference No.:; SKU Reference No.:ZZTEST-001;');

        $this->assertSame('Phone Holder 2:1 ratio', $details['product name']);
    }

    public function testSetCellValue_KeysAreMatchedCaseInsensitively(): void
    {
        $details = self::parse('[1] PRODUCT NAME:Upper; VARIATION NAME:Case; PRICE:RM 1; QUANTITY:4; '
            . 'PARENT SKU REFERENCE NO.:; SKU REFERENCE NO.:ZZTEST-001;');

        $this->assertSame('Upper', $details['product name']);
        $this->assertSame('Case', $details['variation name']);
        $this->assertSame(4, $details['quantity']);
        $this->assertSame('ZZTEST-001', $details['sku reference no.']);
    }

    public function testSetCellValue_RepeatedKey_FirstOccurrenceWins(): void
    {
        $details = self::parse('[1] Product Name:First; Variation Name:B; Price:RM 1; Quantity:1; '
            . 'Parent SKU Reference No.:; SKU Reference No.:ZZTEST-001; [2] Product Name:Second; Quantity:9;');

        $this->assertSame('First', $details['product name']);
        $this->assertSame(1, $details['quantity']);
    }

    public function testSetCellValue_UnknownKeysAreIgnored(): void
    {
        $details = self::parse('Weight:1kg; Colour:Red; [1] Product Name:A; Quantity:1; SKU Reference No.:ZZTEST-001;');

        $this->assertSame('A', $details['product name']);
        $this->assertSame('ZZTEST-001', $details['sku reference no.']);
        $this->assertSame('', $details['variation name']);
    }

    public function testSetCellValue_EmptyCell_LeavesDefaultsWithNumericCasts(): void
    {
        $details = self::parse('   ');

        $this->assertSame([
            'product name' => '',
            'variation name' => '',
            'price' => 0.0,
            'quantity' => 0,
            'parent sku reference no.' => '',
            'sku reference no.' => '',
        ], $details);
    }

    public function testSetCellValue_WithoutResetAll_documentsCurrentBehaviour_PriceAndQuantityStayZero(): void
    {
        // Shopee_Product_Info.php:24 casts price/quantity to 0.0/0 in the constructor; :69 then treats "0"
        // as "already filled", so a fresh instance can never extract them until resetAll() is called
        $info = new Shopee_Product_Info();

        $info->setCell_Value(self::CELL);

        $details = $info->getDetails();
        $this->assertSame('Test Seat Cover', $details['product name']);
        $this->assertSame(0.0, $details['price']);
        $this->assertSame(0, $details['quantity']);
    }

    /**
     * @group known-bug
     */
    public function testSetCellValue_FreshInstance_ExtractsPriceAndQuantity(): void
    {
        // Shopee_Product_Info.php:24 - the constructor should not pre-cast the numeric details
        $info = new Shopee_Product_Info();

        $info->setCell_Value(self::CELL);

        $details = $info->getDetails();
        $this->assertSame(12.9, $details['price']);
        $this->assertSame(2, $details['quantity']);
    }

    public function testSetCellValue_SecondCallWithoutReset_KeepsFirstValues(): void
    {
        $info = new Shopee_Product_Info();
        $info->resetAll();
        $info->setCell_Value(self::CELL);

        $info->setCell_Value('[1] Product Name:Other; Variation Name:Red; Price:RM 99; Quantity:9; '
            . 'Parent SKU Reference No.:ZZTEST-999; SKU Reference No.:ZZTEST-999-RED;');

        $details = $info->getDetails();
        $this->assertSame('Test Seat Cover', $details['product name']);
        $this->assertSame('Black', $details['variation name']);
        $this->assertSame('ZZTEST-001', $details['parent sku reference no.']);
    }

    private static function parse(string $cell): array
    {
        $info = new Shopee_Product_Info();
        $info->resetAll();
        $info->setCell_Value($cell);

        return $info->getDetails();
    }
}
