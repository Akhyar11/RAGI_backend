<?php

namespace Tests\Unit\Services;

use App\Services\Spmb\SpmbBillAllocator;
use PHPUnit\Framework\TestCase;

class SpmbBillAllocatorTest extends TestCase
{
    public function test_component_discount_stays_with_its_component_and_general_discount_is_split_once(): void
    {
        $groups = (new SpmbBillAllocator)->split([
            ['komponen_biaya_id' => 1, 'payment_gateway_config_id' => 17, 'nominal' => 100000],
            ['komponen_biaya_id' => 2, 'payment_gateway_config_id' => 28, 'nominal' => 200000],
        ], [
            ['komponen_biaya_id' => 2, 'nominal_potongan' => 50000],
            ['nominal_potongan' => 25000],
        ]);
        $this->assertEquals(10000, array_sum(array_column($groups[0]['potongan'], 'nominal_potongan')));
        $this->assertEquals(65000, array_sum(array_column($groups[1]['potongan'], 'nominal_potongan')));
    }

    public function test_rounding_preserves_every_cent_and_combines_same_gateway(): void
    {
        $groups = (new SpmbBillAllocator)->split([
            ['payment_gateway_config_id' => 12, 'nominal' => 0.01],
            ['payment_gateway_config_id' => 23, 'nominal' => 0.01],
            ['payment_gateway_config_id' => 12, 'nominal' => 0.01],
        ], [['nominal_potongan' => 0.02]]);
        $this->assertCount(2, $groups);
        $this->assertCount(2, $groups[0]['details']);
        $this->assertEquals(0.02, array_sum(array_map(fn ($group) => array_sum(array_column($group['potongan'], 'nominal_potongan')), $groups)));
    }

    public function test_stacked_discounts_never_exceed_component_balance(): void
    {
        $groups = (new SpmbBillAllocator)->split([
            ['komponen_biaya_id' => 1, 'nominal' => 1000], ['komponen_biaya_id' => 2, 'nominal' => 2000],
        ], [['komponen_biaya_id' => 1, 'nominal_potongan' => 5000], ['nominal_potongan' => 10000]]);
        $this->assertCount(1, $groups);
        $this->assertEquals(3000, array_sum(array_column($groups[0]['potongan'], 'nominal_potongan')));
    }

    public function test_large_tuition_allocation_does_not_overflow_integer_cents(): void
    {
        $groups = (new SpmbBillAllocator)->split([
            ['payment_gateway_config_id' => 12, 'nominal' => 100000000],
            ['payment_gateway_config_id' => 23, 'nominal' => 200000000],
        ], [['nominal_potongan' => 150000000]]);
        $this->assertEquals(50000000, array_sum(array_column($groups[0]['potongan'], 'nominal_potongan')));
        $this->assertEquals(100000000, array_sum(array_column($groups[1]['potongan'], 'nominal_potongan')));
    }
}
