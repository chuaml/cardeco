<?php

declare(strict_types=1);

namespace test\Unit\Orders\Lazada;

use Orders\Lazada\AutoFilling;
use Orders\MonthlyRecord;
use Orders\PaymentCharges\Lazada;
use Orders\PaymentCharges\Shopee;
use Orders\Record;
use PHPUnit\Framework\TestCase;
use Product\Item;

/**
 * Characterization tests for Orders\Lazada\AutoFilling (src/Legacy/Orders/Lazada/AutoFilling.php):
 * the Lazada weight -> courier fee table and the per-tracking-number fee assignment.
 *
 * setShippingWeightByLzdProduct() needs the database and is intentionally NOT covered here.
 */
final class AutoFillingTest extends TestCase
{
    public function testConstants_documentedTable(): void
    {
        $this->assertSame([0, 1, 3, 5, 10, 15, 30], AutoFilling::WEIGHT_RANK);
        $this->assertSame([
            'Kuala Lumpur' => [0, 4, 4.5, 4.5, 8.5, 12, 18],
            'default' => [0, 4.8, 4.8, 4.8, 9.5, 13, 20],
        ], AutoFilling::SHIPPINGFEE_WEIGHT);
        $this->assertSame(1 / 6000, AutoFilling::DIMENSION_TO_WEIGHT);
    }

    // ---------------------------------------------------------------- getShippingFeeByWeightAmount

    /** @return array<string, array<int, mixed>> */
    public function provideWeightBands(): array
    {
        // weight (kg), Kuala Lumpur fee, default (any other state) fee
        $bands = [
            [0.0, 0.0, 0.0],
            [0.5, 4.0, 4.8],
            [1.0, 4.0, 4.8],
            [1.01, 4.5, 4.8],
            [3.0, 4.5, 4.8],
            [3.01, 4.5, 4.8],
            [5.0, 4.5, 4.8],
            [5.01, 8.5, 9.5],
            [10.0, 8.5, 9.5],
            [10.01, 12.0, 13.0],
            [15.0, 12.0, 13.0],
            [15.01, 18.0, 20.0],
            [30.0, 18.0, 20.0],
        ];
        $cases = [];
        foreach ($bands as $band) {
            $cases[$band[0] . ' kg Kuala Lumpur'] = [$band[0], 'Kuala Lumpur', $band[1]];
            $cases[$band[0] . ' kg Selangor'] = [$band[0], 'Selangor', $band[2]];
        }

        return $cases;
    }

    /** @dataProvider provideWeightBands */
    public function testGetShippingFeeByWeightAmount_bandBoundaries(float $weight, string $state, float $fee): void
    {
        $this->assertSame($fee, AutoFilling::getShippingFeeByWeightAmount($weight, $state));
    }

    public function testGetShippingFeeByWeightAmount_unknownOrEmptyState_usesDefaultRate(): void
    {
        $this->assertSame(4.8, AutoFilling::getShippingFeeByWeightAmount(0.5, ''));
        $this->assertSame(4.8, AutoFilling::getShippingFeeByWeightAmount(0.5, 'Johor'));
        $this->assertSame(4.8, AutoFilling::getShippingFeeByWeightAmount(0.5, 'default'));
    }

    public function testGetShippingFeeByWeightAmount_stateLookupIsCaseSensitive(): void
    {
        $this->assertSame(4.0, AutoFilling::getShippingFeeByWeightAmount(0.5, 'Kuala Lumpur'));
        $this->assertSame(4.8, AutoFilling::getShippingFeeByWeightAmount(0.5, 'kuala lumpur'));
        $this->assertSame(4.8, AutoFilling::getShippingFeeByWeightAmount(0.5, 'KUALA LUMPUR'));
    }

    public function testGetShippingFeeByWeightAmount_negativeWeight_isFree(): void
    {
        $this->assertSame(0.0, AutoFilling::getShippingFeeByWeightAmount(-1.0, 'Selangor'));
    }

    /** A parcel above the last WEIGHT_RANK entry (30 kg) is charged the top band (used to fall through to RM 0). */
    public function testGetShippingFeeByWeightAmount_over30kg_chargesTopBand(): void
    {
        $this->assertSame(20.0, AutoFilling::getShippingFeeByWeightAmount(30.01, 'Selangor'));
        $this->assertSame(18.0, AutoFilling::getShippingFeeByWeightAmount(45.0, 'Kuala Lumpur'));
    }

    // ---------------------------------------------------------------- getDimensionToWeight

    public function testGetDimensionToWeight_volumetricDivisor6000(): void
    {
        $this->assertSame(1.0, AutoFilling::getDimensionToWeight(10.0, 20.0, 30.0));
        $this->assertSame(20.0, AutoFilling::getDimensionToWeight(60.0, 50.0, 40.0));
        $this->assertSame(0.0, AutoFilling::getDimensionToWeight(0.0, 50.0, 40.0));
    }

    public function testGetLargerWeight_picksMaxOfActualAndVolumetricPerSku(): void
    {
        $list = [
            ['sku' => 'ZZTEST-001', 'weight' => '2.5', 'length' => 10, 'width' => 20, 'height' => 30], // vol 1.0
            ['sku' => 'ZZTEST-002', 'weight' => '0.2', 'length' => 60, 'width' => 50, 'height' => 40], // vol 20.0
        ];

        $this->assertSame(
            [['ZZTEST-001' => 2.5], ['ZZTEST-002' => 20.0]],
            AutoFilling::getLargerWeight($list)
        );
    }

    // ---------------------------------------------------------------- setShippingFeeByWeight

