<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Models\Vehicle;

/**
 * The price of a rental (specification 6.4): the total is the cheapest
 * combination of the rules the agency published, never the naive
 * days × daily rate.
 *
 * An agency that offers 4 500 DA/day and 28 000 DA/week is saying that seven
 * days cost 28 000. A client renting six days must not be charged 27 000 when
 * seven would cost 28 000 — but neither must they be charged 27 000 when the
 * weekly rate would have cost less. Reading the rules literally is what the
 * agency expects; making the client hunt for the combination is not.
 */
class PricingService
{
    /**
     * @return array{
     *     total: int, vehicle: int, driver: int, days: int,
     *     lines: array<int, array{label: string, quantity: int, unit_price: int, total: int}>
     * }
     */
    public function quote(Vehicle $vehicle, int $days, bool $withDriver = false): array
    {
        $rules = $vehicle->relationLoaded('pricingRules')
            ? $vehicle->pricingRules
            : $vehicle->pricingRules()->get();

        $vehiclePrice = $this->cheapestCombination($rules->all(), $days);
        $driverPrice = $withDriver ? $days * (int) $vehicle->driver_price_per_day : 0;

        return [
            'days' => $days,
            'vehicle' => $vehiclePrice['total'],
            'driver' => $driverPrice,
            'total' => $vehiclePrice['total'] + $driverPrice,
            'lines' => array_values(array_merge(
                $vehiclePrice['lines'],
                $driverPrice > 0 ? [[
                    'label' => 'Chauffeur',
                    'quantity' => $days,
                    'unit_price' => (int) $vehicle->driver_price_per_day,
                    'total' => $driverPrice,
                ]] : []
            )),
        ];
    }

    /**
     * Cheapest way to cover `$days` with the available rules, by dynamic
     * programming. A rule may cover more days than remain — that is precisely
     * how a week can come out cheaper than five separate days — so the cost of
     * the remainder is clamped at zero rather than forbidden.
     *
     * @param  array<int, PricingRule>  $rules
     * @return array{total: int, lines: array}
     */
    private function cheapestCombination(array $rules, int $days): array
    {
        $usable = array_values(array_filter($rules, fn (PricingRule $r) => $r->price_dzd > 0));

        if ($days < 1 || $usable === []) {
            return ['total' => 0, 'lines' => []];
        }

        // cost[n] = cheapest price for n days; from[n] = rule that got us there.
        $cost = [0 => 0];
        $from = [];

        for ($n = 1; $n <= $days; $n++) {
            $cost[$n] = PHP_INT_MAX;

            foreach ($usable as $rule) {
                $remaining = max(0, $n - $rule->unitDays());
                $candidate = $cost[$remaining] + $rule->price_dzd;

                if ($candidate < $cost[$n]) {
                    $cost[$n] = $candidate;
                    $from[$n] = $rule;
                }
            }
        }

        return ['total' => $cost[$days], 'lines' => $this->lines($from, $days)];
    }

    /**
     * Walks the chosen rules back into lines the client can read. Identical
     * rules are grouped: "3 × semaine" beats three lines saying the same thing.
     *
     * @param  array<int, PricingRule>  $from
     */
    private function lines(array $from, int $days): array
    {
        $labels = [
            PricingRule::DAILY => 'Journée',
            PricingRule::WEEKLY => 'Semaine',
            PricingRule::MONTHLY => 'Mois',
        ];

        $counts = [];
        $n = $days;

        while ($n > 0 && isset($from[$n])) {
            $rule = $from[$n];
            $key = $rule->duration_type;

            $counts[$key] ??= ['price' => $rule->price_dzd, 'quantity' => 0];
            $counts[$key]['quantity']++;

            $n = max(0, $n - $rule->unitDays());
        }

        // Longest units first: the invoice reads month, then week, then day.
        $order = [PricingRule::MONTHLY => 0, PricingRule::WEEKLY => 1, PricingRule::DAILY => 2];
        uksort($counts, fn ($a, $b) => $order[$a] <=> $order[$b]);

        return array_map(fn ($key, $line) => [
            'label' => $labels[$key] ?? $key,
            'quantity' => $line['quantity'],
            'unit_price' => $line['price'],
            'total' => $line['quantity'] * $line['price'],
        ], array_keys($counts), $counts);
    }
}
