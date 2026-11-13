<?php

declare(strict_types=1);

namespace test\Integration\Orders\Factory\Excel;

use Orders\Factory\Excel\SqlImport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPUnit\Framework\TestCase;
use test\Support\XlsxFixture;

/**
 * Characterization of Orders\Factory\Excel\SqlImport (src/Legacy/Orders/Factory/Excel/SqlImport.php): the workbook
 * that /CashSaleSqlImport offers as "CashSales for SQL Import.xlsx", and of saving it with the Xlsx writer the way
 * request_handler/CashSaleSqlImport.php does.
 *
 * This is the only place where the application WRITES a workbook (everything else reads uploads), so it is where a
 * different PhpSpreadsheet version shows first. The file is imported into SQL Accounting, hence the checks on what
 * a reader gets back.
 *
 * No database. It lives in the integration suite because the Xlsx writer needs ext-zip, which the "unit" suite
 * (also run on the host PHP) must not require.
 *
 * @requires extension zip
 */
final class SqlImportTest extends TestCase
{
    /** The 22 columns of Orders\Factory\Excel\CashSales::transformToCashSales(), in order. */
    const COLUMNS = [
        'DocDate', 'DocNo(20)', 'Code(10)', 'CompanyName(100)', 'Agent(10)', 'TERMS(10)', 'Description_HDR(200)',
        'SEQ', 'ItemCode(30)', 'Description_DTL(200)', 'Qty', 'UOM', 'UnitPrice', 'DISC(20)', 'Tax(10)',
        'TaxInclusive', 'TaxAmt', 'Amount', 'P_AMOUNT', 'P_PAYMENTMETHOD', 'P_BANKCHARGE', 'DOCREF1',
    ];

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed> one import line, typed like transformToCashSales() returns it
     */
    private function entry(array $overrides = []): array
    {
        return array_merge([
            'DocDate' => '15-01-24',
            'DocNo(20)' => 'ZZTEST-CS1',
            'Code(10)' => '300-C0006',
            'CompanyName(100)' => 'CASH A/C - LAZADA (CAR DECO)',
            'Agent(10)' => '----',
            'TERMS(10)' => 'C.O.D.',
            'Description_HDR(200)' => 'Cash Sales',
            'SEQ' => 1,
            'ItemCode(30)' => 'ZZTEST-001',
            'Description_DTL(200)' => 'ZZTEST item A 4" <b>&</b>',
            'Qty' => 1,
            'UOM' => 'PCS',
            'UnitPrice' => 10.0,
            'DISC(20)' => 1.5,
            'Tax(10)' => '',
            'TaxInclusive' => 0.0,
            'TaxAmt' => 0.0,
            'Amount' => 8.5,
            'P_AMOUNT' => 8.5,
            'P_PAYMENTMETHOD' => '321-000',
            'P_BANKCHARGE' => 0.75,
            'DOCREF1' => '00123456789012',
        ], $overrides);
    }

    public function testEntryFixture_HasTheTwentyTwoImportColumns(): void
    {
        $this->assertSame(self::COLUMNS, array_keys($this->entry()));
    }

    public function testLoadSpreadsheet_HeaderRow_IsTheEntryKeysInBoldOnYellow(): void
    {
        $sheet = SqlImport::loadSpreadsheet([$this->entry()])->getActiveSheet();

        $this->assertSame('Cash Sales', $sheet->getTitle());
        $this->assertSame([self::COLUMNS], $sheet->rangeToArray('A1:V1', null, false, false, false));
        foreach (['A1', 'L1', 'V1'] as $coordinate) {
            $style = $sheet->getStyle($coordinate);
            $this->assertTrue($style->getFont()->getBold(), $coordinate . ' is bold');
            $this->assertSame(Fill::FILL_SOLID, $style->getFill()->getFillType(), $coordinate . ' is filled');
            $this->assertSame('FFFFFF00', $style->getFill()->getStartColor()->getARGB(), $coordinate . ' is yellow');
        }
        $this->assertSame(24.0, (float) $sheet->getRowDimension(1)->getRowHeight());
        $this->assertTrue($sheet->getColumnDimension('A')->getAutoSize());
    }

