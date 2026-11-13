<?php

declare(strict_types=1);

namespace test\Unit\Orders;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Orders\MonthlyRecord;
use Orders\PaymentCharges\BankIn;
use Orders\PaymentCharges\Lazada;
use Orders\RecordEditor;
use PHPUnit\Framework\TestCase;
use Product\Item;
use test\Support\HtmlTableReader;

/**
 * Characterization of the rendering side of Orders\RecordEditor (src/Legacy/Orders/RecordEditor.php): monthly
 * records -> the editable `<table id="RecordEditor" cd-editable-sheet>` of /RecordEditor whose <input> names
 * (`r[<recordId>][<field>]`) are what process/RecordEditor/submit.php reads back.
 *
 * getFloorPage() reads $_GET through `??`; under the CLI $_GET is empty, nothing here sets it.
 */
final class RecordEditorRenderTest extends TestCase
{
    const MONEY_INPUT = '<input inputmode="numeric" pattern="[0-9,]+(?:\.[0-9]{1,2})?" name="r[%d][%s]" value="%s" maxlength="16" class="money" readonly/>';

    const HEADERS = [
        'billno' => 'Bill No.',
        'date' => 'Date',
        'dateStockOut' => 'Date Stock Out',
        'orderNum' => 'Order Number',
        'itemName' => 'Description',
        'sku' => 'Seller SKU',
        'trackingNum' => 'Tracking Number',
        'sellingPrice' => 'Selling Price',
        'grandTotal' => 'Grand Total',
        'shippingFee' => 'Courier',
        'shippingFeeByWeight' => 'Courier 2',
        'shippingFeeByCust' => 'Courier By Customer',
        'shippingState' => 'State',
        'shippingWeight' => 'Weight',
        'voucher' => 'Voucher',
        'transferCharges' => 'Transfer Charges',
        'platformCharges' => 'Platform Charges',
        'platformChargesAmount' => 'Platform Charges Amount',
        'bankIn' => 'Bank In',
        'cash' => 'Cash',
    ];

    // ---------------------------------------------------------------- header

    public function testGetTable_header_isTheTwentyRenamedColumnsWithFieldNameIds(): void
    {
        $html = $this->render([$this->record(101)]);

        $this->assertSame(array_values(self::HEADERS), (new HtmlTableReader($html))->headers());
        $ids = [];
        foreach ($this->xpath($html)->query('//thead//th') as $th) {
            $ids[] = $th->getAttribute('id');
        }
        $this->assertSame(array_keys(self::HEADERS), $ids);
    }

    public function testGetTable_recordIdAndStatus_areNotDisplayed(): void
    {
        $headers = (new HtmlTableReader($this->render([$this->record(101)])))->headers();

        $this->assertNotContains('recordId', $headers);
        $this->assertNotContains('Record ID', $headers);
        $this->assertNotContains('status', $headers);
    }

    public function testGetTable_tableAttributes_areIdRecordEditorAndEditableSheetFlag(): void
    {
        $html = $this->render([$this->record(101)]);

        $this->assertStringStartsWith('<table id="RecordEditor" cd-editable-sheet>', $html);
        $this->assertStringEndsWith('</tbody></table>', $html);
        $this->assertStringNotContainsString('<tfoot>', $html);
    }

    public function testGetTable_noRecords_rendersHeaderAndEmptyBody(): void
    {
        $html = $this->render([]);

        $this->assertSame(array_values(self::HEADERS), (new HtmlTableReader($html))->headers());
        $this->assertStringContainsString('</thead><tbody></tbody></table>', $html);
    }

    // ---------------------------------------------------------------- rows

    public function testGetTable_twoRecords_rendersOneRowPerRecordWithFormattedCells(): void
    {
        $html = $this->render([
            $this->record(101),
            $this->record(102, ['orderNum' => 'ZZ240101DEF', 'sku' => 'ZZTEST-002', 'itemName' => 'Zz Cover', 'sellingPrice' => 10.0, 'dateStockOut' => null]),
        ]);

        $rows = (new HtmlTableReader($html))->assocRows();

        $this->assertCount(2, $rows);
        $this->assertSame('15-Jan-2024', $rows[0]['Date']);
        $this->assertSame('16-Jan-2024', $rows[0]['Date Stock Out']);
        $this->assertSame('ZZ240101ABC', $rows[0]['Order Number']);
        $this->assertSame('Zz Boot Tray', $rows[0]['Description']);
        $this->assertSame('ZZTEST-001', $rows[0]['Seller SKU']);
        $this->assertSame('41.50', $rows[0]['Selling Price']);
        $this->assertSame('40.25', $rows[0]['Grand Total'], 'sellingPrice + shippingFeeByCust - voucher');
        $this->assertSame('0.00', $rows[0]['Courier 2']);
        $this->assertSame('Johor', $rows[0]['State']);
        $this->assertSame('3.00', $rows[0]['Weight']);
        $this->assertSame('0.00', $rows[0]['Transfer Charges']);
        $this->assertSame('Lazada', $rows[0]['Platform Charges']);

        $this->assertSame('ZZ240101DEF', $rows[1]['Order Number']);
        $this->assertSame('', $rows[1]['Date Stock Out'], 'null dateStockOut -> empty cell');
        $this->assertSame('10.00', $rows[1]['Selling Price']);
        $this->assertSame('8.75', $rows[1]['Grand Total']);
    }