    private function record(string $orderNum, string $trackingNum, float $weight, string $state, float $shippingFee = 0.0): Record
    {
        $r = new Record(null, $orderNum, new Item(null, 'ZZTEST-001', 'Zz Widget'));
        $r->setTrackingNum($trackingNum);
        $r->setShippingWeight($weight);
        $r->setShippingState($state);
        $r->setShippingFee($shippingFee);

        return $r;
    }

    public function testSetShippingFeeByWeight_sameTracking_sumsWeightAndChargesFirstRecordOnly(): void
    {
        $records = [
            $this->record('ZZORDER1', 'ZZTRACK1', 0.6, 'Selangor'),
            $this->record('ZZORDER1', 'ZZTRACK1', 0.6, 'Selangor'),
            $this->record('ZZORDER1', 'ZZTRACK1', 4.0, 'Selangor'),
        ];

        AutoFilling::setShippingFeeByWeight($records);

        // 0.6 + 0.6 + 4.0 = 5.2 kg -> 10 kg band -> RM 9.50 (each alone would have been 4.80)
        $this->assertSame(9.5, $records[0]->shippingFeeByWeight);
        $this->assertSame(0.0, $records[1]->shippingFeeByWeight);
        $this->assertSame(0.0, $records[2]->shippingFeeByWeight);
        // the seller-paid shippingFee itself is untouched
        $this->assertSame(0.0, $records[0]->shippingFee);
    }

    public function testSetShippingFeeByWeight_anyRecordWithShippingFee_skipsWholeTrackingNumber(): void
    {
        $records = [
            $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Selangor'),
            $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Selangor', 6.0),
            $this->record('ZZORDER1', 'ZZTRACK2', 0.5, 'Selangor'),
        ];

        AutoFilling::setShippingFeeByWeight($records);

        $this->assertSame(0.0, $records[0]->shippingFeeByWeight);
        $this->assertSame(0.0, $records[1]->shippingFeeByWeight);
        $this->assertSame(4.8, $records[2]->shippingFeeByWeight);
    }

    public function testSetShippingFeeByWeight_usesShippingStateOfFirstRecordOfTrackingNumber(): void
    {
        $records = [
            $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Kuala Lumpur'),
            $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Selangor'),
        ];

        AutoFilling::setShippingFeeByWeight($records);

        $this->assertSame(4.0, $records[0]->shippingFeeByWeight); // Kuala Lumpur rate, 1.0 kg
        $this->assertSame(0.0, $records[1]->shippingFeeByWeight);
    }

    public function testSetShippingFeeByWeight_oneOrderTwoParcels_eachTrackingNumberCharged(): void
    {
        $records = [
            $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Selangor'),
            $this->record('ZZORDER1', 'ZZTRACK2', 12.0, 'Selangor'),
        ];

        AutoFilling::setShippingFeeByWeight($records);

        $this->assertSame(4.8, $records[0]->shippingFeeByWeight);
        $this->assertSame(13.0, $records[1]->shippingFeeByWeight);
    }

    public function testSetShippingFeeByWeight_sameTrackingAcrossOrders_groupedTogether(): void
    {
        $records = [
            $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Selangor'),
            $this->record('ZZORDER2', 'ZZTRACK1', 0.5, 'Selangor'),
        ];

        AutoFilling::setShippingFeeByWeight($records);

        $this->assertSame(4.8, $records[0]->shippingFeeByWeight);
        $this->assertSame(0.0, $records[1]->shippingFeeByWeight);
    }

    public function testSetShippingFeeByWeight_recomputes_overwritingPreviousValue(): void
    {
        $r = $this->record('ZZORDER1', 'ZZTRACK1', 0.5, 'Selangor');
        $r->setShippingFeeByWeight(99.0);
        $records = [$r];

        AutoFilling::setShippingFeeByWeight($records);

        $this->assertSame(4.8, $r->shippingFeeByWeight);
    }

    public function testSetShippingFeeByWeight_emptyList_noop(): void
    {
        $records = [];
        AutoFilling::setShippingFeeByWeight($records);

        $this->assertSame([], $records);
    }

    // ---------------------------------------------------------------- getLzdRecords

    private function monthly(string $orderNum, string $trackingNum, ?\Orders\PaymentCharges\PlatformCharges $charges): MonthlyRecord
    {
        $m = new MonthlyRecord(null, $orderNum);
        $m->setTrackingNum($trackingNum);
        if ($charges !== null) {
            $m->setPlatformCharges($charges);
        }

        return $m;
    }

    public function testGetLzdRecords_keepsOnlyLazadaRecordsWithOrderAndTracking_preservingKeys(): void
    {
        $records = [
            'a' => $this->monthly('ZZORDER1', 'ZZTRACK1', new Lazada(0.0)),
            'b' => $this->monthly('ZZORDER2', 'ZZTRACK2', new Shopee(0.0)),
            'c' => $this->monthly('ZZORDER3', '', new Lazada(0.0)),
            'd' => $this->monthly('', 'ZZTRACK4', new Lazada(0.0)),
            'e' => $this->monthly('ZZORDER5', 'ZZTRACK5', null),
            'f' => $this->monthly('ZZORDER6', 'ZZTRACK6', new Lazada(1.0)),
        ];

        $lzd = AutoFilling::getLzdRecords($records);

        $this->assertSame(['a', 'f'], array_keys($lzd));
        $this->assertSame($records['a'], $lzd['a']);
        $this->assertSame($records['f'], $lzd['f']);
    }

    public function testGetLzdRecords_emptyList_returnsEmpty(): void
    {
        $records = [];

        $this->assertSame([], AutoFilling::getLzdRecords($records));
    }
}
