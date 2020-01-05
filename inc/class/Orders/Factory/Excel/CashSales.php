<?php
namespace Orders\Factory\Excel;

use Exception;
use Orders\PaymentCharges\PaymentCharges;

//transform Monthly Record to Cash Sales entry list
final class CashSales
{
    public static function transformToCashSales(\mysqli $con, string $paymentChargesName, array $list):array
    {
        if (count($list) === 0) {
            return $list;
        }
        $list = self::fillBlank($list, 'TRACKING NUMBER', 'DATE', 'SQL NO', 'ORDER NUMBER', 'SELLER SKU');

        $list = self::setItemUOM(self::selectUomByItemCodes($con, array_column($list, 'SELLER SKU')), $list);

        $cashSales = self::groupByKey('ORDER NUMBER', $list);
        $cashSales = self::sumBySameItemEntries($cashSales);
        $cashSales = self::addShippingFeeEntries($cashSales);

        return self::transformToSqlImportEntries($cashSales, self::getPaymentCharges($paymentChargesName));
    }
//

    /**
     * fill blank cell according to last non blank cell on top value
     */
    private static function fillBlank(array $list, string ...$key):array
    {
        $lastSeenValue = array_fill_keys($key, '');
 
        foreach ($list as $i => $r) {
            foreach ($lastSeenValue as $key => $c) {
                if ($r[$key] === '') {
                    $list[$i][$key] = $c;
                } else {
                    $lastSeenValue[$key] = $r[$key];
                }
            }
        }

        return $list;
    }


