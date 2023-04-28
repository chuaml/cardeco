<?php
namespace main;








use Exception;
use Orders\Factory as Factory;
use Orders\MonthlyRecord;
use Orders\RecordEditor;
use Orders\Lazada\AutoFilling;
use Lazada\Manager\ItemManager;
use Orders\PaymentCharges\PaymentCharges;
use Orders\PaymentCharges\PlatformCharges;

/*


use Orders\RecordInserter;
use Orders\Factory\Lazada;

function insertRecord(\mysqli $con, string $fileName):void{
    $file = 'data/' .$fileName;
    $Lzd = new Lazada($file);
    $list = $Lzd->generateRecords();

    $platform = 'Lazada';
    $Inserter = new RecordInserter($platform);
    $Inserter->insertLog($con, $file, $fileName);
    $Inserter->insert($con, $list);
    $Inserter->update($con, $list);
}

function deleteAllRecords(\mysqli $con):void{
    $tbl = [
        'orders_insert_log',
        'orders_temp',
        'orders'
    ];
    foreach($tbl as $tblName){
        $stmt = $con->query(
            "DELETE FROM `$tblName`"
        );
        if(!$stmt){
            throw new \Exception($con->error);
        }
    }
}

 // deleteAllRecords($con);
    // insertRecord($con,'ready2ship.csv');
    // insertRecord($con,'ready2ship3.csv');
    // insertRecord($con,'ready2ship5.csv');
*/

function setShippingFeeByWeightToLzd(\mysqli $con, array &$MRecords):void
{
    $list = AutoFilling::getLzdRecords($MRecords);
    AutoFilling::setShippingWeightByLzdProduct($con, $list);
    AutoFilling::setShippingFeeByWeight($list);
}

function groupSameItemTogetherInOrderNum(array $MRecords):array
{
    $list = [];
    foreach ($MRecords as $MR) {
        if (array_key_exists($MR->orderNum, $list) === false) {
            $list[$MR->orderNum] = [];
        }
        $item = $MR->getItem();
        if ($item === null) {
            throw new \Exception('no item code for order number: ' . $MR->orderNum);
        }

        if (array_key_exists($item->code, $list[$MR->orderNum]) === false) {
            $list[$MR->orderNum][$item->code] = ['monthlyRecord'=>$MR, 'count'=> 1];
            continue;
        }
        $list[$MR->orderNum][$item->code]['count'] += 1;
    }

    return $list;
}

function getAllItemCode(array $MRecords):array
{
    $itemCodeSet = [];
    foreach ($MRecords as $MR) {
        $item = $MR->getItem();
        if ($item === null) {
            throw new \Exception('no item code for order number: ' . $MR->orderNum);
        }
        $itemCodeSet[$item->code] = $item->code;
    }

    return $itemCodeSet;
}

function getItemUOM(\mysqli $con, array $list)
{
    $count = count($list);
    $placeHolder = \implode(',', array_fill(0, $count, '?'));
    $dataType =  \implode('', array_fill(0, $count, 's'));
    $stmt = $con->prepare("SELECT item_code, uom FROM stock_items WHERE item_code IN({$placeHolder})");
    $stmt->bind_param($dataType, ...array_keys($list));
    if ($stmt->execute() === false) {
        throw new \Exception($stmt->error);
    }

    $itemUom = [];
    $rs = $stmt->get_result();
    while (($r = $rs->fetch_assoc()) !== null) {
        $itemUom[$r['item_code']] = $r['uom'];
    }
    $stmt->free_result();
    $stmt->close();
    
    return $itemUom;
}

