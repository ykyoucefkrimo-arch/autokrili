<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * The scheduled half of specification 4.3: warn before expiry, downgrade after.
 *
 * Warning first, on purpose. A subscription that expires today must produce a
 * downgrade, not a warning that arrives too late to act on.
 */
class RefreshSubscriptions extends Command
{
    protected $signature = 'subscriptions:refresh';

    protected $description = 'Prévient les agences dont la formule expire, et rétrograde celles qui ont expiré';

    public function handle(SubscriptionService $subscriptions): int
    {
        $warned = $subscriptions->warnExpiring();
        $downgraded = $subscriptions->downgradeExpired();

        $this->info("{$warned} agence(s) prévenue(s) de l'échéance.");
        $this->info($downgraded === 0
            ? 'Aucune rétrogradation.'
            : "{$downgraded} agence(s) rétrogradée(s) en Silver.");

        return self::SUCCESS;
    }
}
