<?php

declare(strict_types=1);

namespace test\Unit;

use Orders_Result_Formatter;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of Orders_Result_Formatter (src/Legacy/Orders_Result_Formatter.php): turns the
 * Stock_Items_Checker output sections into <table> strings. Column headers come from the keys of the
 * FIRST row; cell values are concatenated verbatim (the caller escapes them).
 */
final class OrdersResultFormatterTest extends TestCase
{
    const OUT_OF_STOCK = ['Item Code' => 'ZZTEST-004', 'Description' => 'Test Wax', 'Quantity' => '1', 'Stock' => '0'];
    const IN_STOCK = ['Item Code' => 'ZZTEST-001', 'Description' => 'Test Seat Cover', 'Quantity' => '3', 'Stock' => '5'];
    const NOT_FOUND = ['Item Code' => 'N/A', 'Description' => 'ZZTEST-404', 'Quantity' => '1', 'Stock' => 'N/A'];

    public function testGetOutofStock_RendersHeadersFromKeysAndOneRowPerEntry(): void
    {
        $formatter = new Orders_Result_Formatter(['outofstock' => [self::OUT_OF_STOCK, self::IN_STOCK], 'itemList' => [], 'notfound' => []]);

        $this->assertSame(
            '<table><thead><tr><th>Item Code</th><th>Description</th><th>Quantity</th><th>Stock</th></tr><tbody>'
            . '<tr><td>ZZTEST-004</td><td>Test Wax</td><td>1</td><td>0</td></tr>'
            . '<tr><td>ZZTEST-001</td><td>Test Seat Cover</td><td>3</td><td>5</td></tr>'
            . '</tbody></table>',
            $formatter->getOutofStock()
        );
    }

    public function testGetItemList_UsesTheItemListSection(): void
    {
        $formatter = new Orders_Result_Formatter(['outofstock' => [self::OUT_OF_STOCK], 'itemList' => [self::IN_STOCK], 'notfound' => [self::NOT_FOUND]]);

        $html = $formatter->getItemList();

        $this->assertStringContainsString('<td>ZZTEST-001</td>', $html);
        $this->assertStringNotContainsString('ZZTEST-004', $html);
        $this->assertStringNotContainsString('ZZTEST-404', $html);
    }

    public function testGetNotFound_UsesTheNotFoundSection(): void
    {
        $formatter = new Orders_Result_Formatter(['outofstock' => [self::OUT_OF_STOCK], 'itemList' => [self::IN_STOCK], 'notfound' => [self::NOT_FOUND]]);

        $this->assertSame(
            '<table><thead><tr><th>Item Code</th><th>Description</th><th>Quantity</th><th>Stock</th></tr><tbody>'
            . '<tr><td>N/A</td><td>ZZTEST-404</td><td>1</td><td>N/A</td></tr></tbody></table>',
            $formatter->getNotFound()
        );
    }

    public function testCellsAndHeadersAreNotEscaped_documentsCurrentBehaviour(): void
    {
        // Orders_Result_Formatter.php:30,43 - raw concatenation; Stock_Items_Checker::getOutput() escapes beforehand
        $formatter = new Orders_Result_Formatter(['outofstock' => [['<b>' => 'a < b & "c"']], 'itemList' => [], 'notfound' => []]);

        $this->assertSame(
            '<table><thead><tr><th><b></th></tr><tbody><tr><td>a < b & "c"</td></tr></tbody></table>',
            $formatter->getOutofStock()
        );
    }

    public function testEmptySection_documentsCurrentBehaviour_RaisesUndefinedOffset(): void
    {
        // Orders_Result_Formatter.php:38 reads [0] of the section to build the header row
        $formatter = new Orders_Result_Formatter(['outofstock' => [], 'itemList' => [], 'notfound' => []]);

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/Undefined (offset|array key)/');
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $formatter->getOutofStock();
        } finally {
            restore_error_handler();
        }
    }

    public function testGetConclusion_RendersAnyListOfAssociativeRows(): void
    {
        $formatter = new Orders_Result_Formatter([]);

        $this->assertSame(
            '<table><thead><tr><th>Order ID</th><th>has Stock?</th></tr><tbody>'
            . '<tr><td>O-1</td><td>Yes</td></tr><tr><td>O-2</td><td>Maybe</td></tr></tbody></table>',
            $formatter->getConclusion([
                ['Order ID' => 'O-1', 'has Stock?' => 'Yes'],
                ['Order ID' => 'O-2', 'has Stock?' => 'Maybe'],
            ])
        );
    }

    public function testGetConclusion_HeaderComesFromFirstRowOnly(): void
    {
        $html = (new Orders_Result_Formatter([]))->getConclusion([['A' => '1'], ['A' => '2', 'B' => '3']]);

        $this->assertSame(1, substr_count($html, '<th>'));
        $this->assertStringContainsString('<tr><td>2</td><td>3</td></tr>', $html);
    }
}
