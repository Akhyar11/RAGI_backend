<?php

namespace App\Services\Spmb;

/** Allocate component and general discounts exactly once, using integer cents. */
class SpmbBillAllocator
{
    public function split(array $details, array $discounts): array
    {
        $details = array_values($details);
        $remaining = array_map(fn (array $item): int => max(0, (int) round((float) $item['nominal'] * 100)), $details);
        $allocations = array_fill(0, count($details), []);
        foreach ($discounts as $discount) {
            $eligible = array_keys(array_filter($remaining, fn (int $value, int $index): bool => $value > 0
                && (empty($discount['komponen_biaya_id']) || ($details[$index]['komponen_biaya_id'] ?? null) == $discount['komponen_biaya_id']), ARRAY_FILTER_USE_BOTH));
            $total = array_sum(array_intersect_key($remaining, array_flip($eligible)));
            $amount = min($total, max(0, (int) round((float) $discount['nominal_potongan'] * 100)));
            if ($total === 0 || $amount === 0) {
                continue;
            }
            $shares = [];
            $remainders = [];
            foreach ($eligible as $index) {
                [$shares[$index], $remainders[$index]] = $this->portion($amount, $remaining[$index], $total);
            }
            arsort($remainders, SORT_NUMERIC);
            $left = $amount - array_sum($shares);
            foreach (array_keys($remainders) as $index) {
                if ($left-- > 0) {
                    $shares[$index]++;
                }
            }
            foreach ($shares as $index => $share) {
                $remaining[$index] -= $share;
                if ($share > 0) {
                    $allocations[$index][] = array_merge($discount, ['nominal_potongan' => $share / 100]);
                }
            }
        }
        $groups = [];
        foreach ($details as $index => $detail) {
            $key = $detail['payment_gateway_config_id'] ?? 'legacy';
            $groups[$key] ??= ['payment_gateway_config_id' => $detail['payment_gateway_config_id'] ?? null, 'details' => [], 'potongan' => []];
            $groups[$key]['details'][] = $detail;
            array_push($groups[$key]['potongan'], ...$allocations[$index]);
        }

        return array_values($groups);
    }

    /** Integer multiplication/division without overflowing a 64-bit cent balance. */
    private function portion(int $amount, int $weight, int $total): array
    {
        $quotient = 0;
        $remainder = 0;
        $partQuotient = intdiv($weight, $total);
        $partRemainder = $weight % $total;
        while ($amount > 0) {
            if ($amount % 2 === 1) {
                $quotient += $partQuotient;
                $remainder += $partRemainder;
                if ($remainder >= $total) {
                    $quotient++;
                    $remainder -= $total;
                }
            }
            $amount = intdiv($amount, 2);
            $partQuotient *= 2;
            $partRemainder *= 2;
            if ($partRemainder >= $total) {
                $partQuotient++;
                $partRemainder -= $total;
            }
        }

        return [$quotient, $remainder];
    }
}
