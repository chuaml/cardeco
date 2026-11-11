<?php

declare(strict_types=1);

namespace test\Unit\Lazada;

use Lazada\Factory\LzdItemFactory;
use Lazada\Item;
use PHPUnit\Framework\TestCase;
use test\Support\CsvFixture;
use test\Support\XlsxFixture;

/**
 * Characterization of Lazada\Factory\LzdItemFactory (src/Legacy/Lazada/Factory/LzdItemFactory.php):
 * the ';'-delimited Lazada product export -> Lazada\Item[] parser behind /LzdItemManager.
 *
 * The factory maps columns by HEADER NAME (LzdItemFactory::TARGET_FIELD) and insists on >= 167 columns,
 * so every fixture here is a synthetic 167-wide sheet with the 14 target names placed at known positions.
 */
final class LzdItemFactoryTest extends TestCase
{
    const WIDTH = 167;

    /** header name => column position used by the default synthetic header */
    const FIELD_POSITION = [
        'Shop SKU' => 0,
        'SellerSku' => 1,
        'name' => 5,
        'special_price' => 9,
        'color_family' => 20,
        'MainImage' => 40,
        'Image2' => 41,
        'Image3' => 42,
        'Image4' => 43,
        'Image5' => 44,
        'package_weight' => 150,
        'package_length' => 151,
        'package_width' => 152,
        'package_height' => 153,
    ];

    /** a complete, representative product row (header name => value) */
    const SAMPLE = [
        'Shop SKU' => ' ZZLZD-0001 ',
        'SellerSku' => 'ZZTEST-001',
        'name' => 'Test Seat Cover',
        'special_price' => '12.90',
        'color_family' => 'Black',
        'MainImage' => 'https://img.example/1.jpg',
        'Image2' => 'https://img.example/2.jpg',
        'Image3' => '',
        'Image4' => '',
        'Image5' => '',
        'package_weight' => '0.5',
        'package_length' => '30',
        'package_width' => '20',
        'package_height' => '10.5',
    ];

