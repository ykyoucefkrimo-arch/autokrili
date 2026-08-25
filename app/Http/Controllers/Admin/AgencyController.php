<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Wilaya;
use App\Services\AgencyModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyController extends Controller
{
    public function __construct(private readonly AgencyModerationService $moderation)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'wilaya_id', 'search']);

        $agencies = Agency::query()
            // Eager loaded: without this the listing fires one query per row.
            ->with(['wilaya:id,name_fr', 'activeSubscription.plan:id,name,slug,badge_color'])
            ->withCount('vehicles')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['wilaya_id'] ?? null, fn ($q, $id) => $q->where('wilaya_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($sub) => $sub->where('commercial_name', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%")
                    ->orWhere('trade_register_number', 'like', "%{$search}%")
            ))
            // Pending first: the queue to clear is the reason to open this page.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Agency $a) => [
                'id' => $a->id,
                'commercial_name' => $a->commercial_name,
                'manager_name' => $a->manager_name,
                'wilaya' => $a->wilaya?->name_fr,
                'status' => $a->status,
                'vehicles_count' => $a->vehicles_count,
                'logo_url' => $a->logo_path ? Storage::disk('public')->url($a->logo_path) : null,
                'plan' => $a->activeSubscription?->plan?->only(['name', 'slug', 'badge_color']),
                'created_at' => $a->created_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('Admin/Agencies/Index', [
            'agencies' => $agencies,
            'filters' => $filters,
            'wilayas' => Wilaya::orderBy('code')->get(['id', 'name_fr']),
            'counts' => [
                'pending' => Agency::where('status', Agency::STATUS_PENDING)->count(),
            ],
        ]);
    }

    public function show(Agency $agency): Response
    {
        $agency->load(['user:id,name,email,email_verified_at', 'wilaya:id,name_fr', 'commune:id,name_fr',
            'activeSubscription.plan:id,name,slug']);

        return Inertia::render('Admin/Agencies/Show', [
            // La matrice, pour attribuer une formule sans attendre que
            // l'agence en fasse la demande (§4.3).
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'price_dzd', 'max_listings', 'max_photos']),
            'agency' => [
                'id' => $agency->id,
                'commercial_name' => $agency->commercial_name,
                'manager_name' => $agency->manager_name,
                'trade_register_number' => $agency->trade_register_number,
                'nif' => $agency->nif,
                'address' => $agency->address,
                'wilaya' => $agency->wilaya?->name_fr,
                'commune' => $agency->commune?->name_fr,
                'phone' => $agency->phone,
                'whatsapp' => $agency->whatsapp,
                'description' => $agency->description,
                'status' => $agency->status,
                'rejection_reason' => $agency->rejection_reason,
                'is_trusted' => $agency->is_trusted,
                'logo_url' => $agency->logo_path ? Storage::disk('public')->url($agency->logo_path) : null,
                'has_trade_register' => (bool) $agency->trade_register_file,
                'email' => $agency->user?->email,
                'email_verified' => (bool) $agency->user?->email_verified_at,
                'plan' => $agency->activeSubscription?->plan?->only(['id', 'name', 'slug']),
                'plan_ends_at' => $agency->activeSubscription?->ends_at?->translatedFormat('d F Y'),
                'plan_note' => $agency->activeSubscription?->admin_note,
                'created_at' => $agency->created_at->translatedFormat('d F Y'),
                'approved_at' => $agency->approved_at?->translatedFormat('d F Y'),
            ],
        ]);
    }

    public function approve(Agency $agency): RedirectResponse
    {
        $this->moderation->approve($agency);

        return back()->with('success', "L'agence « {$agency->commercial_name} » est approuvée.");
    }

    public function reject(Request $request, Agency $agency): RedirectResponse
    {
        // The motive is mandatory: a refusal the agency cannot act on is a
        // dead end, and the specification requires it twice.
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Le motif est obligatoire.',
            'reason.min' => 'Le motif doit être assez explicite pour que l’agence puisse corriger.',
        ]);

        $this->moderation->reject($agency, $data['reason']);

        return back()->with('success', "L'agence « {$agency->commercial_name} » est rejetée.");
    }

    public function suspend(Request $request, Agency $agency): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Le motif est obligatoire.',
        ]);

        $this->moderation->suspend($agency, $data['reason']);

        return back()->with('success', "L'agence « {$agency->commercial_name} » est suspendue.");
    }

    public function reinstate(Agency $agency): RedirectResponse
    {
        $this->moderation->reinstate($agency);

        return back()->with('success', "L'agence « {$agency->commercial_name} » est réactivée.");
    }

    public function trust(Request $request, Agency $agency): RedirectResponse
    {
        $this->moderation->setTrusted($agency, $request->boolean('is_trusted'));

        return back()->with('success', 'Le statut de confiance est mis à jour.');
    }

    /**
     * Streams the trade register from the private disk. The file never sits
     * under a URL the web server can serve on its own (specification 11).
     */
    public function tradeRegister(Request $request, Agency $agency): StreamedResponse
    {
        abort_unless($request->user()->can('viewTradeRegister', $agency), 403);
        abort_unless($agency->trade_register_file, 404);
        abort_unless(Storage::disk('local')->exists($agency->trade_register_file), 404);

        return Storage::disk('local')->response(
            $agency->trade_register_file,
            'registre-commerce-'.$agency->slug.'.'.pathinfo($agency->trade_register_file, PATHINFO_EXTENSION),
            ['Content-Disposition' => 'inline']
        );
    }
}
