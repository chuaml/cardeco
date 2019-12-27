<?php 
namespace Orders\Lazada;

use Orders\PaymentCharges\PlatformCharges;
use Orders\MonthlyRecord;
use Lazada\Manager\ItemManager;

class AutoFilling{

    const DIMENSION_TO_WEIGHT = 1/6000;
    const WEIGHT_RANK = [
        0, 1, 3, 5, 10, 15, 30
    ];
    
    //cost of shippingFee by Weight, RM, KG
    const SHIPPINGFEE_WEIGHT = [
        'Kuala Lumpur' => [
            0, 4, 4.5, 4.5, 8.5, 12, 18
        ],
        'default' => [
            0, 4.8, 4.8, 4.8, 9.5, 13, 20
        ]
    ];

    public static function getLzdRecords(array &$MonthlyRecords):array{
        return array_filter($MonthlyRecords, function(MonthlyRecord $r){
            $PlatformCharges = $r->getPlatformCharges();
            if(strlen($r->orderNum) > 0 && strlen($r->trackingNum) > 0 
            && $PlatformCharges !== null 
            && $PlatformCharges->getPlatform() === PlatformCharges::TYPE_OF_CHARGES['Lazada']){
                return $r;
            }
        });
    }

    public static function getDimensionToWeight(
        float $lenght, float $width, float $height):float{
            return $lenght * $width * $height * self::DIMENSION_TO_WEIGHT;
    }

    public static function getLargerWeight(array &$list):array{
        return array_map(function($r){
            $dimensionWeight = self::getDimensionToWeight(
                $r['length'], $r['width'], $r['height']
            );
            $weight = (double) $r['weight'];
            return [
                $r['sku'] => ($weight > $dimensionWeight ? $weight : $dimensionWeight)
            ];
        }, $list);
    }

    public static function setShippingWeightByLzdProduct(\mysqli $con, array &$LzdRecords):void{
        //get lzd products weight info
        //compare weight to dimensionWeight
        //set to LzdRecords
        $IM = new ItemManager($con);
            $LzdItems_temp = $IM->selectBySellerSku(
                array_map(
                    function(\Orders\Record $r){
                        return $r->getItem()->code;
                    }, $LzdRecords)
                );
            $LzdItems = [];
            foreach($LzdItems_temp as $r){
                $LzdItems[$r['seller_sku']] = $r;
            }
    
            foreach($LzdRecords as $r){
                $sku = $r->getItem()->code;
                if($sku === null){continue;}
                $item = &$LzdItems[$sku];
    
                if($item === null){continue;}
    
                $weight = (double) $item['weight'];
                $weight2 = AutoFilling::getDimensionToWeight(
                    $item['length'], $item['width'], $item['height']
                );
                $r->setShippingWeight($weight > $weight2 ? $weight : $weight2);
            }
    }

    public static function setShippingFeeByWeight(array &$MonthlyRecords):void{
        //groupby orderNum
        //to unquie orderNum
        $orderNums = [];
        foreach($MonthlyRecords as $r){
            if(!array_key_exists($r->orderNum, $orderNums)){
                $orderNums[$r->orderNum] = [];
            }
            $orderNums[$r->orderNum][] = $r;
        }
    
        //groupby trackingNum
        //to unquie trackingNums
        $trackingNums = [];
        foreach($orderNums as $o){
            foreach($o as $r){
                if(!array_key_exists($r->trackingNum, $trackingNums)){
                    $trackingNums[$r->trackingNum] = [];
                }
                $trackingNums[$r->trackingNum][] = $r;
            }
        }
    
        //sum total item weight foreach trackingNum which shippingFee === 0
        //set ShippingFee by total weight to the first/one MonthlyRecord of that trackingNum
        foreach($trackingNums as $t){
            $totalShippingFee = 0.00;
            foreach($t as $r){
                $totalShippingFee += $r->shippingFee;
            }
            if($totalShippingFee > 0.00){continue;}

            $totalWeight = 0.00;
            foreach($t as $r){
                $totalWeight += $r->shippingWeight;
            }
            $MonthlyRecord = &$t[0];
            $MonthlyRecord->setShippingFeeByWeight(
                self::getShippingFeeByWeightAmount($totalWeight, $MonthlyRecord->shippingState)
            );
        }
    }

    public static function getShippingFeeByWeightAmount(float $weight, string $state):float{
        $weightIndex = 0;
        foreach(self::WEIGHT_RANK as $i => $w){
            if($weight <= $w){
                $weightIndex = $i;
                break;
            }
        }
        $shippingFee = self::SHIPPINGFEE_WEIGHT['default'][$weightIndex];
        if(\array_key_exists($state, self::SHIPPINGFEE_WEIGHT)){
            $shippingFee = self::SHIPPINGFEE_WEIGHT[$state][$weightIndex];
        }

        return $shippingFee;
    }

}
