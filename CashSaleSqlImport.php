<?php
namespace main;

require('vendor/autoload.php');
require('../db/conn_staff.php');

use Exception;
use InvalidArgumentException;
use Orders\Factory\Excel\CashSales;
use Orders\Factory\Excel\SqlImport;
use Orders\Factory\Excel\ExcelReader;
use PhpOffice\PhpSpreadsheet\IOFactory;

function validateFile(array $file):void
{
    if (count($file) !== 5) {
        throw new InvalidArgumentException('expected $_FILES[0]');
    }
    
    if ($file['error'] !== 0) {
        throw new Exception('file upload error.');
    }

    $uploadedFileExt = pathinfo(basename($file['name']), PATHINFO_EXTENSION);
    if ($uploadedFileExt !== 'xlsx') {
        throw new Exception('invalid file extension: ' .$uploadedFileExt);
    }
}

$errmsg = '';
try {
    if (isset($_POST['fileTab']) && isset($_FILES['dataFile'])) {
        $file =& $_FILES['dataFile'];
        $fileTab = $_POST['fileTab'] ?? null;
        $startRowPos = $_POST['startRowPos'] ?? 1;
        $lastRowPos = $_POST['lastRowPos'] ?? -1;
        $lastRowPos = intval($lastRowPos);

        validateFile($file);

        $list = CashSales::transformToCashSales($con, $fileTab, ExcelReader::fetch($file['tmp_name'], $fileTab, $startRowPos, $lastRowPos));
        $Spreadsheet = SqlImport::loadSpreadsheet($list);
        if (error_get_last() !== null) {
            http_response_code(500);
            throw new Exception('Some Errors have occoured.');
        }

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="CashSales for SQL Import.xlsx"');
        header('Cache-Control: max-age=0');
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
    }
} catch (Exception $e) {
    $errmsg = $e->getMessage();
} finally {
    $con->close();
}

require('view/CashsaleSqlImport.html');