    public function testGetTable_editableCells_renderInputsNamedByRecordIdAndField(): void
    {
        $html = $this->render([$this->record(101)]);

        $this->assertSame([
            'r[101][billno]' => 'CS-0000101',
            'r[101][trackingNum]' => 'ZZTRK00101',
            'r[101][shippingFee]' => '4.90',
            'r[101][shippingFeeByCust]' => '0.00',
            'r[101][voucher]' => '1.25',
            'r[101][platformChargesAmount]' => '3.20',
            'r[101][bankIn]' => '10.00',
            'r[101][cash]' => '0.00',
        ], $this->inputValuesByName($html));
    }

    public function testGetTable_everyInputIsReadonly(): void
    {
        $html = $this->render([$this->record(101), $this->record(102)]);

        $inputs = $this->xpath($html)->query('//tbody//input');

        $this->assertSame(16, $inputs->length);
        foreach ($inputs as $input) {
            /** @var DOMElement $input */
            $this->assertTrue($input->hasAttribute('readonly'), $input->getAttribute('name'));
        }
    }

    public function testGetTable_moneyInputs_haveNumericPatternAndMoneyClass(): void
    {
        $html = $this->render([$this->record(101)]);

        $input = $this->xpath($html)->query('//input[@name="r[101][shippingFee]"]')->item(0);

        /** @var DOMElement $input */
        $this->assertSame('numeric', $input->getAttribute('inputmode'));
        $this->assertSame('[0-9,]+(?:\.[0-9]{1,2})?', $input->getAttribute('pattern'));
        $this->assertSame('16', $input->getAttribute('maxlength'));
        $this->assertSame('money', $input->getAttribute('class'));
    }

    public function testGetTable_textInputs_haveMaxLength20(): void
    {
        $xpath = $this->xpath($this->render([$this->record(101)]));

        /** @var DOMElement $billno */
        $billno = $xpath->query('//input[@name="r[101][billno]"]')->item(0);
        /** @var DOMElement $tracking */
        $tracking = $xpath->query('//input[@name="r[101][trackingNum]"]')->item(0);

        $this->assertSame(['text', '20', '5'], [$billno->getAttribute('type'), $billno->getAttribute('maxlength'), $billno->getAttribute('size')]);
        $this->assertSame(['text', '20'], [$tracking->getAttribute('type'), $tracking->getAttribute('maxlength')]);
    }

    public function testGetTable_amountsOverAThousand_useThousandsSeparator(): void
    {
        $html = $this->render([$this->record(101, ['sellingPrice' => 1234.5, 'shippingFee' => 1000.0])]);

        $this->assertSame('1,234.50', (new HtmlTableReader($html))->assocRows()[0]['Selling Price']);
        $this->assertSame('1,000.00', $this->inputValuesByName($html)['r[101][shippingFee]']);
    }

    public function testGetTable_dateCells_carryAnIsoDataValueForSorting(): void
    {
        $xpath = $this->xpath($this->render([$this->record(101)]));

        /** @var DOMElement $date */
        $date = $xpath->query('//tbody/tr[1]/td[2]')->item(0);
        /** @var DOMElement $dateStockOut */
        $dateStockOut = $xpath->query('//tbody/tr[1]/td[3]')->item(0);

        $this->assertSame('2024-01-15', $date->getAttribute('data-value'));
        $this->assertSame('15-Jan-2024', $date->textContent);
        $this->assertSame('2024-01-16', $dateStockOut->getAttribute('data-value'));
    }