function createSQLImport(\mysqli $con, array $MRecords)
{
    $groupedList = groupSameItemTogetherInOrderNum($MRecords);
    $itemUOM = getItemUOM($con, getAllItemCode($MRecords));
    $outputList = [];
    foreach ($groupedList as $orderNum => $record) {
        foreach ($record as $itemCode => $MRcount) {
            $r = [];
            $MR = $MRcount['monthlyRecord'];
            //$MR = new MonthlyRecord();
            $item = $MR->getItem();
            if ($item === null) {
                throw new \Exception("no item for order number: {$orderNum}, itemCode: {$itemCode}");
            }

            if (array_key_exists($itemCode, $itemUOM) === false) {
                $itemUOM[$itemCode] = null;
                //throw new \Exception("no uom for item code: {$itemCode}");
            }
            
            $PaymentCharges = getEitherOnePaymentCharges($MR);
            $billno = $MR->billno === '' ? '<<NEW>>' : $MR->billno;
            $docDate = date_format(date_create($MR->date), 'm/d/Y');
            $r['DocDate'] =$docDate;
            $r['DocNo(20)'] = $billno;
            $r['Code(10)'] = $PaymentCharges->getCustId(); //
            $r['CompanyName(100)'] = $PaymentCharges->getCompanyName(); //
            $r['Agent(10)'] = '----';
            $r['TERMS(10)'] = 'C.O.D.';
            $r['Description_HDR(200)'] = 'Cash Sales';
            $r['SEQ'] = 0; // sequence
            $r['ItemCode(30)'] = $item->code;
            $r['Description_DTL(200)'] = $item->description;
            $r['Qty'] = $MRcount['count'];
            $r['UOM'] = $itemUOM[$itemCode];
            $r['UnitPrice'] = $MR->sellingPrice;
            $r['DISC(20)'] = $MR->voucher; // seller voucher
            $r['Tax(10)'] = '';
            $r['TaxInclusive'] = 0.00;
            $r['TaxAmt'] = 0.00;
            $r['Amount'] = $MR->sellingPrice * $MRcount['count'];
            $r['P_AMOUNT'] = null; //sum of amount of an ordernum
            $r['P_PAYMENTMETHOD'] = $PaymentCharges->getPaymentInto(); //
            $r['P_BANKCHARGE'] = $MR->getSumDirectChargesAmount(); //
            $r['DOCREF1'] = $MR->orderNum;

            $outputList[] = $r;

            //add shipping fee as a record
            if ($MR->shippingFeeByCust > 0.00) {
                $r['DocDate'] = $docDate;
                $r['DocNo(20)'] = $billno;
                $r['Code(10)'] = $PaymentCharges->getCustId();
                $r['CompanyName(100)'] = $PaymentCharges->getCompanyName(); //
                $r['Agent(10)'] = '----';
                $r['TERMS(10)'] = 'C.O.D.';
                $r['Description_HDR(200)'] = 'Cash Sales';
                $r['SEQ'] = 0; // sequence
                $r['ItemCode(30)'] = 'SHIPPING';
                $r['Description_DTL(200)'] = 'SHIPPING FEE';
                $r['Qty'] = 1;
                $r['UOM'] = 'UNIT';
                $r['UnitPrice'] = $MR->shippingFeeByCust;
                $r['DISC(20)'] = 0.00;
                $r['Tax(10)'] = '';
                $r['TaxInclusive'] = 0.00;
                $r['TaxAmt'] = 0.00;
                $r['Amount'] = $MR->shippingFeeByCust;
                $r['P_AMOUNT'] = null; //sum of amount of an ordernum
                $r['P_PAYMENTMETHOD'] = $PaymentCharges->getPaymentInto(); //
                $r['P_BANKCHARGE'] = 0.00; //
                $r['DOCREF1'] = $MR->orderNum;

                $outputList[] = $r;
            }
        }
    }

    //go through again to sum each group of order amount and write correct seq
    $anOrderIndex = 0;
    $seq = 1;
    $totalAmountOfOrder = $outputList[$anOrderIndex]['Amount'] * $outputList[$anOrderIndex]['Qty'];

    $outputList[$anOrderIndex]['SEQ'] = $seq;
    $outputList[$anOrderIndex]['P_AMOUNT'] = $totalAmountOfOrder;

    $count = count($outputList);
    for ($i=1;$i<$count;++$i) {
        if ($outputList[$i]['DOCREF1'] !== $outputList[$anOrderIndex]['DOCREF1']) {
            $outputList[$anOrderIndex]['P_AMOUNT'] = $totalAmountOfOrder;
            $anOrderIndex = $i;
            $totalAmountOfOrder = 0.00;
            $seq = 1;
        }
        $outputList[$i]['SEQ'] = $seq++;
        $totalAmountOfOrder += $outputList[$i]['Amount'] * $outputList[$i]['Qty'];
    }
    //last order
    $outputList[$anOrderIndex]['P_AMOUNT'] = $totalAmountOfOrder;

    return $outputList;
}

function getEitherOnePaymentCharges(MonthlyRecord $MR):PaymentCharges
{
    if ($MR->DirectCharges !== null) {
        if (array_key_exists('BankIn', $MR->DirectCharges) === true
        && $MR->DirectCharges['BankIn']->getAmount() > 0.00) {
            return $MR->DirectCharges['BankIn'];
        }
        if (array_key_exists('Cash', $MR->DirectCharges) === true &&
        $MR->DirectCharges['Cash']->getAmount() > 0.00) {
            return $MR->DirectCharges['Cash'];
        }
    }
    if ($MR->PlatformCharges !== null) {
        return $MR->PlatformCharges;
    }
    throw new \Exception('No Payment Charges for record orderNum: ' .$MR->orderNum);
}


$pageFloor = '';
$recordEditor = '<table></table>';
try {
    try {
        //update changes
        if (isset($_POST['r']) && !empty($_POST['r'])) {
            require('process/RecordEditor/submit.php');
            $con->close();
            exit(header('HTTP/1.1 205'));  //response to reset form
        }

        //setup RecordEditor;
        $LIMIT_ROWS = 50;
    
        $page = $_GET['floorPage'] ?? 0;
        $searchField = $_GET['searchField'] ?? 'id';
        $searchValue = $_GET['searchValue'] ?? '';
    
        $MF = new Factory\MonthlyRecord($con);
        $MF->setRecordLimit($LIMIT_ROWS);
        $MF->setOffset($page * $LIMIT_ROWS); //$ground 0 = page of latest record list
        $MF->setSearch($searchField, $searchValue);
    
        $Tbl = new RecordEditor();
        $MRecords = null;
        $platformType = isset($_GET['platformCharges']) ? $_GET['platformCharges'] : null;
        $platformType = $platformType === 'ALL' ? null : $platformType;
        
        if (isset($_GET['RecordMonth'])) {
            $MRecords = $MF->getMonthlyRecordsByDate($_GET['RecordMonth'], $platformType);
            if (isset($_GET['exportCSV']['default'])) {
                $csvData = &$MRecords;
                setShippingFeeByWeightToLzd($con, $MRecords);
                require('process/RecordEditor/exportCSV.php');
            } elseif (isset($_GET['exportCSV']['sqlImport'])) {
                //var_dump(createSQLImport($con, $MRecords));
                $csvData = createSQLImport($con, $MRecords);
                require('process/RecordEditor/exportCSV_sqlImport.php');
            }
        } else {
            $MRecords = $MF->generateRecords($platformType);
        }

        setShippingFeeByWeightToLzd($con, $MRecords);

        $Tbl->setMonthlyRecords($MRecords, $MF->getNumPage());
        
        $pageFloor = $Tbl->getFloorPage();
        $recordEditor = $Tbl->getTable();
    } finally {
        $con->close();
    }

    require('view/RecordEditor.html');
} catch (\Exception $e) {
    if ($_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR']) {
        throw $e;
    } else {
        header('HTTP/1.1 500');
    }
}
