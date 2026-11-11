<?php

declare(strict_types=1);

namespace test\Unit\Orders\Factory;

use Exception;
use InvalidArgumentException;
use Orders\Factory\DateStockOut;
use Orders\MonthlyRecord;
use PHPUnit\Framework\TestCase;
use test\Support\CsvFixture;
use test\Support\XlsxFixture;
use test\Unit\Support\PhpErrors;

require_once __DIR__ . '/../../Support/PhpErrors.php';

/**
 * Characterization of Orders\Factory\DateStockOut (src/Legacy/Orders/Factory/DateStockOut.php): the
 * header-less 2-column upload "trackingNum,dateStockOut" of /RecordUpdater -> Orders\MonthlyRecord[].
 */
final class DateStockOutFactoryTest extends TestCase
{
    public function testConstructor_missingFile_throwsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('file does not exist');

        new DateStockOut('/nonexistent/zz-stock-out.csv');
    }

    public function testGenerateRecords_twoColumnRows_mapTrackingNumAndDateStockOut(): void
    {
        $path = CsvFixture::write([['ZZTRK001', '2024-01-15'], ['ZZTRK002', '2024-01-16']]);

        $records = (new DateStockOut($path))->generateRecords();

        $this->assertCount(2, $records);
        $this->assertContainsOnlyInstancesOf(MonthlyRecord::class, $records);
        $this->assertSame('ZZTRK001', $records[0]->trackingNum);
        $this->assertSame('15-Jan-2024', $records[0]->dateStockOut);
        $this->assertSame('ZZTRK002', $records[1]->trackingNum);
        $this->assertSame('16-Jan-2024', $records[1]->dateStockOut);
    }

    public function testGenerateRecords_onlyTrackingNumAndDateStockOutAreSet(): void
    {
        $path = CsvFixture::write([['ZZTRK001', '2024-01-15']]);

        $record = (new DateStockOut($path))->generateRecords()[0];

        $this->assertNull($record->recordId);
        $this->assertNull($record->orderNum);
        $this->assertSame('', $record->date);
        $this->assertSame('', $record->billno);
        $this->assertNull($record->getItem());
        $this->assertNull($record->getPlatformCharges());
        $this->assertSame(0.0, $record->sellingPrice);
    }

    public function testGenerateRecords_firstRowIsData_documentsCurrentBehaviour_noHeaderIsSkipped(): void
    {
        // DateStockOut.php generateRecords(): the first fgetcsv() row is appended to the list like any other
        $path = CsvFixture::write([['ZZTRK001', '2024-01-15']]);

        $records = (new DateStockOut($path))->generateRecords();

        $this->assertCount(1, $records);
        $this->assertSame('ZZTRK001', $records[0]->trackingNum);
    }

    public function testGenerateRecords_fileWithHeaderRow_throwsBecauseHeaderIsParsedAsData(): void
    {
        $path = CsvFixture::write([['trackingNum', 'dateStockOut'], ['ZZTRK001', '2024-01-15']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid date format dateStockOut');

        (new DateStockOut($path))->generateRecords();
    }

    public function testGenerateRecords_emptyFile_returnsEmptyList(): void
    {
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, '');

        $this->assertSame([], (new DateStockOut($path))->generateRecords());
    }

    /** @return array<string, array<int, string>> */
    public function provideDates(): array
    {
        return [
            'iso' => ['2024-01-15', '15-Jan-2024'],
            'd/m/Y is read day first' => ['16/01/2024', '16-Jan-2024'],
            'Y/m/d' => ['2024/01/17', '17-Jan-2024'],
            'already d-M-Y' => ['18-Jan-2024', '18-Jan-2024'],
            'with time' => ['2024-01-19 08:30:00', '19-Jan-2024'],
            'surrounding spaces' => [' 2024-01-20 ', '20-Jan-2024'],
        ];
    }

    /**
     * @dataProvider provideDates
     */
    public function testGenerateRecords_dateStockOut_isNormalisedToDayMonthYear(string $cell, string $expected): void
    {
        $path = CsvFixture::write([['ZZTRK001', $cell]]);

        $this->assertSame($expected, (new DateStockOut($path))->generateRecords()[0]->dateStockOut);
    }

    public function testGenerateRecords_monthFirstDate_throwsInvalidArgumentException(): void
    {
        // '01/15/2024' becomes '01-15-2024', which date_create() reads as d-m-Y -> month 15
        $path = CsvFixture::write([['ZZTRK001', '01/15/2024']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid date format');

        (new DateStockOut($path))->generateRecords();
    }

    public function testGenerateRecords_emptyDate_throwsInvalidArgumentException(): void
    {
        $path = CsvFixture::write([['ZZTRK001', '']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty date value');

        (new DateStockOut($path))->generateRecords();
    }

    public function testGenerateRecords_utf8BomAndControlCharacters_areStripped(): void
    {
        // PRINTABLE_CHAR_PATTERN removes every non-printable byte: BOM, tabs, CR
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, "\xEF\xBB\xBFZZTRK\t001,2024-01-15\r\n");

        $record = (new DateStockOut($path))->generateRecords()[0];

        $this->assertSame('ZZTRK001', $record->trackingNum);
        $this->assertSame('15-Jan-2024', $record->dateStockOut);
    }

    public function testGenerateRecords_trackingNumber_isTrimmed(): void
    {
        $path = CsvFixture::write([[' ZZTRK001 ', '2024-01-15']]);

        $this->assertSame('ZZTRK001', (new DateStockOut($path))->generateRecords()[0]->trackingNum);
    }

    public function testGenerateRecords_extraColumns_areIgnored(): void
    {
        $path = CsvFixture::write([['ZZTRK001', '2024-01-15', 'ignored', 'also ignored']]);

        $record = (new DateStockOut($path))->generateRecords()[0];

        $this->assertSame('ZZTRK001', $record->trackingNum);
        $this->assertSame('15-Jan-2024', $record->dateStockOut);
    }

    public function testGenerateRecords_blankLineBetweenRows_documentsCurrentBehaviour_raisesUndefinedOffset(): void
    {
        // fgetcsv() yields [null] for a blank line; DateStockOut.php getRecord() then reads $r[1]
        // (Notice "Undefined offset: 1" on PHP 7, Warning "Undefined array key 1" on PHP 8)
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, "ZZTRK001,2024-01-15\n\nZZTRK002,2024-01-16\n");

        $e = PhpErrors::capture(function () use ($path) {
            (new DateStockOut($path))->generateRecords();
        });

        $this->assertInstanceOf(\ErrorException::class, $e);
        $this->assertMatchesRegularExpression('/Undefined (offset|array key)[: ]+1/', $e->getMessage());
    }

    /**
     * @group known-bug
     */
    public function testGenerateRecords_blankLineBetweenRows_isSkipped(): void
    {
        // DateStockOut.php getRecord(): a blank line in the upload (common at the end of a hand-made CSV) should be
        // ignored instead of producing an "Undefined offset: 1" notice and a MonthlyRecord with trackingNum ''
        $path = XlsxFixture::tempPath('csv');
        file_put_contents($path, "ZZTRK001,2024-01-15\n\nZZTRK002,2024-01-16\n");

        $records = (new DateStockOut($path))->generateRecords();

        $this->assertSame(['ZZTRK001', 'ZZTRK002'], [$records[0]->trackingNum, $records[1]->trackingNum]);
        $this->assertCount(2, $records);
    }

    public function testGenerateRecords_rowWithOnlyOneColumn_documentsCurrentBehaviour_raisesUndefinedOffset(): void
    {
        $path = CsvFixture::write([['ZZTRK001']]);

        $e = PhpErrors::capture(function () use ($path) {
            (new DateStockOut($path))->generateRecords();
        });

        $this->assertInstanceOf(\ErrorException::class, $e);
        $this->assertMatchesRegularExpression('/Undefined (offset|array key)[: ]+1/', $e->getMessage());
    }
}
