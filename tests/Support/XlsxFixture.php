<?php

declare(strict_types=1);

namespace test\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes small .xlsx fixtures at test time (needs ext-zip, i.e. the Docker test container).
 *
 * The platform parsers index rows by column POSITION (Lazada reads up to column 58, BigSeller up to 64),
 * so the header row is padded with `COL_n` names up to `$minColumns`.
 */
final class XlsxFixture
{
    /**
     * @param array<int, array<int, mixed>> $rows first row is the header
     * @return string absolute path of a temp file (deleted on shutdown)
     */
    public static function write(array $rows, int $minColumns = 0): string
    {
        if (count($rows) === 0) {
            throw new \InvalidArgumentException('at least a header row is required');
        }
        $width = $minColumns;
        foreach ($rows as $r) {
            $width = max($width, count($r));
        }
        $header = array_values($rows[0]);
        for ($c = count($header); $c < $width; ++$c) {
            $header[] = 'COL_' . $c;
        }
        $rows[0] = $header;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $cells) {
            $cells = array_values($cells);
            for ($c = 0; $c < $width; ++$c) {
                $value = array_key_exists($c, $cells) ? $cells[$c] : '';
                // [columnIndex, row] - both 1-based (array coordinates are supported since PhpSpreadsheet 1.23)
                $sheet->setCellValue([$c + 1, $rowIndex + 1], $value);
            }
        }

        $path = self::tempPath('xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public static function tempPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cardeco-fixture-');
        $withExtension = $path . '.' . $extension;
        rename($path, $withExtension);
        register_shutdown_function(function () use ($withExtension) {
            if (is_file($withExtension)) {
                @unlink($withExtension);
            }
        });

        return $withExtension;
    }
}
