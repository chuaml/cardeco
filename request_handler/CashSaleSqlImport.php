<?php

namespace main;

use Exception;
use HTML\TableDisplayer;
use InvalidArgumentException;
use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\SqlImport;
use Orders\Factory\Excel\ExcelReader;
use PhpOffice\PhpSpreadsheet\IOFactory;

function validateFile(array $file): void
{
    if (count($file) !== 5) {
        throw new InvalidArgumentException('expected $_FILES[0]');
    }

    if ($file['error'] !== 0) {
        throw new Exception('file upload error.');
    }

    $uploadedFileExt = pathinfo(basename($file['name']), PATHINFO_EXTENSION);
    if ($uploadedFileExt !== 'xlsx') {
        throw new Exception('invalid file extension: ' . $uploadedFileExt);
    }
}

$table = null;
$outputData_json = null;
$errmsg = '';
try {
    if (isset($_POST['fileTab']) && isset($_FILES['dataFile'])) {
        $file = &$_FILES['dataFile'];
        $fileTab = $_POST['fileTab'] ?? null;
        $startRowPos = $_POST['startRowPos'] ?? 1;
        $lastRowPos = $_POST['lastRowPos'] ?? -1;
        $lastRowPos = intval($lastRowPos);

        validateFile($file);

        $rows = (new ExcelReader($file['tmp_name']))->read($fileTab, $startRowPos, $lastRowPos);
        $list = CashSales::transformToCashSales($con, $fileTab, iterator_to_array($rows));
        // \console\dev::dumpjson($list);

        if (isset($_POST['doExport']) === true) {
            $Spreadsheet = SqlImport::loadSpreadsheet($list);
            if (error_get_last() !== null) {
                http_response_code(500);
                throw new Exception('Some Errors have occoured.');
            }

            // // Redirect output to a client’s web browser (Xlsx)
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="CashSales for SQL Import.xlsx"');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');

            // If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0

            $writer = IOFactory::createWriter($Spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit();
        } else {
            $table = new TableDisplayer($list, 'tblCashSaleImport');
        }
    }
    require('view/CashsaleSqlImport.html');
} finally {
    $con->close();
}
