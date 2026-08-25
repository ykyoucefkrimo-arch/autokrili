<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * A booking is visible to its two sides and to nobody else: the client who
 * made it, and the agency that must honour it. It carries a phone number, an
 * address and a driving licence number.
 */
class BookingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->isClient($user, $booking) || $this->isAgency($user, $booking);
    }

    /** Accepting, refusing, handing over and taking back: the agency's side. */
    public function manage(User $user, Booking $booking): bool
    {
        return $this->isAgency($user, $booking) && ! $booking->agency->isSuspended();
    }

    /** Both sides may cancel, with a motive (specification 6.2). */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->isCancellable() && $this->view($user, $booking);
    }

    private function isClient(User $user, Booking $booking): bool
    {
        return $booking->client_id !== null && $booking->client_id === $user->id;
    }

    private function isAgency(User $user, Booking $booking): bool
    {
        $agency = $user->activeAgency();

        return $agency !== null && $agency->id === $booking->agency_id;
    }
}