    public function testLoadSpreadsheet_DataCells_AreAllWrittenAsText(): void
    {
        // setCellValueExplicit(..., TYPE_STRING): nothing is converted to a number or a date, so a leading zero of an
        // order number survives and SQL Accounting gets exactly these characters
        $sheet = SqlImport::loadSpreadsheet([$this->entry()])->getActiveSheet();

        $this->assertSame([[
            '15-01-24', 'ZZTEST-CS1', '300-C0006', 'CASH A/C - LAZADA (CAR DECO)', '----', 'C.O.D.', 'Cash Sales',
            '1', 'ZZTEST-001', 'ZZTEST item A 4" <b>&</b>', '1', 'PCS', '10', '1.5', '',
            '0', '0', '8.5', '8.5', '321-000', '0.75', '00123456789012',
        ]], $sheet->rangeToArray('A2:V2', null, false, false, false));
        foreach (['A2', 'H2', 'M2', 'V2'] as $coordinate) {
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($coordinate)->getDataType(), $coordinate);
        }
    }

    public function testLoadSpreadsheet_NumberFormats_documentsCurrentBehaviour_DocRefFormatSkipsTheLastRow(): void
    {
        // setAllData() formats column A of every data row. addStyle(2, count($entries)) is one row short, because
        // the data starts in row 2: the last data row keeps the General format in the DOCREF1 column.
        // (No visible effect: the cells are text.)
        $sheet = SqlImport::loadSpreadsheet([$this->entry(), $this->entry(['SEQ' => 2])])->getActiveSheet();

        $this->assertSame('dd-mm-yy', $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame('dd-mm-yy', $sheet->getStyle('A3')->getNumberFormat()->getFormatCode());
        $this->assertSame('0', $sheet->getStyle('V2')->getNumberFormat()->getFormatCode());
        $this->assertSame('General', $sheet->getStyle('V3')->getNumberFormat()->getFormatCode());
    }

    public function testLoadSpreadsheet_UnknownUom_documentsCurrentBehaviour_IsAnEmptyTextCellWithoutTheRedFill(): void
    {
        // CashSales::transformToCashSales() yields UOM null for an item that is not in stock_items. addStyle() means
        // to mark that cell red (`getValue() === null`), but the cell was written as TEXT first, which turns null
        // into '': the mark never appears. (Row 2 of two entries, so that addStyle() does visit the row.)
        $sheet = SqlImport::loadSpreadsheet([$this->entry(['UOM' => null]), $this->entry(['SEQ' => 2])])->getActiveSheet();

        $this->assertSame('', $sheet->getCell('L2')->getValue());
        $this->assertSame(Fill::FILL_NONE, $sheet->getStyle('L2')->getFill()->getFillType());
        $this->assertSame('0', $sheet->getStyle('V2')->getNumberFormat()->getFormatCode(), 'addStyle() did visit row 2');
    }

    public function testLoadSpreadsheet_NoEntries_IsAnEmptySheetNamedCashSales(): void
    {
        $sheet = SqlImport::loadSpreadsheet([])->getActiveSheet();

        $this->assertSame('Cash Sales', $sheet->getTitle());
        $this->assertSame(1, $sheet->getHighestRow());
        $this->assertNull($sheet->getCell('A1')->getValue());
    }

    public function testXlsxWriter_SavedLikeTheDownload_ReadsBackWithTheSameTextInEveryCell(): void
    {
        // request_handler/CashSaleSqlImport.php: IOFactory::createWriter($Spreadsheet, 'Xlsx')->save('php://output')
        $entries = [
            $this->entry(),
            $this->entry(['SEQ' => 2, 'ItemCode(30)' => 'SHIPPING', 'Description_DTL(200)' => 'SHIPPING FEE', 'UOM' => 'UNIT']),
        ];
        $path = XlsxFixture::tempPath('xlsx');

        IOFactory::createWriter(SqlImport::loadSpreadsheet($entries), 'Xlsx')->save($path);

        $this->assertGreaterThan(0, filesize($path));
        $sheet = IOFactory::load($path)->getSheetByName('Cash Sales');
        $this->assertNotNull($sheet);
        $rows = $sheet->rangeToArray('A1:V3', null, false, false, false);
        $this->assertSame(self::COLUMNS, $rows[0]);
        $this->assertSame('15-01-24', $rows[1][0], 'the date stays the text that was written, it is not parsed');
        $this->assertSame('ZZTEST item A 4" <b>&</b>', $rows[1][9]);
        $this->assertSame('10', $rows[1][12]);
        $this->assertSame('00123456789012', $rows[1][21], 'leading zeros of the order number survive');
        $this->assertSame(['2', 'SHIPPING', 'SHIPPING FEE', '1', 'UNIT'], array_slice($rows[2], 7, 5));
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('V2')->getDataType());
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold(), 'styles are written too');
    }
}