    public function testGetTable_singleRecord_fullMarkupSample(): void
    {
        $html = $this->render([$this->record(101)]);

        $head = '<thead><tr>';
        foreach (self::HEADERS as $field => $label) {
            $head .= '<th id="' . $field . '">' . $label . '</th>';
        }
        $head .= '</tr></thead>';

        $expected = '<table id="RecordEditor" cd-editable-sheet>' . $head . '<tbody><tr>'
            . '<td><input type="text" name="r[101][billno]" value="CS-0000101" maxlength="20" size="5" readonly/></td>'
            . '<td data-value="2024-01-15">15-Jan-2024</td>'
            . '<td data-value="2024-01-16">16-Jan-2024</td>'
            . '<td>ZZ240101ABC</td>'
            . '<td>Zz Boot Tray</td>'
            . '<td>ZZTEST-001</td>'
            . '<td><input type="text" name="r[101][trackingNum]" value="ZZTRK00101" maxlength="20" readonly/></td>'
            . '<td>41.50</td>'
            . '<td>40.25</td>'
            . '<td>' . sprintf(self::MONEY_INPUT, 101, 'shippingFee', '4.90') . '</td>'
            . '<td>0.00</td>'
            . '<td>' . sprintf(self::MONEY_INPUT, 101, 'shippingFeeByCust', '0.00') . '</td>'
            . '<td>Johor</td>'
            . '<td>3.00</td>'
            . '<td>' . sprintf(self::MONEY_INPUT, 101, 'voucher', '1.25') . '</td>'
            . '<td>0.00</td>'
            . '<td>Lazada</td>'
            . '<td>' . sprintf(self::MONEY_INPUT, 101, 'platformChargesAmount', '3.20') . '</td>'
            . '<td>' . sprintf(self::MONEY_INPUT, 101, 'bankIn', '10.00') . '</td>'
            . '<td>' . sprintf(self::MONEY_INPUT, 101, 'cash', '0.00') . '</td>'
            . '</tr></tbody></table>';

        $this->assertSame($expected, $html);
    }

    public function testSetMonthlyRecords_calledTwice_replacesThePreviousRows(): void
    {
        $editor = new RecordEditor();
        $editor->setMonthlyRecords([$this->record(101), $this->record(102)], 1);
        $editor->setMonthlyRecords([$this->record(103, ['orderNum' => 'ZZ240101XYZ'])], 1);

        $rows = (new HtmlTableReader($editor->getTable()))->assocRows();

        $this->assertCount(1, $rows);
        $this->assertSame('ZZ240101XYZ', $rows[0]['Order Number']);
    }

    // ---------------------------------------------------------------- escaping / date heuristic

    public function testGetTable_description_documentsCurrentBehaviour_isEscapedTwice(): void
    {
        // setMonthlyRecords() escapes every non-float value and formatCell()'s default branch escapes again
        $html = $this->render([$this->record(101, ['itemName' => 'Zz <Tray> & Co'])]);

        $this->assertStringContainsString('<td>Zz &amp;lt;Tray&amp;gt; &amp;amp; Co</td>', $html);
        $this->assertSame('Zz &lt;Tray&gt; &amp; Co', (new HtmlTableReader($html))->assocRows()[0]['Description']);
    }

    /**
     * @group known-bug
     */
    public function testGetTable_description_isEscapedExactlyOnce(): void
    {
        // RecordEditor.php setMonthlyRecords() + formatCell(): '<' and '&' in an item description must be
        // displayed as characters, i.e. appear escaped once in the markup
        $html = $this->render([$this->record(101, ['itemName' => 'Zz <Tray> & Co'])]);

        $this->assertStringContainsString('<td>Zz &lt;Tray&gt; &amp; Co</td>', $html);
        $this->assertSame('Zz <Tray> & Co', (new HtmlTableReader($html))->assocRows()[0]['Description']);
    }

    public function testGetTable_inputValues_areHtmlEscapedOnce(): void
    {
        $html = $this->render([$this->record(101, ['billno' => 'CS"1<2>'])]);

        $this->assertStringContainsString('value="CS&quot;1&lt;2&gt;"', $html);
        $this->assertSame('CS"1<2>', $this->inputValuesByName($html)['r[101][billno]']);
    }

