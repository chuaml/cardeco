<?php

namespace App\Service\Shipping;

use Exception;
use mysqli;
use Orders\Lazada\AutoFilling;
use Orders\Record;
use Product\Item;

/**
 * Adds the weight-based shipping fee estimate ('shippingFeeByWeight', 'shippingWeight') to daily order
 * rows and normalises their 'date' through Orders\Record. Previously duplicated as setShippingFeeByWeight()
 * in LazadaOrderProcess, BigSellerOrderProcess and TikTokOrder.
 *
 * Weights come from lzd_products (matched on seller_sku == sku); the fee table lives in AutoFilling.
 */
final class ShippingFeeByWeight
{
    /**
     * @param array<int, array<string, mixed>> $orders rows with orderNum, sku, shippingFee, shippingState, trackingNum, date
     */
    public static function apply(mysqli $con, array &$orders): void
    {
        $records = array_map(function (array $r) {
            $record = new Record(null, $r['orderNum'], new Item(null, $r['sku'], null));
            $record->setShippingFee((float) $r['shippingFee']);
            $record->setShippingState($r['shippingState']);
            $record->setTrackingNum($r['trackingNum']);
            $record->setDate($r['date']);

            return $record;
        }, $orders);

        AutoFilling::setShippingWeightByLzdProduct($con, $records);
        AutoFilling::setShippingFeeByWeight($records);

        foreach ($records as $i => $record) {
            if ($orders[$i]['orderNum'] !== $record->orderNum) {
                throw new Exception("cannot map shippingFeeByWeight to orders. iteration: {$i}");
            }
            $orders[$i]['shippingFeeByWeight'] = $record->shippingFeeByWeight;
            $orders[$i]['shippingWeight'] = $record->shippingWeight;
            $orders[$i]['date'] = $record->date;
        }
    }
}
