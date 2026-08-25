<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The life of a listing on the agency side: created as a draft, submitted to
 * moderation, edited, archived. Publication itself belongs to
 * VehicleModerationService — except for trusted agencies, whose submissions
 * publish immediately (specification 9).
 */
class VehicleService
{
    public function __construct(
        private readonly ListingQuotaService $quotas,
        private readonly VehiclePhotoService $photos,
    ) {
    }

    public function create(Agency $agency, array $data): Vehicle
    {
        if (! $this->quotas->canAddListing($agency)) {
            throw new RuntimeException($this->quotas->listingLimitMessage($agency));
        }

        return DB::transaction(function () use ($agency, $data) {
            $vehicle = $agency->vehicles()->create($this->attributes($data) + [
                'slug' => $this->uniqueSlug($data),
                // Always a draft first: an agency fills a form in several
                // sittings, and a half-written listing has no business being
                // in the moderation queue.
                'status' => Vehicle::STATUS_DRAFT,
            ]);

            $this->syncPricing($vehicle, $data['pricing'] ?? []);

            return $vehicle;
        });
    }

    /**
     * Editing a published listing sends it back to moderation, unless the
     * agency is trusted (specification 9). The listing stays visible while it
     * waits: taking a working listing offline over a phone-number change would
     * punish the agency for keeping its page accurate.
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data) {
            $vehicle->update($this->attributes($data));
            $this->syncPricing($vehicle, $data['pricing'] ?? []);

            if ($vehicle->status === Vehicle::STATUS_PUBLISHED && ! $vehicle->agency->is_trusted) {
                $vehicle->update(['status' => Vehicle::STATUS_PENDING]);
            }

            return $vehicle->refresh();
        });
    }

    /**
     * Sends a listing to the moderation queue. The two prerequisites are
     * checked here rather than in the form: they are what makes a listing
     * showable at all, and a listing can reach this point from an edit that
     * removed its last photo.
     */
    public function submit(Vehicle $vehicle): Vehicle
    {
        if ($vehicle->photos()->count() === 0) {
            throw new RuntimeException('Ajoutez au moins une photo avant de soumettre cette annonce.');
        }

        if (! $vehicle->pricingRules()->where('duration_type', PricingRule::DAILY)->exists()) {
            throw new RuntimeException('Renseignez au moins un tarif journalier avant de soumettre cette annonce.');
        }

        // An archived listing coming back must fit under the current quota:
        // this is the moment a downgrade would otherwise be undone silently.
        if ($vehicle->status === Vehicle::STATUS_ARCHIVED && ! $this->quotas->canAddListing($vehicle->agency)) {
            throw new RuntimeException($this->quotas->listingLimitMessage($vehicle->agency));
        }

        $trusted = $vehicle->agency->is_trusted;

        $vehicle->update([
            'status' => $trusted ? Vehicle::STATUS_PUBLISHED : Vehicle::STATUS_PENDING,
            'rejection_reason' => null,
            'published_at' => $trusted ? ($vehicle->published_at ?? now()) : $vehicle->published_at,
        ]);

        return $vehicle->refresh();
    }

    /**
     * Archiving is the reversible way out: it frees a listing slot and hides
     * the listing without destroying its photos or its booking history.
     */
    public function archive(Vehicle $vehicle): Vehicle
    {
        $vehicle->update(['status' => Vehicle::STATUS_ARCHIVED]);

        return $vehicle->refresh();
    }

    /** Deletion is soft: bookings keep pointing at what was rented. */
    public function delete(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            $this->photos->deleteAll($vehicle);
            $vehicle->delete();
        });
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => $data['year'],
            'category' => $data['category'],
            'transmission' => $data['transmission'],
            'fuel' => $data['fuel'],
            'seats' => $data['seats'],
            'doors' => $data['doors'],
            'air_conditioning' => (bool) ($data['air_conditioning'] ?? false),
            'mileage_limit_per_day' => $data['mileage_limit_per_day'] ?? null,
            'description' => $data['description'] ?? null,
            'pickup_wilaya_id' => $data['pickup_wilaya_id'],
            'pickup_commune_id' => $data['pickup_commune_id'],
            'with_driver_available' => (bool) ($data['with_driver_available'] ?? false),
            // A chauffeur price without the option is dead data that would
            // resurface the day the option is switched back on.
            'driver_price_per_day' => ($data['with_driver_available'] ?? false)
                ? ($data['driver_price_per_day'] ?? 0)
                : 0,
        ];
    }

    /**
     * Degressive pricing. Rules arrive as a full picture of what the agency
     * wants, so anything absent is removed rather than left behind: a stale
     * weekly rate would keep discounting a price the agency thinks it raised.
     */
    private function syncPricing(Vehicle $vehicle, array $pricing): void
    {
        foreach ([PricingRule::DAILY, PricingRule::WEEKLY, PricingRule::MONTHLY] as $type) {
            $price = $pricing[$type] ?? null;

            if (! $price) {
                $vehicle->pricingRules()->where('duration_type', $type)->delete();

                continue;
            }

            $vehicle->pricingRules()->updateOrCreate(
                ['duration_type' => $type],
                ['price_dzd' => (int) $price, 'min_days' => PricingRule::UNIT_DAYS[$type]]
            );
        }
    }

    /** The slug lands in a public URL, so it must be unique on its own. */
    private function uniqueSlug(array $data): string
    {
        $base = Str::slug("{$data['brand']}-{$data['model']}-{$data['year']}");
        $slug = $base;
        $suffix = 2;

        while (Vehicle::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