    public function testGetTable_numericLookingSku_documentsCurrentBehaviour_isRenderedAsADate(): void
    {
        // RecordEditor.php formatCell(): every non-float string that date_create() accepts is treated as a date;
        // '1001' parses as the time 10:01 of TODAY, so the cell gets a (time-dependent) data-value attribute
        $xpath = $this->xpath($this->render([$this->record(101, ['sku' => '1001'])]));

        /** @var DOMElement $sku */
        $sku = $xpath->query('//tbody/tr[1]/td[6]')->item(0);

        $this->assertSame('1001', $sku->textContent);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $sku->getAttribute('data-value'));
    }

    /**
     * @group known-bug
     */
    public function testGetTable_numericLookingSku_isNotRenderedAsADate(): void
    {
        // RecordEditor.php formatCell() date heuristic: only the date columns (date, dateStockOut) should get a
        // data-value; a SKU / order number / description that happens to parse must stay a plain cell
        $xpath = $this->xpath($this->render([$this->record(101, ['sku' => '1001'])]));

        /** @var DOMElement $sku */
        $sku = $xpath->query('//tbody/tr[1]/td[6]')->item(0);

        $this->assertFalse($sku->hasAttribute('data-value'));
    }

    public function testGetTable_recordWithoutPlatformCharges_rendersAnEmptyPlatformChargesAmountInput(): void
    {
        // platformChargesAmount is null -> '' after escaping. number_format('') used to be a warning on PHP 7 (which
        // then rendered value="") but a TypeError on PHP 8, so /RecordEditor crashed on 8.x only.
        // RecordEditor::moneyValue() renders the PHP 7 cell on every version, without the warning.
        // Reachable for orders whose platformCharges column is NULL or '' (Orders\Factory\MonthlyRecord sets none).
        $html = $this->render([$this->record(101, ['withPlatformCharges' => false])]);

        $values = $this->inputValuesByName($html);
        $this->assertSame('', $values['r[101][platformChargesAmount]']);
        $this->assertSame('4.90', $values['r[101][shippingFee]']);
        $this->assertSame('0.00', $values['r[101][shippingFeeByCust]']);
        $this->assertSame('1.25', $values['r[101][voucher]']);
        $this->assertSame('10.00', $values['r[101][bankIn]']);
        $this->assertSame('', (new HtmlTableReader($html))->assocRows()[0]['Platform Charges']);
    }

    // ---------------------------------------------------------------- getFloorPage ($_GET is empty under the CLI)

    public function testGetFloorPage_zeroPages_rendersASingleActivePageZeroButton(): void
    {
        $editor = new RecordEditor();
        $editor->setMonthlyRecords([$this->record(101)], 0);

        $this->assertSame(
            '<form method="GET" id="floorPage">'
            . '<input type="hidden" name="searchField" value="">'
            . '<input type="hidden" name="searchValue" value="">'
            . '<input class=\'active\' type="submit" name="floorPage" value="0"> '
            . '</form>',
            $editor->getFloorPage(0)
        );
    }

    public function testGetFloorPage_threePages_marksOnlyTheCurrentPageActive(): void
    {
        $editor = new RecordEditor();
        $editor->setMonthlyRecords([$this->record(101)], 3);

        $this->assertStringContainsString(
            '<input type="submit" name="floorPage" value="0"> '
            . '<input class=\'active\' type="submit" name="floorPage" value="1"> '
            . '<input type="submit" name="floorPage" value="2"> ',
            $editor->getFloorPage(1)
        );
    }

    public function testGetFloorPage_noRecords_documentsCurrentBehaviour_rendersNoPageButtons(): void
    {
        // setMonthlyRecords() returns before storing $numPage when the list is empty
        $editor = new RecordEditor();
        $editor->setMonthlyRecords([], 3);

        $this->assertStringNotContainsString('name="floorPage"', $editor->getFloorPage(0));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param array<string, mixed> $overrides itemName, sku, orderNum, sellingPrice, shippingFee, dateStockOut, billno,
     *                                        withPlatformCharges
     */
    private function record(int $recordId, array $overrides = []): MonthlyRecord
    {
        $v = $overrides + [
            'orderNum' => 'ZZ240101ABC',
            'sku' => 'ZZTEST-001',
            'itemName' => 'Zz Boot Tray',
            'sellingPrice' => 41.5,
            'shippingFee' => 4.9,
            'dateStockOut' => '2024-01-16',
            'billno' => 'CS-0000' . $recordId,
            'withPlatformCharges' => true,
        ];

        $m = new MonthlyRecord($recordId, $v['orderNum'], new Item(null, $v['sku'], $v['itemName']));
        $m->setDate('2024-01-15');
        $m->setTrackingNum('ZZTRK00' . $recordId);
        $m->setSellingPrice($v['sellingPrice']);
        $m->setShippingFee($v['shippingFee']);
        $m->setShippingFeeByCust(0.0);
        $m->setVoucher(1.25);
        $m->setShippingState('Johor');
        $m->setShippingWeight(3.0);
        $m->setBillNo($v['billno']);
        $m->setDateStockOut($v['dateStockOut']);
        if ($v['withPlatformCharges']) {
            $m->setPlatformCharges(new Lazada(3.2));
        }
        $m->setDirectCharges(new BankIn(10.0));

        return $m;
    }

    /**
     * @param array<int, MonthlyRecord> $records
     */
    private function render(array $records): string
    {
        $editor = new RecordEditor();
        $editor->setMonthlyRecords($records, 1);

        return $editor->getTable();
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    /** @return array<string, string> input name => value, in document order */
    private function inputValuesByName(string $html): array
    {
        $values = [];
        foreach ($this->xpath($html)->query('//tbody//input') as $input) {
            /** @var DOMElement $input */
            $values[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        return $values;
    }
}
