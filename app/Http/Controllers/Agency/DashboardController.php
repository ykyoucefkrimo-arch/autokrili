<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\ListingQuotaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bookings and revenue figures of specification 8 arrive with the phases
 * that create the data they display. What exists today — the listings and the
 * plan quotas — is shown for real.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ListingQuotaService $quotas)
    {
    }

    public function __invoke(Request $request): Response
    {
        $agency = $request->user()->activeAgency();
        $plan = $agency->currentPlan();

        $byStatus = $agency->vehicles()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Agency/Dashboard', [
            'agency' => [
                'commercial_name' => $agency->commercial_name,
                'status' => $agency->status,
                'is_suspended' => $agency->isSuspended(),
                'wilaya' => $agency->wilaya?->name_fr,
                'commune' => $agency->commune?->name_fr,
            ],
            'plan' => $plan ? [
                'name' => $plan->name,
                'max_listings' => $plan->max_listings,
                'max_photos' => $plan->max_photos,
            ] : null,
            'listings' => [
                'used' => $this->quotas->usedListings($agency),
                'can_add' => $this->quotas->canAddListing($agency),
                'published' => (int) ($byStatus[Vehicle::STATUS_PUBLISHED] ?? 0),
                'pending' => (int) ($byStatus[Vehicle::STATUS_PENDING] ?? 0),
                'rejected' => (int) ($byStatus[Vehicle::STATUS_REJECTED] ?? 0),
                'draft' => (int) ($byStatus[Vehicle::STATUS_DRAFT] ?? 0),
                'views' => (int) $agency->vehicles()->sum('views_count'),
            ],
        ]);
    }
}
