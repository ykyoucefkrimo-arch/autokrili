<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

/** Le rappel J-1 du §10, envoyé au client et à l'agence. */
class SendBookingReminders extends Command
{
    protected $signature = 'bookings:remind';

    protected $description = 'Rappelle aux deux parties les départs prévus le lendemain';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->sendReminders();

        $this->info($count === 0
            ? 'Aucun départ demain.'
            : "{$count} départ(s) rappelé(s) au client et à l'agence.");

        return self::SUCCESS;
    }
}
