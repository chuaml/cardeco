<?php

declare(strict_types=1);

namespace test\Support;

/**
 * Writes small CSV fixtures at test time for the Shopee / Lazada / TikTok / DateStockOut parsers,
 * which address columns by POSITION. Rows shorter than `$minColumns` are padded with ''.
 */
final class CsvFixture
{
    /**
     * @param array<int, array<int, mixed>> $rows first row is usually the header
     * @return string absolute path of a temp file (deleted on shutdown)
     */
    public static function write(array $rows, string $delimiter = ',', int $minColumns = 0): string
    {
        $path = XlsxFixture::tempPath('csv');
        $fh = fopen($path, 'w');
        foreach ($rows as $cells) {
            $cells = array_values($cells);
            for ($c = count($cells); $c < $minColumns; ++$c) {
                $cells[] = '';
            }
            fputcsv($fh, $cells, $delimiter);
        }
        fclose($fh);

        return $path;
    }

    /**
     * Builds a row of `$width` empty cells with the given positional values set, e.g. [0 => 'ORDER1', 16 => '2'].
     *
     * @param array<int, mixed> $positional column index => value
     * @return array<int, mixed>
     */
    public static function row(int $width, array $positional): array
    {
        $row = array_fill(0, $width, '');
        foreach ($positional as $index => $value) {
            $row[$index] = $value;
        }

        return $row;
    }
}
