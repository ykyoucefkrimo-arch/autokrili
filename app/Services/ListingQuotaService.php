<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Plan;
use App\Models\Vehicle;

/**
 * The plan quotas of specification 4.1, asked as questions rather than read as
 * numbers. Every caller — controller, request rule, Vue page — goes through
 * here so the same rule cannot be spelled two different ways.
 *
 * A listing occupies a slot unless it is archived: a rejected listing is one
 * the agency is expected to correct and resubmit, so it still counts. Archiving
 * is what frees a slot, and it is the one action that is always available.
 */
class ListingQuotaService
{
    /** Statuses that consume one of the plan's listing slots. */
    public const OCCUPYING_STATUSES = [
        Vehicle::STATUS_DRAFT,
        Vehicle::STATUS_PENDING,
        Vehicle::STATUS_PUBLISHED,
        Vehicle::STATUS_REJECTED,
    ];

    public function usedListings(Agency $agency): int
    {
        return $agency->vehicles()->whereIn('status', self::OCCUPYING_STATUSES)->count();
    }

    public function maxListings(Agency $agency): ?int
    {
        return $agency->currentPlan()?->max_listings;
    }

    public function canAddListing(Agency $agency): bool
    {
        $max = $this->maxListings($agency);

        return $max === null || $this->usedListings($agency) < $max;
    }

    public function maxPhotos(Agency $agency): int
    {
        // Silver allows a single photo; a plan without a value would silently
        // allow none, which reads as a bug rather than as a quota.
        return (int) ($agency->currentPlan()?->max_photos ?: 1);
    }

    public function canAddPhoto(Vehicle $vehicle, int $adding = 1): bool
    {
        return $vehicle->photos()->count() + $adding <= $this->maxPhotos($vehicle->agency);
    }

    public function remainingPhotos(Vehicle $vehicle): int
    {
        return max(0, $this->maxPhotos($vehicle->agency) - $vehicle->photos()->count());
    }

    /**
     * The message shown when a quota blocks. The specification asks for the
     * current plan, its allowance and what the next plan would give: a refusal
     * that names the way out is the difference between a wall and an offer.
     */
    public function listingLimitMessage(Agency $agency): string
    {
        $plan = $agency->currentPlan();
        $max = $plan?->max_listings;

        // NULL is the unlimited plan: there is no limit to announce, and no
        // upgrade to suggest above it.
        if ($max === null) {
            return "Votre formule {$plan?->name} n’impose aucune limite d’annonces.";
        }

        $message = "Votre formule {$plan?->name} autorise {$max} annonce".($max > 1 ? 's' : '').' active'.($max > 1 ? 's' : '').'.';

        return $message.$this->upgradeHint($plan, 'max_listings', 'annonces');
    }

    public function photoLimitMessage(Agency $agency): string
    {
        $plan = $agency->currentPlan();
        $max = $this->maxPhotos($agency);

        $message = "Votre formule {$plan?->name} autorise {$max} photo".($max > 1 ? 's' : '').' par annonce.';

        return $message.$this->upgradeHint($plan, 'max_photos', 'photos');
    }

    /**
     * Names the cheapest plan that actually lifts the limit. Suggesting a plan
     * that grants the same allowance would be a sales pitch, not information.
     */
    private function upgradeHint(?Plan $current, string $column, string $noun): string
    {
        // Nothing to suggest above a plan that is already unlimited on this
        // column — and comparing a number against NULL is not a query.
        if (! $current || $current->$column === null) {
            return '';
        }

        $better = Plan::where('is_active', true)
            ->where('sort_order', '>', $current->sort_order)
            ->where(fn ($q) => $q->whereNull($column)->orWhere($column, '>', $current->$column))
            ->orderBy('sort_order')
            ->first();

        if (! $better) {
            return '';
        }

        $allowance = $better->$column === null ? 'un nombre illimité' : $better->$column;

        return " Passez en {$better->name} pour en avoir {$allowance}.";
    }
}
