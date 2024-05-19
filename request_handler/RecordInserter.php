<?php

namespace main;

use \Orders\Factory\Record;
use \Orders\RecordInserter;
use \Orders\PaymentCharges\PlatformCharges;
use \Orders\Lazada\AutoFilling;
use \Exception;

$error = new Exception();
$msg = '';

if (isset($_POST['r'])) {
    function insertRecord(\mysqli $con, array $r, string $platform): void
    {
        $Rows = array_filter($r, function ($row) {
            if (strlen(trim($row['orderNum'])) > 0 && strlen(trim($row['sku'])) > 0) {
                return $row;
            }
        });
        $RecordFac = new Record($Rows);
        $list = $RecordFac->generateRecords();
        if ($_POST['platform'] === PlatformCharges::TYPE_OF_CHARGES['Lazada']) {
            AutoFilling::setShippingWeightByLzdProduct($con, $list);
            AutoFilling::setShippingFeeByWeight($list);
        }

        $Inserter = new RecordInserter($platform);

        $tmpFile = tmpfile();
        if (!$tmpFile) {
            throw new \Exception("I/O Exception. Fail to create tmp file.");
        }
        try {
            $file = stream_get_meta_data($tmpFile)['uri'];
            $listPath = explode('\\', $file);
            foreach ($Rows as $row) {
                fwrite($tmpFile, implode('', $row));
            }

            $con->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
            try {
                $Inserter->insertLog(
                    $con,
                    $file,
                    end($listPath)
                );
                $Inserter->insert($con, $list);
            } catch (Exception $e) {
                $con->rollback();
                throw $e;
            }
        } finally {
            fclose($tmpFile);
        }
    }

    try {
        try {
            insertRecord($con, $_POST['r'], $_POST['platform']);
            $msg = 'Done. Records added';
        } finally {
            $con->close();
        }
    } catch (Exception $e) {
        $error = $e;
    }
    // var_dump($_POST);
}

require('view/RecordInserter.html');
