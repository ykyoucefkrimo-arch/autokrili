<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Notifications\VehicleApproved;
use App\Notifications\VehicleRejected;
use Illuminate\Support\Facades\DB;

/**
 * Administrative moderation of the listings (specification 9). Like the agency
 * moderation service, each decision changes the status, writes the audit entry
 * and notifies the agency in one place, so the journal cannot drift from what
 * actually happened.
 */
class VehicleModerationService
{
    /**
     * The predefined motives of the specification. Free text stays possible,
     * but a fixed list is what makes rejections comparable from one
     * administrator to the next — and lets the agency recognise the problem.
     */
    public const REASONS = [
        'photos' => 'Photos de mauvaise qualité ou non conformes',
        'prix' => 'Prix incohérent',
        'informations' => 'Informations manquantes ou erronées',
        'contenu' => 'Contenu inapproprié',
        'doublon' => 'Annonce en doublon',
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function approve(Vehicle $vehicle): Vehicle
    {
        return DB::transaction(function () use ($vehicle) {
            $before = $vehicle->getAttributes();

            $vehicle->update([
                'status' => Vehicle::STATUS_PUBLISHED,
                'rejection_reason' => null,
                // Kept on first publication: it dates the listing's entry into
                // the catalogue, and a re-approval after an edit is not a new
                // listing.
                'published_at' => $vehicle->published_at ?? now(),
            ]);

            $this->audit->logChange('vehicle.approved', $vehicle, $before);
            $vehicle->agency->user->notify(new VehicleApproved($vehicle));

            return $vehicle->refresh();
        });
    }

    public function reject(Vehicle $vehicle, string $reason): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $reason) {
            $before = $vehicle->getAttributes();

            $vehicle->update([
                'status' => Vehicle::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            $this->audit->logChange('vehicle.rejected', $vehicle, $before);
            $vehicle->agency->user->notify(new VehicleRejected($vehicle, $reason));

            return $vehicle->refresh();
        });
    }

    /**
     * Batch approval. Each listing is decided on its own transaction: a
     * failure on the seventh must not undo the six already handled, nor stop
     * the administrator's queue from emptying.
     *
     * @param  array<int, int>  $ids
     * @return int  listings actually approved
     */
    public function approveMany(array $ids): int
    {
        return Vehicle::whereIn('id', $ids)
            ->where('status', Vehicle::STATUS_PENDING)
            ->with('agency.user')
            ->get()
            ->each(fn (Vehicle $vehicle) => $this->approve($vehicle))
            ->count();
    }

    /** @param  array<int, int>  $ids */
    public function rejectMany(array $ids, string $reason): int
    {
        return Vehicle::whereIn('id', $ids)
            ->where('status', Vehicle::STATUS_PENDING)
            ->with('agency.user')
            ->get()
            ->each(fn (Vehicle $vehicle) => $this->reject($vehicle, $reason))
            ->count();
    }
}
