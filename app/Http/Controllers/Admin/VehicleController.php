<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\PricingRule;
use App\Models\Vehicle;
use App\Models\Wilaya;
use App\Services\VehicleModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The listing moderation queue of specification 9. It shows what the public
 * would see, because approving a listing on a summary table is approving
 * something nobody has actually looked at.
 */
class VehicleController extends Controller
{
    public function __construct(private readonly VehicleModerationService $moderation)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'wilaya_id', 'agency_id', 'search']);
        // Pending by default: the queue is the reason to open this page.
        $status = $filters['status'] ?? Vehicle::STATUS_PENDING;

        $vehicles = Vehicle::query()
            ->with(['agency:id,commercial_name,is_trusted', 'coverPhoto', 'pickupWilaya:id,name_fr', 'pricingRules'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['wilaya_id'] ?? null, fn ($q, $id) => $q->where('pickup_wilaya_id', $id))
            ->when($filters['agency_id'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($sub) => $sub->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
            ))
            // Oldest first inside the queue: a listing must not wait forever
            // because newer ones keep arriving.
            ->orderBy($status === Vehicle::STATUS_PENDING ? 'updated_at' : 'created_at',
                $status === Vehicle::STATUS_PENDING ? 'asc' : 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Vehicle $v) => [
                'id' => $v->id,
                'title' => $v->title(),
                'status' => $v->status,
                'agency' => $v->agency?->commercial_name,
                'agency_is_trusted' => (bool) $v->agency?->is_trusted,
                'wilaya' => $v->pickupWilaya?->name_fr,
                'daily_price' => $v->pricingRules->firstWhere('duration_type', PricingRule::DAILY)?->price_dzd,
                'cover_url' => $v->coverPhoto
                    ? Storage::disk('public')->url($v->coverPhoto->path_card ?? $v->coverPhoto->path)
                    : null,
                'submitted_at' => $v->updated_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('Admin/Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => $filters + ['status' => $status],
            'wilayas' => Wilaya::orderBy('code')->get(['id', 'name_fr']),
            'agencies' => Agency::where('status', Agency::STATUS_APPROVED)
                ->orderBy('commercial_name')
                ->get(['id', 'commercial_name']),
            'reasons' => VehicleModerationService::REASONS,
            'counts' => [
                'pending' => Vehicle::where('status', Vehicle::STATUS_PENDING)->count(),
            ],
        ]);
    }

    /** The preview: the same content a visitor would get, before it is public. */
    public function show(Vehicle $vehicle): Response
    {
        $vehicle->load(['agency.wilaya:id,name_fr', 'photos', 'pricingRules',
            'pickupWilaya:id,name_fr', 'pickupCommune:id,name_fr']);

        return Inertia::render('Admin/Vehicles/Show', [
            'vehicle' => [
                'id' => $vehicle->id,
                'title' => $vehicle->title(),
                'status' => $vehicle->status,
                'rejection_reason' => $vehicle->rejection_reason,
                'category' => $vehicle->category,
                'transmission' => $vehicle->transmission,
                'fuel' => $vehicle->fuel,
                'seats' => $vehicle->seats,
                'doors' => $vehicle->doors,
                'air_conditioning' => $vehicle->air_conditioning,
                'mileage_limit_per_day' => $vehicle->mileage_limit_per_day,
                'description' => $vehicle->description,
                'with_driver_available' => $vehicle->with_driver_available,
                'driver_price_per_day' => $vehicle->driver_price_per_day,
                'pickup' => trim(($vehicle->pickupCommune?->name_fr ?? '').', '.($vehicle->pickupWilaya?->name_fr ?? ''), ', '),
                'pricing' => $vehicle->pricingRules->map(fn (PricingRule $r) => [
                    'duration_type' => $r->duration_type,
                    'price_dzd' => $r->price_dzd,
                    'min_days' => $r->min_days,
                ]),
                'photos' => $vehicle->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => Storage::disk('public')->url($p->path),
                    'is_cover' => $p->is_cover,
                ]),
                'agency' => [
                    'id' => $vehicle->agency->id,
                    'commercial_name' => $vehicle->agency->commercial_name,
                    'wilaya' => $vehicle->agency->wilaya?->name_fr,
                    'is_trusted' => $vehicle->agency->is_trusted,
                ],
                'submitted_at' => $vehicle->updated_at->translatedFormat('d F Y à H:i'),
            ],
            'reasons' => VehicleModerationService::REASONS,
        ]);
    }

    public function approve(Vehicle $vehicle): RedirectResponse
    {
        $this->moderation->approve($vehicle);

        return back()->with('success', "L'annonce « {$vehicle->title()} » est publiée.");
    }

    public function reject(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $reason = $this->resolveReason($request);

        $this->moderation->reject($vehicle, $reason);

        return back()->with('success', "L'annonce « {$vehicle->title()} » est renvoyée à l'agence.");
    }

    /** Batch handling: a queue of twenty near-identical listings is common. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:vehicles,id'],
            'action' => ['required', Rule::in(['approve', 'reject'])],
        ]);

        if ($data['action'] === 'approve') {
            $count = $this->moderation->approveMany($data['ids']);

            return back()->with('success', "{$count} annonce(s) publiée(s).");
        }

        $count = $this->moderation->rejectMany($data['ids'], $this->resolveReason($request));

        return back()->with('success', "{$count} annonce(s) renvoyée(s) à leur agence.");
    }

    /**
     * A rejection carries either a predefined motive, a free text, or both.
     * The free text alone is allowed but must be explicit enough to act on —
     * the same bar as the agency rejections.
     */
    private function resolveReason(Request $request): string
    {
        $data = $request->validate([
            'reason_code' => ['nullable', Rule::in(array_keys(VehicleModerationService::REASONS))],
            'reason_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $parts = array_filter([
            $data['reason_code'] ?? null ? VehicleModerationService::REASONS[$data['reason_code']] : null,
            $data['reason_note'] ?? null,
        ]);

        if ($parts === []) {
            throw ValidationException::withMessages([
                'reason_code' => 'Choisissez un motif ou écrivez-en un.',
            ]);
        }

        $reason = implode(' — ', $parts);

        // Guards the free-text-only case: "non" tells the agency nothing.
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason_note' => 'Le motif doit être assez explicite pour que l’agence puisse corriger.',
            ]);
        }

        return $reason;
    }
}
