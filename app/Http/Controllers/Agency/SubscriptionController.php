<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Notifications\PlanChangeRequested;
use App\Models\User;
use App\Services\ListingQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mon abonnement" (specification 8): the current plan, when it ends, what the
 * others would give, and a button to ask for a change.
 *
 * Asking is all an agency can do — there is no online payment in v1 (§4.3).
 * The administrator grants the plan once payment has been collected off the
 * platform.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly ListingQuotaService $quotas)
    {
    }

    public function show(Request $request): Response
    {
        $agency = $request->user()->activeAgency();
        $subscription = $agency->activeSubscription()->with('plan')->first();
        $current = $agency->currentPlan();

        return Inertia::render('Agency/Subscription', [
            'subscription' => $subscription ? [
                'plan' => $subscription->plan->only(['id', 'name', 'slug', 'badge_color']),
                'starts_at' => $subscription->starts_at->translatedFormat('d F Y'),
                'ends_at' => $subscription->ends_at?->translatedFormat('d F Y'),
                'days_left' => $subscription->ends_at
                    ? (int) now()->startOfDay()->diffInDays($subscription->ends_at, false)
                    : null,
                'admin_note' => $subscription->admin_note,
            ] : null,
            // La matrice vient de la base, jamais du PHP (§4.1) : l'administrateur
            // la modifie, cet écran suit sans redéploiement.
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'price_dzd' => $plan->price_dzd,
                    'max_listings' => $plan->max_listings,
                    'max_photos' => $plan->max_photos,
                    'max_users' => $plan->max_users,
                    'has_commune_priority' => $plan->has_commune_priority,
                    'has_wilaya_priority' => $plan->has_wilaya_priority,
                    'has_homepage_feature' => $plan->has_homepage_feature,
                    'can_reply_reviews' => $plan->can_reply_reviews,
                    'stats_level' => $plan->stats_level,
                    'badge_color' => $plan->badge_color,
                    'description' => $plan->description,
                    'is_current' => $current && $plan->id === $current->id,
                ]),
            'usage' => [
                'used_listings' => $this->quotas->usedListings($agency),
                'max_listings' => $this->quotas->maxListings($agency),
                'max_photos' => $this->quotas->maxPhotos($agency),
            ],
            'pendingRequest' => $agency->planChangeRequests()
                ->where('status', PlanChangeRequest::STATUS_PENDING)
                ->with('requestedPlan:id,name')
                ->latest()
                ->first()?->only(['id', 'agency_message', 'created_at']),
            'history' => $agency->planChangeRequests()
                ->with('requestedPlan:id,name')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (PlanChangeRequest $r) => [
                    'plan' => $r->requestedPlan?->name,
                    'status' => $r->status,
                    'admin_response' => $r->admin_response,
                    'created_at' => $r->created_at->translatedFormat('d M Y'),
                ]),
            'canWrite' => ! $agency->isSuspended(),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $agency = $request->user()->activeAgency();

        $data = $request->validate([
            'requested_plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'agency_message' => ['nullable', 'string', 'max:1000'],
        ]);

        // Une seule demande en cours : en empiler trois ne ferait pas répondre
        // l'administrateur plus vite, et brouillerait sa file.
        if ($agency->planChangeRequests()->where('status', PlanChangeRequest::STATUS_PENDING)->exists()) {
            return back()->withErrors([
                'requested_plan_id' => 'Une demande est déjà en cours de traitement.',
            ]);
        }

        $changeRequest = $agency->planChangeRequests()->create($data + [
            'status' => PlanChangeRequest::STATUS_PENDING,
        ]);

        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new PlanChangeRequested($changeRequest)
        );

        return back()->with('success',
            'Demande envoyée. Un administrateur vous recontactera pour le règlement.');
    }
}
