<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * Releases the dates held by requests the agency never answered (§6.2).
 *
 * Without it a single unanswered request would freeze a vehicle's calendar
 * indefinitely, and the client would never learn that nothing was coming.
 */
class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Expire les demandes de réservation sans réponse depuis 24 heures';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->expirePending();

        $this->info($count === 0
            ? 'Aucune demande à expirer.'
            : "{$count} demande(s) expirée(s), dates libérées.");

        return self::SUCCESS;
    }
}
