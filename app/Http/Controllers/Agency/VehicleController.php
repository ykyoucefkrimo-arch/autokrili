<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\VehicleRequest;
use App\Models\PricingRule;
use App\Models\Vehicle;
use App\Models\Wilaya;
use App\Services\ReferenceDataService;
use App\Services\ListingQuotaService;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleService $vehicles,
        private readonly ListingQuotaService $quotas,
        private readonly ReferenceDataService $reference,
    ) {
    }

    public function index(Request $request): Response
    {
        $agency = $request->user()->activeAgency();
        $status = $request->input('status');

        $vehicles = $agency->vehicles()
            ->with(['coverPhoto', 'pickupCommune:id,name_fr', 'pricingRules'])
            ->withCount('photos')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->input('search'), fn ($q, $search) => $q->where(
                fn ($sub) => $sub->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
            ))
            // Rejected first, then drafts: the listings that need the agency's
            // attention are the reason to open this page. Written as a CASE
            // rather than MySQL's FIELD(), which SQLite — the test database —
            // does not have.
            ->orderByRaw("CASE status
                WHEN 'rejected' THEN 0
                WHEN 'draft' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'published' THEN 3
                ELSE 4 END")
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Vehicle $v) => $this->card($v));

        return Inertia::render('Agency/Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => ['status' => $status, 'search' => $request->input('search')],
            'counts' => $agency->vehicles()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'quota' => $this->quotaPayload($request),
            'canWrite' => ! $agency->isSuspended(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Agency/Vehicles/Form', $this->formPayload($request) + [
            'vehicle' => null,
        ]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        $agency = $request->user()->activeAgency();

        try {
            $vehicle = $this->vehicles->create($agency, $request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['quota' => $e->getMessage()]);
        }

        // Straight to the edit screen: a listing without photos cannot be
        // submitted, and the photo box only exists once the listing has an id.
        return redirect()
            ->route('agency.vehicles.edit', $vehicle)
            ->with('success', 'Annonce créée. Ajoutez vos photos, puis soumettez-la à la modération.');
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        $this->authorize('view', $vehicle);
        $vehicle->load(['photos', 'pricingRules']);

        return Inertia::render('Agency/Vehicles/Form', $this->formPayload($request) + [
            'vehicle' => $this->detail($vehicle),
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $wasPublished = $vehicle->isPublished();
        $vehicle = $this->vehicles->update($vehicle, $request->validated());

        $message = $wasPublished && $vehicle->status === Vehicle::STATUS_PENDING
            ? 'Annonce enregistrée. Toute modification repasse en modération : elle reste visible en attendant.'
            : 'Annonce enregistrée.';

        return back()->with('success', $message);
    }

    public function submit(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        try {
            $vehicle = $this->vehicles->submit($vehicle);
        } catch (RuntimeException $e) {
            return back()->withErrors(['submit' => $e->getMessage()]);
        }

        return back()->with('success', $vehicle->isPublished()
            ? 'Votre agence est de confiance : l’annonce est publiée immédiatement.'
            : 'Annonce envoyée à la modération.');
    }

    public function archive(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        $this->vehicles->archive($vehicle);

        return back()->with('success', 'Annonce archivée : elle ne compte plus dans votre quota.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);
        $this->vehicles->delete($vehicle);

        return redirect()->route('agency.vehicles.index')->with('success', 'Annonce supprimée.');
    }

    /** Shared by the create and edit screens. */
    private function formPayload(Request $request): array
    {
        return [
            'wilayas' => $this->reference->wilayas(),
            'options' => [
                'categories' => Vehicle::CATEGORIES,
                'transmissions' => Vehicle::TRANSMISSIONS,
                'fuels' => Vehicle::FUELS,
            ],
            'quota' => $this->quotaPayload($request),
        ];
    }

    /**
     * The quota travels to the browser as numbers *and* as the sentence to
     * show: the wording of a refusal is a product decision, not something the
     * Vue page should be reinventing.
     */
    private function quotaPayload(Request $request): array
    {
        $agency = $request->user()->activeAgency();

        return [
            'used_listings' => $this->quotas->usedListings($agency),
            'max_listings' => $this->quotas->maxListings($agency),
            'can_add_listing' => $this->quotas->canAddListing($agency),
            'max_photos' => $this->quotas->maxPhotos($agency),
            'plan_name' => $agency->currentPlan()?->name,
            'listing_message' => $this->quotas->listingLimitMessage($agency),
            'photo_message' => $this->quotas->photoLimitMessage($agency),
        ];
    }

    private function card(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'title' => $vehicle->title(),
            'status' => $vehicle->status,
            'rejection_reason' => $vehicle->rejection_reason,
            'commune' => $vehicle->pickupCommune?->name_fr,
            'views_count' => $vehicle->views_count,
            'photos_count' => $vehicle->photos_count,
            'daily_price' => $vehicle->pricingRules
                ->firstWhere('duration_type', PricingRule::DAILY)?->price_dzd,
            'cover_url' => $vehicle->coverPhoto
                ? Storage::disk('public')->url($vehicle->coverPhoto->path_card ?? $vehicle->coverPhoto->path)
                : null,
            'updated_at' => $vehicle->updated_at->translatedFormat('d M Y'),
        ];
    }

    private function detail(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'title' => $vehicle->title(),
            'status' => $vehicle->status,
            'rejection_reason' => $vehicle->rejection_reason,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'category' => $vehicle->category,
            'transmission' => $vehicle->transmission,
            'fuel' => $vehicle->fuel,
            'seats' => $vehicle->seats,
            'doors' => $vehicle->doors,
            'air_conditioning' => $vehicle->air_conditioning,
            'mileage_limit_per_day' => $vehicle->mileage_limit_per_day,
            'description' => $vehicle->description,
            'pickup_wilaya_id' => $vehicle->pickup_wilaya_id,
            'pickup_commune_id' => $vehicle->pickup_commune_id,
            'with_driver_available' => $vehicle->with_driver_available,
            'driver_price_per_day' => $vehicle->driver_price_per_day,
            'pricing' => $vehicle->pricingRules->pluck('price_dzd', 'duration_type'),
            'photos' => $vehicle->photos->map(fn ($p) => [
                'id' => $p->id,
                'url' => Storage::disk('public')->url($p->path_card ?? $p->path),
                'is_cover' => $p->is_cover,
            ]),
        ];
    }
}