    public function testConstructor_MissingFile_ThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('file does not exist: /no/such/dir/lzd-products.csv ');
        new LzdItemFactory('/no/such/dir/lzd-products.csv');
    }

    public function testGenerateRecords_MapsEveryTargetHeaderToTheItemFields(): void
    {
        $path = CsvFixture::write([self::header(), self::row(self::SAMPLE)], ';');

        $list = (new LzdItemFactory($path))->generateRecords();

        $this->assertCount(1, $list);
        $this->assertInstanceOf(Item::class, $list[0]);
        $this->assertSampleItem($list[0]);
    }

    public function testGenerateRecords_ColumnsAreResolvedByHeaderNameNotPosition(): void
    {
        // same names, completely different positions -> identical Item
        $positions = [
            'package_height' => 2, 'package_width' => 3, 'package_length' => 4, 'package_weight' => 6,
            'Image5' => 10, 'Image4' => 11, 'Image3' => 12, 'Image2' => 13, 'MainImage' => 14,
            'color_family' => 60, 'special_price' => 61, 'name' => 62, 'SellerSku' => 100, 'Shop SKU' => 166,
        ];
        $path = CsvFixture::write([self::header($positions), self::row(self::SAMPLE, $positions)], ';');

        $list = (new LzdItemFactory($path))->generateRecords();

        $this->assertSampleItem($list[0]);
    }

    public function testGenerateRecords_MoreThan167Columns_IsAccepted(): void
    {
        $path = CsvFixture::write([self::header(self::FIELD_POSITION, 180), self::row(self::SAMPLE, self::FIELD_POSITION, 180)], ';');

        $list = (new LzdItemFactory($path))->generateRecords();

        $this->assertSame('ZZLZD-0001', $list[0]->lzdSku);
    }

    public function testGenerateRecords_MultipleRows_PreservesFileOrder(): void
    {
        $second = array_merge(self::SAMPLE, ['Shop SKU' => 'ZZLZD-0002', 'SellerSku' => 'ZZTEST-002']);
        $third = array_merge(self::SAMPLE, ['Shop SKU' => 'ZZLZD-0003', 'SellerSku' => 'ZZTEST-003']);
        $path = CsvFixture::write([self::header(), self::row(self::SAMPLE), self::row($second), self::row($third)], ';');

        $list = (new LzdItemFactory($path))->generateRecords();

        $this->assertSame(['ZZLZD-0001', 'ZZLZD-0002', 'ZZLZD-0003'], array_map(function (Item $i) {
            return $i->lzdSku;
        }, $list));
        $this->assertSame(['ZZTEST-001', 'ZZTEST-002', 'ZZTEST-003'], array_map(function (Item $i) {
            return $i->sellerSku;
        }, $list));
    }

    public function testGenerateRecords_HeaderOnly_ReturnsEmptyList(): void
    {
        $path = CsvFixture::write([self::header()], ';');

        $this->assertSame([], (new LzdItemFactory($path))->generateRecords());
    }

    /**
     * @dataProvider numericCastProvider
     */
    public function testGenerateRecords_NumericColumnsAreCastWithDoubleCast(string $raw, float $expected): void
    {
        $values = array_merge(self::SAMPLE, [
            'special_price' => $raw, 'package_weight' => $raw, 'package_length' => $raw,
            'package_width' => $raw, 'package_height' => $raw,
        ]);
        $path = CsvFixture::write([self::header(), self::row($values)], ';');

        $item = (new LzdItemFactory($path))->generateRecords()[0];

        $this->assertSame($expected, $item->price);
        $this->assertSame($expected, $item->weight);
        $this->assertSame($expected, $item->length);
        $this->assertSame($expected, $item->width);
        $this->assertSame($expected, $item->height);
    }

    public function numericCastProvider(): array
    {
        return [
            'decimal' => ['12.90', 12.9],
            'integer' => ['30', 30.0],
            'padded' => [' 1.5 ', 1.5],
            'empty -> 0' => ['', 0.0],
            'non numeric -> 0' => ['n/a', 0.0],
            'leading number kept' => ['2kg', 2.0],
        ];
    }

    public function testGenerateRecords_TextColumnsAreTrimmed_ImagesKeepFivePositions(): void
    {
        $values = array_merge(self::SAMPLE, [
            'SellerSku' => "\tZZTEST-001 ", 'name' => '  Test Seat Cover  ', 'color_family' => ' Black ',
            'MainImage' => ' https://img.example/1.jpg ', 'Image2' => '', 'Image3' => '', 'Image4' => '', 'Image5' => ' ',
        ]);
        $path = CsvFixture::write([self::header(), self::row($values)], ';');

        $item = (new LzdItemFactory($path))->generateRecords()[0];

        $this->assertSame('ZZTEST-001', $item->sellerSku);
        $this->assertSame('Test Seat Cover', $item->name);
        $this->assertSame('Black', $item->color);
        $this->assertSame(['https://img.example/1.jpg', '', '', '', ''], $item->images);
    }

    public function testGenerateRecords_QuotedFieldContainingDelimiter_IsOneField(): void
    {
        $values = array_merge(self::SAMPLE, ['name' => 'Seat Cover; "Deluxe", black']);
        $path = CsvFixture::write([self::header(), self::row($values)], ';');

        $item = (new LzdItemFactory($path))->generateRecords()[0];

        $this->assertSame('Seat Cover; "Deluxe", black', $item->name);
    }

    public function testGenerateRecords_HeaderNamesAreTrimmedBeforeMatching(): void
    {
        $header = self::header();
        $header[self::FIELD_POSITION['name']] = '  name ';
        $header[self::FIELD_POSITION['SellerSku']] = "SellerSku\t";
        $path = CsvFixture::write([$header, self::row(self::SAMPLE)], ';');

        $item = (new LzdItemFactory($path))->generateRecords()[0];

        $this->assertSame('Test Seat Cover', $item->name);
        $this->assertSame('ZZTEST-001', $item->sellerSku);
    }

    public function testGenerateRecords_HeaderNameMatchIsCaseSensitive(): void
    {
        $header = self::header();
        $header[self::FIELD_POSITION['name']] = 'Name';
        $path = CsvFixture::write([$header, self::row(self::SAMPLE)], ';');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("field: 'name' does not exist.");
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_Utf8BomBeforeUnquotedFirstHeader_IsTolerated(): void
    {
        // LzdItemFactory.php:97-103 - the BOM glued to 'Shop SKU' is stripped by the substr() fallback
        $path = self::writeRaw("\xEF\xBB\xBF" . implode(';', self::header()) . "\n", [self::row(self::SAMPLE)]);

        $item = (new LzdItemFactory($path))->generateRecords()[0];

        $this->assertSame('ZZLZD-0001', $item->lzdSku);
        $this->assertSame('ZZTEST-001', $item->sellerSku);
    }

    public function testGenerateRecords_Utf8BomBeforeQuotedFirstHeader_documentsCurrentBehaviour_Throws(): void
    {
        // a BOM in front of "Shop SKU" (quoted) leaves the closing quote in the cell -> fallback at :99-100 misses
        $path = self::writeRaw("\xEF\xBB\xBF\"Shop SKU\";" . implode(';', array_slice(self::header(), 1)) . "\n", [self::row(self::SAMPLE)]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("field: 'Shop SKU' does not exist.");
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_BomOnlyRescuesTheFirstTargetField(): void
    {
        // the BOM workaround is hard-wired to TARGET_FIELD[0]; any other first column stays unmatched
        $positions = array_merge(self::FIELD_POSITION, ['Shop SKU' => 1, 'SellerSku' => 0]);
        $path = self::writeRaw("\xEF\xBB\xBF" . implode(';', self::header($positions)) . "\n", [self::row(self::SAMPLE, $positions)]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("field: 'SellerSku' does not exist.");
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_FewerThan167Columns_ThrowsInvalidFile(): void
    {
        $path = CsvFixture::write([self::header(self::FIELD_POSITION, 166), self::row(self::SAMPLE, self::FIELD_POSITION, 166)], ';');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('invalid file. must lazada products info .csv');
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_MissingTargetHeader_ThrowsNamingTheField(): void
    {
        $header = self::header();
        $header[self::FIELD_POSITION['special_price']] = 'price';
        $path = CsvFixture::write([$header, self::row(self::SAMPLE)], ';');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("field: 'special_price' does not exist.");
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_DuplicateTargetHeader_Throws(): void
    {
        $header = self::header();
        $header[self::FIELD_POSITION['name'] + 1] = 'name';
        $path = CsvFixture::write([$header, self::row(self::SAMPLE)], ';');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("column field name must be unique. duplicate field: 'name'.");
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_RowWithDifferentColumnCount_ThrowsWithLineNumber(): void
    {
        $path = CsvFixture::write([
            self::header(),
            self::row(self::SAMPLE),
            self::row(self::SAMPLE, self::FIELD_POSITION, 166),
        ], ';');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('inconsistence number of columns at line 3, invalid file or data cannot parse correctly.');
        (new LzdItemFactory($path))->generateRecords();
    }

    public function testGenerateRecords_EmptyFile_documentsCurrentBehaviour_FailsOnCountOfFalse(): void
    {
        // LzdItemFactory.php:45 tests `$r === null`, but fgetcsv() returns false at EOF, so count(false) runs at :49
        // (TypeError on PHP 8; "count(): Parameter must be an array" warning on 7.x)
        $path = CsvFixture::write([], ';');

        $this->expectException(PHP_VERSION_ID >= 80000 ? \TypeError::class : \ErrorException::class);
        $this->expectExceptionMessageMatches('/count\(\)/');
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            (new LzdItemFactory($path))->generateRecords();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @group known-bug
     */
    public function testGenerateRecords_EmptyFile_ThrowsFailToParse(): void
    {
        // LzdItemFactory.php:45 - should compare against false and raise 'fail to parse csv file data: <file>'
        $path = CsvFixture::write([], ';');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('fail to parse csv file data: ' . $path);
        (new LzdItemFactory($path))->generateRecords();
    }

    // ---------------------------------------------------------------------------------------------

    private function assertSampleItem(Item $item): void
    {
        $this->assertSame('ZZLZD-0001', $item->lzdSku, 'Shop SKU is trimmed');
        $this->assertSame('ZZTEST-001', $item->sellerSku);
        $this->assertSame('Test Seat Cover', $item->name);
        $this->assertSame(12.9, $item->price);
        $this->assertSame('Black', $item->color);
        $this->assertSame(['https://img.example/1.jpg', 'https://img.example/2.jpg', '', '', ''], $item->images);
        $this->assertSame(0.5, $item->weight);
        $this->assertSame(30.0, $item->length);
        $this->assertSame(20.0, $item->width);
        $this->assertSame(10.5, $item->height);
        $this->assertNull($item->id, 'id is only known after a DB round-trip');
        $this->assertNull($item->status);
    }

    /**
     * @param array<string, int> $positions
     * @return array<int, string>
     */
    private static function header(array $positions = self::FIELD_POSITION, int $width = self::WIDTH): array
    {
        $header = [];
        for ($i = 0; $i < $width; ++$i) {
            $header[] = 'col_' . $i;
        }
        foreach ($positions as $name => $index) {
            $header[$index] = $name;
        }

        return $header;
    }

    /**
     * @param array<string, string> $values header name => cell value
     * @param array<string, int> $positions
     * @return array<int, string>
     */
    private static function row(array $values, array $positions = self::FIELD_POSITION, int $width = self::WIDTH): array
    {
        $row = array_fill(0, $width, '');
        foreach ($values as $name => $value) {
            $row[$positions[$name]] = $value;
        }

        return $row;
    }

    /**
     * Writes a raw (pre-formatted) header line followed by fputcsv() rows.
     *
     * @param array<int, array<int, string>> $rows
     */
    private static function writeRaw(string $headerLine, array $rows): string
    {
        $path = XlsxFixture::tempPath('csv');
        $fh = fopen($path, 'w');
        fwrite($fh, $headerLine);
        foreach ($rows as $cells) {
            fputcsv($fh, $cells, ';');
        }
        fclose($fh);

        return $path;
    }
}
