<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Notifications\PlanChangeHandled;
use App\Notifications\SubscriptionDowngraded;
use App\Services\AuditLogger;
use App\Services\ReferenceDataService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plan matrix and who is on it (specification 4.1 and 4.3).
 *
 * The specification is explicit that the allowances live in the database and
 * are editable here — never hard-coded in PHP. This screen is what makes that
 * promise true rather than merely technically possible.
 */
class PlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
        private readonly ReferenceDataService $reference,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::orderBy('sort_order')
                ->withCount(['subscriptions as agencies_count' => fn ($q) => $q
                    ->where('status', Subscription::STATUS_ACTIVE)])
                ->get(),
            'requests' => PlanChangeRequest::query()
                ->with(['agency:id,commercial_name,slug', 'requestedPlan:id,name,slug'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->take(30)
                ->get()
                ->map(fn (PlanChangeRequest $r) => [
                    'id' => $r->id,
                    'agency' => $r->agency?->commercial_name,
                    'agency_id' => $r->agency_id,
                    'plan' => $r->requestedPlan?->name,
                    'plan_id' => $r->requested_plan_id,
                    'status' => $r->status,
                    'agency_message' => $r->agency_message,
                    'admin_response' => $r->admin_response,
                    'created_at' => $r->created_at->translatedFormat('d M Y'),
                ]),
            // Les abonnements qui expirent bientôt : c'est la relance
            // commerciale, et la plateforme n'encaisse rien en ligne.
            'expiring' => Subscription::query()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->whereNotNull('ends_at')
                ->whereDate('ends_at', '<=', now()->addDays(30)->toDateString())
                ->with(['agency:id,commercial_name', 'plan:id,name'])
                ->orderBy('ends_at')
                ->get()
                ->map(fn (Subscription $s) => [
                    'agency' => $s->agency?->commercial_name,
                    'agency_id' => $s->agency_id,
                    'plan' => $s->plan?->name,
                    'ends_at' => $s->ends_at->translatedFormat('d M Y'),
                    'days_left' => (int) now()->startOfDay()->diffInDays($s->ends_at, false),
                ]),
            'counts' => [
                'pending_requests' => PlanChangeRequest::where('status', PlanChangeRequest::STATUS_PENDING)->count(),
            ],
        ]);
    }

    /**
     * Editing the matrix. Nothing is created or deleted here: the three plans
     * are the product, and a fourth would need screens, badges and a pricing
     * page that do not exist.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'price_dzd' => ['required', 'integer', 'min:0', 'max:10000000'],
            // NULL vaut illimité, jamais un grand nombre : une sentinelle finit
            // toujours par s'afficher quelque part.
            'max_listings' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_photos' => ['required', 'integer', 'min:1', 'max:50'],
            'max_users' => ['required', 'integer', 'min:1', 'max:100'],
            'has_commune_priority' => ['boolean'],
            'has_wilaya_priority' => ['boolean'],
            'has_homepage_feature' => ['boolean'],
            'can_reply_reviews' => ['boolean'],
            'stats_level' => ['required', Rule::in(['basic', 'advanced', 'premium'])],
            'badge_label' => ['nullable', 'string', 'max:30'],
            'badge_color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $before = $plan->getAttributes();
        $plan->update($data);
        $this->audit->logChange('plan.updated', $plan, $before);

        // Le cache des formules est vide explicitement plutot que laisse
        // expirer : une modification doit se voir tout de suite, sinon
        // l'administrateur la refait en croyant qu'elle n'a pas pris.
        $this->reference->forget();

        // Baisser un quota peut mettre des agences hors des clous : le service
        // archive ce qui dépasse et les prévient, plutôt que de les laisser
        // publier au-delà de ce qu'elles paient.
        $affected = 0;

        if (($before['max_listings'] ?? null) !== $plan->max_listings) {
            foreach ($this->agenciesOn($plan) as $agency) {
                $archived = $this->subscriptions->archiveExcessListings($agency);

                // archiveExcessListings est une primitive : prevenir appartient
                // a l'appelant, comme le fait grant().
                if ($archived->isNotEmpty()) {
                    $agency->user?->notify(new SubscriptionDowngraded($agency, $plan, $archived));
                    $affected += $archived->count();
                }
            }
        }

        return back()->with('success', $affected > 0
            ? "Formule enregistrée. {$affected} annonce(s) archivée(s) chez les agences concernées, qui en sont prévenues."
            : 'Formule enregistrée.');
    }

    /** Grants a plan by hand, once payment has been collected off-platform. */
    public function grant(Request $request, Agency $agency): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'ends_at' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:1000'],
            'request_id' => ['nullable', 'integer', 'exists:plan_change_requests,id'],
        ], [
            'ends_at.after' => 'Une échéance déjà passée rétrograderait l’agence dès cette nuit.',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $this->subscriptions->grant(
            $agency,
            $plan,
            $data['ends_at'] ?? null,
            $data['note'] ?? null
        );

        if (! empty($data['request_id'])) {
            $this->closeRequest(
                PlanChangeRequest::findOrFail($data['request_id']),
                PlanChangeRequest::STATUS_ACCEPTED,
                $data['note'] ?? 'Formule attribuée.'
            );
        }

        $ends = isset($data['ends_at'])
            ? ' jusqu’au '.Carbon::parse($data['ends_at'])->format('d/m/Y')
            : ' sans échéance';

        return back()->with('success',
            "« {$agency->commercial_name} » est sur la formule {$plan->name}{$ends}.");
    }

    public function refuseRequest(Request $request, PlanChangeRequest $planChangeRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_response' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'admin_response.required' => 'Expliquez le refus : l’agence a demandé et attend une réponse.',
        ]);

        $this->closeRequest($planChangeRequest, PlanChangeRequest::STATUS_REFUSED, $data['admin_response']);

        return back()->with('success', 'Demande refusée : l’agence est prévenue.');
    }

    private function closeRequest(PlanChangeRequest $request, string $status, string $response): void
    {
        $request->update([
            'status' => $status,
            'admin_response' => $response,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        $this->audit->log('plan_change.'.$status, $request->agency, [], [
            'plan' => $request->requestedPlan?->slug,
            'response' => $response,
        ]);

        $request->agency?->user?->notify(new PlanChangeHandled($request->fresh()));
    }

    /** @return \Illuminate\Support\Collection<int, Agency> */
    private function agenciesOn(Plan $plan)
    {
        return Agency::whereHas('subscriptions', fn ($q) => $q
            ->where('plan_id', $plan->id)
            ->where('status', Subscription::STATUS_ACTIVE))
            ->with('user')
            ->get();
    }
}
