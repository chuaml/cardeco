CashSaleSqlImport.php[36m:[m[1;31m<?php[m
CashSaleSqlImport.php[36m:[m[1;31mnamespace main;[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31mrequire('vendor/autoload.php');[m
CashSaleSqlImport.php[36m:[m[1;31mrequire('../db/conn_staff.php');[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31muse Exception;[m
CashSaleSqlImport.php[36m:[m[1;31muse InvalidArgumentException;[m
CashSaleSqlImport.php[36m:[m[1;31muse Orders\Factory\Excel\CashSales;[m
CashSaleSqlImport.php[36m:[m[1;31muse Orders\Factory\Excel\SqlImport;[m
CashSaleSqlImport.php[36m:[m[1;31muse Orders\Factory\Excel\ExcelReader;[m
CashSaleSqlImport.php[36m:[m[1;31muse PhpOffice\PhpSpreadsheet\IOFactory;[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31mfunction validateFile(array $file):void[m
CashSaleSqlImport.php[36m:[m[1;31m{[m
CashSaleSqlImport.php[36m:[m[1;31m    if (count($file) !== 5) {[m
CashSaleSqlImport.php[36m:[m[1;31m        throw new InvalidArgumentException('expected $_FILES[0]');[m
CashSaleSqlImport.php[36m:[m[1;31m    }[m
CashSaleSqlImport.php[36m:[m[1;31m    [m
CashSaleSqlImport.php[36m:[m[1;31m    if ($file['error'] !== 0) {[m
CashSaleSqlImport.php[36m:[m[1;31m        throw new Exception('file upload error.');[m
CashSaleSqlImport.php[36m:[m[1;31m    }[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31m    $uploadedFileExt = pathinfo(basename($file['name']), PATHINFO_EXTENSION);[m
CashSaleSqlImport.php[36m:[m[1;31m    if ($uploadedFileExt !== 'xlsx') {[m
CashSaleSqlImport.php[36m:[m[1;31m        throw new Exception('invalid file extension: ' .$uploadedFileExt);[m
CashSaleSqlImport.php[36m:[m[1;31m    }[m
CashSaleSqlImport.php[36m:[m[1;31m}[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31m$errmsg = '';[m
CashSaleSqlImport.php[36m:[m[1;31mtry {[m
CashSaleSqlImport.php[36m:[m[1;31m    if (isset($_POST['fileTab']) && isset($_FILES['dataFile'])) {[m
CashSaleSqlImport.php[36m:[m[1;31m        $file =& $_FILES['dataFile'];[m
CashSaleSqlImport.php[36m:[m[1;31m        $fileTab = $_POST['fileTab'] ?? null;[m
CashSaleSqlImport.php[36m:[m[1;31m        $startRowPos = $_POST['startRowPos'] ?? 1;[m
CashSaleSqlImport.php[36m:[m[1;31m        $lastRowPos = $_POST['lastRowPos'] ?? -1;[m
CashSaleSqlImport.php[36m:[m[1;31m        $lastRowPos = intval($lastRowPos);[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31m        validateFile($file);[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31m        $list = CashSales::transformToCashSales($con, $fileTab, ExcelReader::fetch($file['tmp_name'], $fileTab, $startRowPos, $lastRowPos));[m
CashSaleSqlImport.php[36m:[m[1;31m        $Spreadsheet = SqlImport::loadSpreadsheet($list);[m
CashSaleSqlImport.php[36m:[m[1;31m        if (error_get_last() !== null) {[m
CashSaleSqlImport.php[36m:[m[1;31m            http_response_code(500);[m
CashSaleSqlImport.php[36m:[m[1;31m            throw new Exception('Some Errors have occoured.');[m
CashSaleSqlImport.php[36m:[m[1;31m        }[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31m        // Redirect output to a client’s web browser (Xlsx)[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Content-Disposition: attachment;filename="CashSales for SQL Import.xlsx"');[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Cache-Control: max-age=0');[m
CashSaleSqlImport.php[36m:[m[1;31m        // If you're serving to IE 9, then the following may be needed[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Cache-Control: max-age=1');[m
CashSaleSqlImport.php[36m:[m[1;31m    [m
CashSaleSqlImport.php[36m:[m[1;31m        // If you're serving to IE over SSL, then the following may be needed[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1[m
CashSaleSqlImport.php[36m:[m[1;31m        header('Pragma: public'); // HTTP/1.0[m
CashSaleSqlImport.php[36m:[m[1;31m    [m
CashSaleSqlImport.php[36m:[m[1;31m        $writer = IOFactory::createWriter($Spreadsheet, 'Xlsx');[m
CashSaleSqlImport.php[36m:[m[1;31m        $writer->save('php://output');[m
CashSaleSqlImport.php[36m:[m[1;31m        exit();[m
CashSaleSqlImport.php[36m:[m[1;31m    }[m
CashSaleSqlImport.php[36m:[m[1;31m} catch (Exception $e) {[m
CashSaleSqlImport.php[36m:[m[1;31m    $errmsg = $e->getMessage();[m
CashSaleSqlImport.php[36m:[m[1;31m} finally {[m
CashSaleSqlImport.php[36m:[m[1;31m    $con->close();[m
CashSaleSqlImport.php[36m:[m[1;31m}[m
CashSaleSqlImport.php[36m:[m[1;31m[m
CashSaleSqlImport.php[36m:[m[1;31mrequire('view/CashsaleSqlImport.html');[m
Cashsale.php[36m:[m[1;31m<?php [m
Cashsale.php[36m:[m[1;31mnamespace main;[m
Cashsale.php[36m:[m[1;31m[m
Cashsale.php[36m:[m[1;31mrequire_once('inc/class/Orders/Factory/Cashsale.php');[m
Cashsale.php[36m:[m[1;31mrequire_once(__DIR__ .'/../db/conn_staff.php');[m
Cashsale.php[36m:[m[1;31m[m
Cashsale.php[36m:[m[1;31muse \Orders\Factory\Cashsale;[m
Cashsale.php[36m:[m[1;31muse \Exception;[m
Cashsale.php[36m:[m[1;31m[m
Cashsale.php[36m:[m[1;31m$msg = '';[m
Cashsale.php[36m:[m[1;31mtry{[m
Cashsale.php[36m:[m[1;31m    if(isset($_POST['dateStockOut'])){[m
Cashsale.php[36m:[m[1;31m        try{[m
Cashsale.php[36m:[m[1;31m            $CF = new Cashsale($con);[m
Cashsale.php[36m:[m[1;31m            $CF->setMonthlyRecordByDateStockOut($_POST['dateStockOut']);[m
Cashsale.php[36m:[m[1;31m            [m
Cashsale.php[36m:[m[1;31m            $list = $CF->generateRecords();[m
Cashsale.php[36m:[m[1;31m            $list = array_map(function($r){[m
Cashsale.php[36m:[m[1;31m                return $r->getData();[m
Cashsale.php[36m:[m[1;31m            }, $list);[m
Cashsale.php[36m:[m[1;31m        [m
Cashsale.php[36m:[m[1;31m            $MainFolder = 'public/txtImport_file2/';[m
Cashsale.php[36m:[m[1;31m            $targetFolder = $MainFolder .date('Y') .'/' .date('M') . '/' .date('d');[m
Cashsale.php[36m:[m[1;31m            $Date_NOW = date('d-m-Y');[m
Cashsale.php[36m:[m[1;31m            $fileName = '';[m
Cashsale.php[36m:[m[1;31m            if(!is_writeable($MainFolder)){[m
Cashsale.php[36m:[m[1;31m                throw new Exception("Unable to write file. have no permission write to dir: {$targetFolder}.");[m
Cashsale.php[36m:[m[1;31m            }[m
Cashsale.php[36m:[m[1;31m            if(!file_exists($targetFolder)){[m
Cashsale.php[36m:[m[1;31m                mkdir($targetFolder, 0777, true);[m
Cashsale.php[36m:[m[1;31m            }[m
Cashsale.php[36m:[m[1;31m            $i = 0;[m
Cashsale.php[36m:[m[1;31m        [m
Cashsale.php[36m:[m[1;31m            foreach($list as $file){[m
Cashsale.php[36m:[m[1;31m                $fileName = $Date_NOW . '_' . time() . "({$i})";[m
Cashsale.php[36m:[m[1;31m                [m
Cashsale.php[36m:[m[1;31m                $out = fopen("{$targetFolder}/{$fileName}.txt", 'wb');[m
Cashsale.php[36m:[m[1;31m                if($out === false){[m
Cashsale.php[36m:[m[1;31m                    throw new Exception("Unable to open file {$targetFolder}/{$fileName}.txt");[m
Cashsale.php[36m:[m[1;31m                }[m
Cashsale.php[36m:[m[1;31m                try{[m
Cashsale.php[36m:[m[1;31m                    if(!fwrite($out, $file)){[m
Cashsale.php[36m:[m[1;31m                        throw new Exception('Fail to write File ');[m
Cashsale.php[36m:[m[1;31m                    }[m
Cashsale.php[36m:[m[1;31m                }finally{[m
Cashsale.php[36m:[m[1;31m                    fclose($out);[m
Cashsale.php[36m:[m[1;31m                }[m
Cashsale.php[36m:[m[1;31m                ++$i;[m
Cashsale.php[36m:[m[1;31m            }[m
Cashsale.php[36m:[m[1;31m            header('HTTP/1.1 200');[m
Cashsale.php[36m:[m[1;31m            $msg = '<script>alert("Cash sales files exported at: [server]/public/ folder.")</script>';[m
Cashsale.php[36m:[m[1;31m        }catch(Exception $e){[m
Cashsale.php[36m:[m[1;31m            header('HTTP/1.1 500');[m
Cashsale.php[36m:[m[1;31m            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES);[m
Cashsale.php[36m:[m[1;31m        }[m
Cashsale.php[36m:[m[1;31m    }[m
Cashsale.php[36m:[m[1;31m}finally{[m
Cashsale.php[36m:[m[1;31m    $con->close();[m
Cashsale.php[36m:[m[1;31m}[m
Cashsale.php[36m:[m[1;31m[m
Cashsale.php[36m:[m[1;31mrequire('view/Cashsale.html')[m
Cashsale.php[36m:[m[1;31m?>[m
ItemManager.php[36m:[m[1;31m<?php [m
ItemManager.php[36m:[m[1;31mnamespace main;[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31mrequire_once('inc/class/Product/ItemManager.php');[m
ItemManager.php[36m:[m[1;31mrequire_once('inc/class/Product/ItemEditor.php');[m
ItemManager.php[36m:[m[1;31mrequire_once('inc/class/Product/Item.php');[m
ItemManager.php[36m:[m[1;31mrequire_once(__DIR__ .'/../db/conn_staff.php');[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31muse \Orders\MonthlyRecord;[m
ItemManager.php[36m:[m[1;31muse \Product\ItemEditor;[m
ItemManager.php[36m:[m[1;31muse \Product\ItemManager;[m
ItemManager.php[36m:[m[1;31muse \Product\Item;[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31m$itemEditor = '';[m
ItemManager.php[36m:[m[1;31m$error = '';[m
ItemManager.php[36m:[m[1;31mtry{[m
ItemManager.php[36m:[m[1;31m    try{[m
ItemManager.php[36m:[m[1;31m        if(isset($_GET['itemCode'])){[m
ItemManager.php[36m:[m[1;31m            $ItemM = new ItemManager($con);[m
ItemManager.php[36m:[m[1;31m            $ItemEditor = new ItemEditor();[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31m            $ItemEditor->setItems([m
ItemManager.php[36m:[m[1;31m                $ItemM->getItemLikeItemCode($_GET['itemCode']), [m
ItemManager.php[36m:[m[1;31m                0[m
ItemManager.php[36m:[m[1;31m            );[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31m            $itemEditor = $ItemEditor->getTable();[m
ItemManager.php[36m:[m[1;31m        }[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31m        if(isset($_POST['r'])){[m
ItemManager.php[36m:[m[1;31m            $ItemM = new ItemManager($con);[m
ItemManager.php[36m:[m[1;31m            $Items = [];[m
ItemManager.php[36m:[m[1;31m            foreach($_POST['r'] as $itemId => $r){[m
ItemManager.php[36m:[m[1;31m                $Items[] = new Item($itemId, null, $r['description']);[m
ItemManager.php[36m:[m[1;31m            }[m
ItemManager.php[36m:[m[1;31m            $ItemM->update($Items);[m
ItemManager.php[36m:[m[1;31m            header('HTTP/1.1 205');[m
ItemManager.php[36m:[m[1;31m        }[m
ItemManager.php[36m:[m[1;31m    }finally{[m
ItemManager.php[36m:[m[1;31m        $con->close();[m
ItemManager.php[36m:[m[1;31m    }[m
ItemManager.php[36m:[m[1;31m}catch(\Exception $e){[m
ItemManager.php[36m:[m[1;31m    $error = $e->getMessage();[m
ItemManager.php[36m:[m[1;31m}[m
ItemManager.php[36m:[m[1;31m[m
ItemManager.php[36m:[m[1;31mrequire('view/ItemManager.html');[m
ItemManager.php[36m:[m[1;31m?>[m
LzdFeeChecker.php[36m:[m[1;31m<?php[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31mnamespace main;[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/Orders/Factory/Record.php';[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/Orders/PaymentCharges/PlatformCharges.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/Lazada/Manager/Fee_StatementsManager.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/Lazada/Manager/OrdersManager.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/Lazada/Factory/LzdFeeStatementFactory.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/Lazada/FeeStatement.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/HTML/TableDisplayer.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/IO/FileInputStream.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once 'inc/class/IO/CSVInputStream.php';[m
LzdFeeChecker.php[36m:[m[1;31mrequire_once __DIR__ . '/../db/conn_staff.php';[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31muse \Lazada\Manager\Fee_StatementsManager;[m
LzdFeeChecker.php[36m:[m[1;31muse \Lazada\Manager\OrdersManager;[m
LzdFeeChecker.php[36m:[m[1;31muse \Lazada\Factory\LzdFeeStatementFactory;[m
LzdFeeChecker.php[36m:[m[1;31muse \HTML\TableDisplayer;[m
LzdFeeChecker.php[36m:[m[1;31muse \IO\CSVInputStream;[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m$errormsg = '';[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m$tbl = new TableDisplayer();[m
LzdFeeChecker.php[36m:[m[1;31mtry {[m
LzdFeeChecker.php[36m:[m[1;31m    if (isset($_POST['btnSubmit']['uploadLzdFeeStmt']) && isset($_FILES['file_LzdFeeStmt']) && $_FILES['file_LzdFeeStmt']['error'] === 0) {[m
LzdFeeChecker.php[36m:[m[1;31m        $file = new CSVInputStream($_FILES['file_LzdFeeStmt']['tmp_name']);[m
LzdFeeChecker.php[36m:[m[1;31m        $list = $file->readLines();[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        \array_splice($list, 0, 1);[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $records = LzdFeeStatementFactory::getFeeStatementList($list);[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $con->autoCommit(false);[m
LzdFeeChecker.php[36m:[m[1;31m        Fee_StatementsManager::insertRecords($con, $records, $_SERVER['REMOTE_ADDR']);[m
LzdFeeChecker.php[36m:[m[1;31m        $con->commit();[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        header('Location: fee_statement.php');[m
LzdFeeChecker.php[36m:[m[1;31m    }[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m    if (isset($_POST['btnSubmit']['checkLzdFeeStmt'])) {[m
LzdFeeChecker.php[36m:[m[1;31m        $paymentAmounts = Fee_StatementsManager::getOrderNumPaymentAmount($con, $_SERVER['REMOTE_ADDR']);[m
LzdFeeChecker.php[36m:[m[1;31m        $shippingFeeByCusts = Fee_StatementsManager::getOrderNumShippingFeeByCust($con, $_SERVER['REMOTE_ADDR']);[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $orders = OrdersManager::getForLzdFeeStmtByOrderNums($con, \array_keys($paymentAmounts));[m
LzdFeeChecker.php[36m:[m[1;31m        foreach ($orders as $i => $r) {[m
LzdFeeChecker.php[36m:[m[1;31m            $orders[$i]['grandTotal'] = $r['sellingPrice'] + $r['shippingFeeByCust'] - $r['voucher'] - $r['platformChargesAmount'];[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m            if (\array_key_exists($r['orderNum'], $paymentAmounts) === true) {[m
LzdFeeChecker.php[36m:[m[1;31m                $orders[$i]['stmtPaymentAmount'] = $paymentAmounts[$r['orderNum']];[m
LzdFeeChecker.php[36m:[m[1;31m            }[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m            if (\array_key_exists($r['orderNum'], $shippingFeeByCusts) === true) {[m
LzdFeeChecker.php[36m:[m[1;31m                $orders[$i]['stmtShippingFeeByCust'] = $shippingFeeByCusts[$r['orderNum']];[m
LzdFeeChecker.php[36m:[m[1;31m            }[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m            $orders[$i]['grantTotalDiff'] = $orders[$i]['grandTotal'] - $orders[$i]['stmtPaymentAmount'] - $orders[$i]['stmtShippingFeeByCust'];[m
LzdFeeChecker.php[36m:[m[1;31m        }[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $header = [];[m
LzdFeeChecker.php[36m:[m[1;31m        $header['billno'] = 'Bill no';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['orderNum'] = 'Order Number';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['sellingPrice'] = 'Selling Price';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['shippingFeeByCust'] = 'Courier By Customer';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['voucher'] = 'Voucher';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['platformChargesAmount'] = 'Platform Charges';[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $header['grandTotal'] = 'Grand Total';[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $header['stmtPaymentAmount'] = 'Payment Amount';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['stmtShippingFeeByCust'] = 'Shipping Fee';[m
LzdFeeChecker.php[36m:[m[1;31m        $header['grantTotalDiff'] = 'Grand Total Difference';[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $tbl->setHead($header);[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $tbl->setBody($orders);[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31m        $tbl->setBody($orders);[m
LzdFeeChecker.php[36m:[m[1;31m    }[m
LzdFeeChecker.php[36m:[m[1;31m} catch (\Exception $e) {[m
LzdFeeChecker.php[36m:[m[1;31m    $con->rollback();[m
LzdFeeChecker.php[36m:[m[1;31m    $errormsg = $e->getMessage();[m
LzdFeeChecker.php[36m:[m[1;31m} finally {[m
LzdFeeChecker.php[36m:[m[1;31m    $con->close();[m
LzdFeeChecker.php[36m:[m[1;31m}[m
LzdFeeChecker.php[36m:[m[1;31m[m
LzdFeeChecker.php[36m:[m[1;31mrequire 'view/LzdFeeChecker.html';[m
LzdItemManager.php[36m:[m[1;31m<?php [m
LzdItemManager.php[36m:[m[1;31mnamespace main;[m
LzdItemManager.php[36m:[m[1;31m[m
LzdItemManager.php[36m:[m[1;31mrequire_once('inc/class/Lazada/Item.php');[m
LzdItemManager.php[36m:[m[1;31mrequire_once('inc/class/Lazada/Manager/ItemManager.php');[m
LzdItemManager.php[36m:[m[1;31mrequire_once('inc/class/Lazada/Factory/LzdItemFactory.php');[m
LzdItemManager.php[36m:[m[1;31mrequire_once(__DIR__ .'/../db/conn_staff.php');[m
LzdItemManager.php[36m:[m[1;31m[m
LzdItemManager.php[36m:[m[1;31muse Lazada\Item;[m
LzdItemManager.php[36m:[m[1;31muse Lazada\Factory\LzdItemFactory;[m
LzdItemManager.php[36m:[m[1;31muse Lazada\Manager\ItemManager;[m
LzdItemManager.php[36m:[m[1;31m[m
LzdItemManager.php[36m:[m[1;31m$msg = '';[m
LzdItemManager.php[36m:[m[1;31m$errorMsg = '';[m
LzdItemManager.php[36m:[m[1;31mtry{[m
LzdItemManager.php[36m:[m[1;31m    try{[m
LzdItemManager.php[36m:[m[1;31m        if(isset($_FILES['LzdProducts'])){[m
LzdItemManager.php[36m:[m[1;31m            if($_FILES['LzdProducts']['error'] !== 0){[m
LzdItemManager.php[36m:[m[1;31m                throw new Exception("File has error.");[m
LzdItemManager.php[36m:[m[1;31m            }[m
LzdItemManager.php[36m:[m[1;31m            $file = $_FILES['LzdProducts']['tmp_name'];[m
LzdItemManager.php[36m:[m[1;31m            $Fac = new LzdItemFactory($file);[m
LzdItemManager.php[36m:[m[1;31m            $list = $Fac->generateRecords();[m
LzdItemManager.php[36m:[m[1;31m[m
LzdItemManager.php[36m:[m[1;31m            $M = new ItemManager($con);[m
LzdItemManager.php[36m:[m[1;31m            $existingLzdSku_temp = $M->selectAll('lzd_sku');[m
LzdItemManager.php[36m:[m[1;31m            $existingLzdSku = [];[m
LzdItemManager.php[36m:[m[1;31m            foreach($existingLzdSku_temp as $r){[m
LzdItemManager.php[36m:[m[1;31m                $existingLzdSku[$r['lzd_sku']] = null;[m
LzdItemManager.php[36m:[m[1;31m            }[m
LzdItemManager.php[36m:[m[1;31m            unset($existingLzdSku_temp);[m
LzdItemManager.php[36m:[m[1;31m[m
LzdItemManager.php[36m:[m[1;31m            //split[m
LzdItemManager.php[36m:[m[1;31m            $listToUpdate = [];[m
LzdItemManager.php[36m:[m[1;31m            $listToInsert = [];[m
LzdItemManager.php[36m:[m[1;31m            foreach($list as $Item){[m
LzdItemManager.php[36m:[m[1;31m                if(\array_key_exists($Item->lzdSku, $existingLzdSku)){[m
LzdItemManager.php[36m:[m[1;31m                    $listToUpdate[] = $Item;[m
LzdItemManager.php[36m:[m[1;31m                } else {[m
LzdItemManager.php[36m:[m[1;31m                    $listToInsert[] = $Item;[m
LzdItemManager.php[36m:[m[1;31m                }[m
LzdItemManager.php[36m:[m[1;31m            }[m
LzdItemManager.php[36m:[m[1;31m        [m
LzdItemManager.php[36m:[m[1;31m            //update[m
LzdItemManager.php[36m:[m[1;31m            $con->begin_transaction();[m
LzdItemManager.php[36m:[m[1;31m            try{[m
LzdItemManager.php[36m:[m[1;31m                $M->updateByLzdSku($listToUpdate);[m
LzdItemManager.php[36m:[m[1;31m                $M->insert($listToInsert);[m
LzdItemManager.php[36m:[m[1;31m                $con->commit();[m
LzdItemManager.php[36m:[m[1;31m                $msg = 'Product info updated.';[m
LzdItemManager.php[36m:[m[1;31m            }catch(\Exception $e){[m
LzdItemManager.php[36m:[m[1;31m                $con->rollback();[m
LzdItemManager.php[36m:[m[1;31m                throw $e;[m
LzdItemManager.php[36m:[m[1;31m            }[m
LzdItemManager.php[36m:[m[1;31m        }[m
LzdItemManager.php[36m:[m[1;31m    }finally{[m
LzdItemManager.php[36m:[m[1;31m        $con->close();[m
LzdItemManager.php[36m:[m[1;31m    }[m
LzdItemManager.php[36m:[m[1;31m}catch(\Exception $e){[m
LzdItemManager.php[36m:[m[1;31m    $errorMsg = \htmlspecialchars($e->getMessage(), ENT_QUOTES);[m
LzdItemManager.php[36m:[m[1;31m}[m
LzdItemManager.php[36m:[m[1;31mrequire('view/LzdItemManager.html');[m
LzdItemManager.php[36m:[m[1;31m?>[m
