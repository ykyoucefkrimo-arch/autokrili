<?php

namespace App\Console\Commands;

use App\Services\ReviewService;
use Illuminate\Console\Command;

/**
 * The review invitation of specification 6.2, sent 24 h after the return.
 *
 * Delayed on purpose: asked at the counter, a client answers to be polite;
 * asked the next day, they answer what they think.
 */
class SendReviewInvitations extends Command
{
    protected $signature = 'reviews:invite';

    protected $description = 'Invite les clients à laisser un avis 24 h après le retour du véhicule';

    public function handle(ReviewService $reviews): int
    {
        $count = $reviews->sendInvitations();

        $this->info($count === 0
            ? 'Aucune invitation à envoyer.'
            : "{$count} invitation(s) envoyée(s).");

        return self::SUCCESS;
    }
}
