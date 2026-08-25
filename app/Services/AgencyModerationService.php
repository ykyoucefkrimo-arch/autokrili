<?php

namespace App\Services;

use App\Models\Agency;
use App\Notifications\AgencyApproved;
use App\Notifications\AgencyRejected;
use App\Notifications\AgencySuspended;
use Illuminate\Support\Facades\DB;

/**
 * The moderation decisions of specification 3.2. Each one does three things
 * together — change the status, write the audit entry, tell the agency — and
 * doing them in one place is what keeps them from drifting apart.
 */
class AgencyModerationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    public function approve(Agency $agency): Agency
    {
        return DB::transaction(function () use ($agency) {
            $before = $agency->getAttributes();

            $agency->update([
                'status' => Agency::STATUS_APPROVED,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            // An approved agency must always have a plan: every quota check
            // asks the plan a question and needs an answer.
            if (! $agency->activeSubscription()->exists()) {
                $this->subscriptions->grantDefaultPlan($agency);
            }

            $this->audit->logChange('agency.approved', $agency, $before);
            $agency->user->notify(new AgencyApproved($agency));

            return $agency->refresh();
        });
    }

    public function reject(Agency $agency, string $reason): Agency
    {
        return DB::transaction(function () use ($agency, $reason) {
            $before = $agency->getAttributes();

            $agency->update([
                'status' => Agency::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            $this->audit->logChange('agency.rejected', $agency, $before);
            $agency->user->notify(new AgencyRejected($agency, $reason));

            return $agency->refresh();
        });
    }

    /**
     * Suspension hides the listings without touching them: the agency keeps
     * read access to its dashboard, and a reinstatement restores everything.
     */
    public function suspend(Agency $agency, string $reason): Agency
    {
        return DB::transaction(function () use ($agency, $reason) {
            $before = $agency->getAttributes();

            $agency->update([
                'status' => Agency::STATUS_SUSPENDED,
                'rejection_reason' => $reason,
            ]);

            $this->audit->logChange('agency.suspended', $agency, $before);
            $agency->user->notify(new AgencySuspended($agency, $reason));

            return $agency->refresh();
        });
    }

    public function reinstate(Agency $agency): Agency
    {
        return DB::transaction(function () use ($agency) {
            $before = $agency->getAttributes();

            $agency->update([
                'status' => Agency::STATUS_APPROVED,
                'rejection_reason' => null,
                'approved_at' => $agency->approved_at ?? now(),
            ]);

            if (! $agency->activeSubscription()->exists()) {
                $this->subscriptions->grantDefaultPlan($agency);
            }

            $this->audit->logChange('agency.reinstated', $agency, $before);
            $agency->user->notify(new AgencyApproved($agency));

            return $agency->refresh();
        });
    }

    public function setTrusted(Agency $agency, bool $trusted): Agency
    {
        $before = $agency->getAttributes();
        $agency->update(['is_trusted' => $trusted]);
        $this->audit->logChange('agency.trust_changed', $agency, $before);

        return $agency->refresh();
    }
}