    private static function selectUomByItemCodes(\mysqli $con, array $list):array
    {
        $count = count($list);
        $placeHolder = \implode(',', array_fill(0, $count, '?'));
        $dataType =  \implode('', array_fill(0, $count, 's'));
        $stmt = $con->prepare("SELECT item_code, uom FROM stock_items WHERE item_code IN({$placeHolder})");
        $stmt->bind_param($dataType, ...$list);
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

    private static function setItemUOM(array $itemUomMap, array $list):array
    {
        foreach ($list as $i => $r) {
            if (array_key_exists($r['SELLER SKU'], $itemUomMap) === true) {
                $list[$i]['uom'] = $itemUomMap[$r['SELLER SKU']];
            } else {
                $list[$i]['uom'] = null;
            }
        }
        return $list;
    }

    private static function groupByKey($key, array $list):array
    {
        $cashSales = [];
        foreach ($list as $r) {
            if (array_key_exists($r[$key], $cashSales) === false) {
                $cashSales[$r[$key]] = [];
            }

            $cashSales[$r[$key]][] = $r;
        }

        return $cashSales;
    }

    private static function sumBySameItemEntries(array $cashSales):array
    {
        $cashSalesWithSumByItemCode = [];
        foreach ($cashSales as $orderNum => $list) {
            $cashSalesWithSumByItemCode[$orderNum] = self::countByKey(
                'SELLER SKU',
                'count',
                $list,
                'COURIER PAY BY CUSTOMER',
                'SELLER VOUCHER',
                'PLATFORM FEE CHARGES'
            );
        }

        return $cashSalesWithSumByItemCode;
    }

    /**
     * count array $r by and group by $r[key]
     * inaddition sum the specified $r[keysToSum] of each $r
     * In SQL lang example: SELECT COUNT(key) AS 'count', SUM(subTotal) AS 'subTotal' FROM list
     */
    private static function countByKey($key, string $countAsKey, array $list, string ...$keysToSum):array
    {
        $occuredValues = []; //key => index of first occured $r in groupedList
        $groupedList = [];
        $i=0;
        foreach ($list as $r) {
            if (array_key_exists($r[$key], $occuredValues) === false) {
                $r[$countAsKey] = 1;
                foreach ($keysToSum as $keyToSum) {
                    if (array_key_exists($keyToSum, $r) === false) {
                        throw new \InvalidArgumentException("undifined index key: {$keyToSum}");
                    }
    
                    $r[$keyToSum] = doubleval($r[$keyToSum]);
                }
    
                $groupedList[$i] = $r;
    
                $occuredValues[$r[$key]] = $i++;
                continue;
            }
            $groupedList[$occuredValues[$r[$key]]][$countAsKey] += 1;
            foreach ($keysToSum as $keyToSum) {
                $groupedList[$occuredValues[$r[$key]]][$keyToSum] += doubleval($r[$keyToSum]);
            }
        }
        return $groupedList;
    }

    private static function addShippingFeeEntries(array $cashSales):array
    {
        foreach ($cashSales as $orderNum => $list) {
            foreach ($list as $r) {
                if ($r['COURIER PAY BY CUSTOMER'] === 0.00
                || $r['COURIER PAY BY CUSTOMER'] === ''
                || $r['COURIER PAY BY CUSTOMER'] === null) {
                    continue;
                }

                //add shipping entry here
                //with some fields same as normal record
                $cashSales[$orderNum][] = [
                'DATE' => $r['DATE'],
                'SQL NO' => $r['SQL NO'],
                'ORDER NUMBER' => $r['ORDER NUMBER'],
                'SELLER SKU' => 'SHIPPING',
                'DESCRIPTION' => 'SHIPPING FEE',
                'count' => 1,
                'uom' => 'UNIT',
                'SELLING PRICE' => doubleval($r['COURIER PAY BY CUSTOMER']),
                'SELLER VOUCHER' => 0.00,
                'PLATFORM FEE CHARGES' => 0.00
            ];
            }
        }
        return $cashSales;
    }

    private static function getPaymentCharges($paymentChargesName):PaymentCharges
    {
        switch ($paymentChargesName) {
            case 'Lazada':
                return new \Orders\PaymentCharges\Lazada(0.00);
            break;
            case 'Shopee':
                return new \Orders\PaymentCharges\Shopee(0.00);
                break;
            case 'Netpay':
            case 'Lelong':
                return new \Orders\PaymentCharges\Netpay(0.00);
                break;
            case 'Cash':
            case 'Cash Bil':
                return new \Orders\PaymentCharges\Cash(0.00);
                break;
            default:
                throw new \InvalidArgumentException("no PaymentCharges class for: {$paymentChargesName}");
            }
    }
    private static function transformToSqlImportEntries(array $cashSales, PaymentCharges $PaymentCharges):array
    {
        $entries = [];
        foreach ($cashSales as $list) {
            $i = 1;
            $tempEntries = []; //one batch, one cashsale contains list of entries
            foreach ($list as $r) {
                $tempEntries[] = self::createSqlImportEntry($i++, $PaymentCharges, $r);
            }

            //sum the amount and bankcharge for P_AMOUNT and P_BANKCHARGE respectively
            $totalSellingPrice = 0.00;
            $totalPlatformFeeCharges = 0.00;
            foreach ($tempEntries as &$entry) {
                $totalSellingPrice += $entry['Amount'];
                $totalPlatformFeeCharges += $entry['P_BANKCHARGE'];
                
                //clear these cell, since it is sum to the top cell there
                $entry['P_AMOUNT'] = null;
                $entry['P_BANKCHARGE'] = null;

                //add to entries, by ref
                $entries[] =& $entry;
            }
            //set the total amount and bankcharge
            //entries ref to $temp_list so is affected, just set via $temp_list ref
            $tempEntries[0]['P_AMOUNT'] = $totalSellingPrice;
            $tempEntries[0]['P_BANKCHARGE'] = $totalPlatformFeeCharges;
        }

        return $entries;
    }

    private static function createSqlImportEntry(int $seqNumber, PaymentCharges $PaymentCharges, array $r):array
    {
        $sellingPrice = doubleval($r['SELLING PRICE']);
        $entry = [];

        $Date = date_create($r['DATE']);
        if ($Date === false) {
            throw new Exception('invalid date: ' . $r['DATE']);
        }
        $entry['DocDate'] = date_format($Date, 'm/d/Y');
        $entry['DocNo(20)'] = $r['SQL NO'] === '' ? '<<NEW>>' : $r['SQL NO'];
        $entry['Code(10)'] = $PaymentCharges->getCustId(); //
        $entry['CompanyName(100)'] = $PaymentCharges->getCompanyName(); //
        $entry['Agent(10)'] = '----';
        $entry['TERMS(10)'] = 'C.O.D.';
        $entry['Description_HDR(200)'] = 'Cash Sales';
        $entry['SEQ'] = $seqNumber; // sequence
        $entry['ItemCode(30)'] = $r['SELLER SKU'];
        $entry['Description_DTL(200)'] = $r['DESCRIPTION'];
        $entry['Qty'] = $r['count'];
        $entry['UOM'] = $r['uom'];
        $entry['UnitPrice'] = $sellingPrice;
        $entry['DISC(20)'] = doubleval($r['SELLER VOUCHER'] ?? 0.00); // seller voucher
        $entry['Tax(10)'] = '';
        $entry['TaxInclusive'] = 0.00;
        $entry['TaxAmt'] = 0.00;
        $entry['Amount'] =  ($sellingPrice * $r['count']) - doubleval($r['SELLER VOUCHER']);
        $entry['P_AMOUNT'] = null; //sum of amount of an ordernum, later after only sum all the $entry['Amount']
        $entry['P_PAYMENTMETHOD'] = $PaymentCharges->getPaymentInto(); //
        $entry['P_BANKCHARGE'] = doubleval($r['PLATFORM FEE CHARGES']); //
        $entry['DOCREF1'] = $r['ORDER NUMBER'];

        return $entry;
    }
}
// class CashSale
// {
//     private $date = '';
//     private $billNo = '';
//     private $orderNum = '';
//     private $PaymentCharges = null;
//     private $Entry = [];
// }

// class CashSaleEntry
// {
//     private $date = '';
//     private $PaymentCharges = null;
//     private $agent = '----';
//     private $term = 'C.O.D.';
//     private $descriptionHDR = 'Cash Sales';
//     private $sequence = 1;
//     private $Item = null;
//     private $quantity = 1;
//     private $voucher = 0.00;
//     private $tax = 0.00;
//     private $taxInclusive = 0.00;
//     private $taxAmount = 0.00;
// }
