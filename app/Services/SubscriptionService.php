<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\SubscriptionDowngraded;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * All subscription changes funnel through this service. Specification 4.3 asks
 * that a payment module (Chargily Pay, SATIM) can be plugged in later without a
 * rewrite: the day it arrives, it calls grant() after the payment succeeds and
 * nothing else in the application needs to know.
 */
class SubscriptionService
{
    /** Days before expiry at which the agency is warned (specification 4.3). */
    public const WARNING_DAYS = 7;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ListingQuotaService $quotas,
    ) {
    }

    /**
     * Puts an agency on a plan, closing whatever ran before. A NULL end date
     * means no expiry — used for the Silver plan granted on approval.
     */
    public function grant(Agency $agency, Plan $plan, ?string $endsAt = null, ?string $note = null): Subscription
    {
        return DB::transaction(function () use ($agency, $plan, $endsAt, $note) {
            $previous = $agency->activeSubscription()->first();

            $agency->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_CANCELLED]);

            $subscription = $agency->subscriptions()->create([
                'plan_id' => $plan->id,
                'starts_at' => now()->toDateString(),
                'ends_at' => $endsAt,
                'status' => Subscription::STATUS_ACTIVE,
                'admin_note' => $note,
                'created_by' => Auth::id(),
            ]);

            $this->audit->log('subscription.granted', $agency, [
                'plan' => $previous?->plan?->slug,
                'ends_at' => $previous?->ends_at?->toDateString(),
            ], [
                'plan' => $plan->slug,
                'ends_at' => $endsAt,
                'note' => $note,
            ]);

            // A plan that lowers the allowance must not leave the agency over
            // quota in silence — see archiveExcessListings().
            $archived = $this->archiveExcessListings($agency->refresh());

            if ($archived->isNotEmpty()) {
                $agency->user->notify(new SubscriptionDowngraded($agency, $plan, $archived));
            }

            return $subscription;
        });
    }

    /** The free plan every approved agency starts on. */
    public function grantDefaultPlan(Agency $agency): Subscription
    {
        return $this->grant($agency, Plan::where('slug', Plan::SILVER)->firstOrFail());
    }

    /**
     * Expired subscriptions fall back to Silver (specification 4.3).
     *
     * The fallback is granted rather than merely flagged: every quota check
     * asks the plan a question and must get an answer, and an agency without
     * any active subscription would be a hole in that rule.
     *
     * @return int  agencies downgraded
     */
    public function downgradeExpired(): int
    {
        $silver = Plan::where('slug', Plan::SILVER)->firstOrFail();
        $count = 0;

        Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->where('plan_id', '!=', $silver->id)
            ->with('agency.user')
            ->chunkById(50, function (Collection $subscriptions) use ($silver, &$count) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->agency) {
                        continue;
                    }

                    $subscription->update(['status' => Subscription::STATUS_EXPIRED]);

                    // grant() closes the old row, opens the Silver one, writes
                    // the audit trail and archives what no longer fits.
                    $this->grant(
                        $subscription->agency,
                        $silver,
                        null,
                        'Rétrogradation automatique : abonnement expiré le '
                            .$subscription->ends_at->format('d/m/Y').'.'
                    );

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Warns agencies whose plan expires within the week. `expiry_notified_at`
     * makes the command idempotent: run hourly, the agency is told once.
     *
     * @return int  agencies warned
     */
    public function warnExpiring(): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->whereNull('expiry_notified_at')
            ->whereDate('ends_at', '>=', now()->toDateString())
            ->whereDate('ends_at', '<=', now()->addDays(self::WARNING_DAYS)->toDateString())
            ->with(['agency.user', 'plan'])
            ->chunkById(50, function (Collection $subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->agency?->user) {
                        continue;
                    }

                    $subscription->agency->user->notify(new SubscriptionExpiring($subscription));

                    // Le §10 adresse cette alerte à l'agence *et* à l'admin :
                    // sans encaissement en ligne, c'est lui qui relance.
                    Notification::send(
                        User::where('role', User::ROLE_ADMIN)->get(),
                        new SubscriptionExpiring($subscription, forAdmin: true)
                    );

                    $subscription->update(['expiry_notified_at' => now()]);

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Brings an agency back within its listing quota, oldest listings first.
     *
     * Specification 4.3 is explicit: **nothing is deleted**. Excess listings
     * are archived, which hides them and frees the slot while keeping the
     * photos, the pricing and the history. The agency is told exactly which
     * ones, so it can choose differently and republish.
     *
     * @return Collection<int, Vehicle>
     */
    public function archiveExcessListings(Agency $agency): Collection
    {
        $max = $this->quotas->maxListings($agency);

        if ($max === null) {
            return collect();
        }

        $excess = $this->quotas->usedListings($agency) - $max;

        if ($excess <= 0) {
            return collect();
        }

        $victims = $agency->vehicles()
            ->whereIn('status', ListingQuotaService::OCCUPYING_STATUSES)
            // Oldest first: the agency's newest work is what it cares about,
            // and picking at random would be worse than picking badly.
            ->oldest('created_at')
            ->limit($excess)
            ->get();

        foreach ($victims as $vehicle) {
            $vehicle->update(['status' => Vehicle::STATUS_ARCHIVED]);
        }

        if ($victims->isNotEmpty()) {
            $this->audit->log('subscription.listings_archived', $agency, [], [
                'count' => $victims->count(),
                'vehicles' => $victims->pluck('id')->all(),
            ]);
        }

        return $victims;
    }
}
