<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public catalogue: what a visitor sees, and in which order.
 *
 * The ordering of specification 4.2 is where the plans earn their price, so it
 * lives here alone rather than being spelled out again in each controller.
 */
class PublicListingService
{
    /** Manual sorts. They deliberately ignore the plan priorities (§4.2). */
    public const SORTS = ['price_asc', 'price_desc', 'recent'];

    /**
     * Base query for every public page. `visible()` already restricts to
     * published listings of approved agencies; what is added here are the plan
     * columns the ordering and the badges need.
     */
    public function query(): Builder
    {
        return Vehicle::visible()
            ->with(['agency:id,commercial_name,slug,average_rating,reviews_count',
                'coverPhoto', 'pickupWilaya:id,name_fr,slug', 'pickupCommune:id,name_fr,slug'])
            ->addSelect('vehicles.*')
            ->addSelect(['daily_price' => PricingRule::select('price_dzd')
                ->whereColumn('pricing_rules.vehicle_id', 'vehicles.id')
                ->where('duration_type', PricingRule::DAILY)
                ->limit(1)])
            ->addSelect(['plan_wilaya_priority' => $this->planColumn('has_wilaya_priority')])
            ->addSelect(['plan_commune_priority' => $this->planColumn('has_commune_priority')])
            ->addSelect(['plan_homepage_feature' => $this->planColumn('has_homepage_feature')])
            ->addSelect(['plan_badge' => $this->planColumn('badge_label')])
            ->addSelect(['plan_badge_color' => $this->planColumn('badge_color')]);
    }

    /** @param  array<string, mixed>  $filters */
    public function filter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['wilaya_id'] ?? null, fn ($q, $id) => $q->where('pickup_wilaya_id', $id))
            ->when($filters['commune_id'] ?? null, fn ($q, $id) => $q->where('pickup_commune_id', $id))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['transmission'] ?? null, fn ($q, $v) => $q->where('transmission', $v))
            ->when($filters['fuel'] ?? null, fn ($q, $v) => $q->where('fuel', $v))
            ->when($filters['seats'] ?? null, fn ($q, $v) => $q->where('seats', '>=', (int) $v))
            ->when($filters['air_conditioning'] ?? null, fn ($q) => $q->where('air_conditioning', true))
            ->when($filters['with_driver'] ?? null, fn ($q) => $q->where('with_driver_available', true))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($sub) => $sub->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
            ))
            // Le prix se filtre par EXISTS sur la regle journaliere, pas par
            // HAVING sur l'alias : SQLite refuse un HAVING sans GROUP BY, et
            // la base de test est SQLite.
            ->when($filters['price_min'] ?? null, fn ($q, $v) => $q->whereHas(
                'pricingRules',
                fn ($r) => $r->where('duration_type', PricingRule::DAILY)->where('price_dzd', '>=', (int) $v)
            ))
            ->when($filters['price_max'] ?? null, fn ($q, $v) => $q->whereHas(
                'pricingRules',
                fn ($r) => $r->where('duration_type', PricingRule::DAILY)->where('price_dzd', '<=', (int) $v)
            ));
    }

    /**
     * The ordering of specification 4.2:
     *
     *   1. Platinium listings of the searched wilaya
     *   2. Gold and Platinium listings of the searched commune
     *   3. everyone else, Silver included
     *
     * and inside a tier, a rotation reseeded every day so that one agency does
     * not own the first row for good.
     *
     * A manual sort replaces all of it. That is the transparency trade named in
     * the specification: a visitor who asks for the cheapest gets the cheapest.
     */
    public function sort(Builder $query, array $filters, ?string $manual = null): Builder
    {
        if (in_array($manual, self::SORTS, true)) {
            return match ($manual) {
                'price_asc' => $query->orderByRaw('daily_price is null')->orderBy('daily_price'),
                'price_desc' => $query->orderByDesc('daily_price'),
                'recent' => $query->latest('published_at'),
            };
        }

        $wilayaId = $filters['wilaya_id'] ?? null;
        $communeId = $filters['commune_id'] ?? null;

        $query->orderByRaw(
            'CASE
                WHEN ? IS NOT NULL AND pickup_wilaya_id = ? AND plan_wilaya_priority = 1 THEN 0
                WHEN ? IS NOT NULL AND pickup_commune_id = ? AND plan_commune_priority = 1 THEN 1
                ELSE 2
            END',
            [$wilayaId, $wilayaId, $communeId, $communeId]
        );

        return $query->orderByRaw($this->rotationExpression())->latest('published_at');
    }

    /**
     * A stable pseudo-random order that changes once a day. Written in plain
     * arithmetic rather than with MySQL's RAND(seed): the same expression has
     * to run on SQLite, and a seeded shuffle must stay identical from one page
     * of results to the next, or a listing would appear twice and another
     * never.
     */
    private function rotationExpression(): string
    {
        $seed = (int) date('Ymd');

        return '(vehicles.id * '.($seed % 9973 + 7).') % 9973';
    }

    /**
     * Listings the Platinium plan puts on the home page. Restricted to that
     * plan on purpose: the slot is what the agency pays for, and a homepage
     * that features everyone features no one.
     */
    public function homepageFeatured(int $limit = 6)
    {
        return $this->query()
            ->whereIn('agency_id', $this->agencyIdsOnFeaturedPlans())
            ->orderByRaw($this->rotationExpression())
            ->limit($limit)
            ->get();
    }

    /** The newest listings, all plans together. */
    public function latest(int $limit = 8)
    {
        return $this->query()->latest('published_at')->limit($limit)->get();
    }

    private function agencyIdsOnFeaturedPlans()
    {
        return \App\Models\Subscription::query()
            ->select('agency_id')
            ->where('subscriptions.status', 'active')
            ->whereIn('plan_id', Plan::where('has_homepage_feature', true)->select('id'));
    }

    /**
     * The plan in force for the listing's agency, column by column. A subquery
     * rather than a join: an agency with two rows in `subscriptions` would
     * otherwise duplicate every one of its listings in the results.
     */
    private function planColumn(string $column)
    {
        return Plan::query()
            ->select("plans.{$column}")
            ->join('subscriptions', 'subscriptions.plan_id', '=', 'plans.id')
            ->whereColumn('subscriptions.agency_id', 'vehicles.agency_id')
            ->where('subscriptions.status', 'active')
            ->orderByDesc('subscriptions.starts_at')
            ->limit(1);
    }
}
