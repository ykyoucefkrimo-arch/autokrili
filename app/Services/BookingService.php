<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingRefused;
use App\Notifications\BookingReminder;
use App\Notifications\BookingRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The life of a booking (specification 6.2), from the request to the return of
 * the vehicle.
 *
 * Every transition does the same three things — move the status, stamp the
 * date, tell the other side — and doing them in one place is what keeps a
 * booking from ending up confirmed with nobody informed.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly StatsService $stats,
    ) {
    }

    /**
     * Creates the request. The vehicle is not held yet in the sense of being
     * confirmed, but a `pending` booking already blocks its dates: two clients
     * must not be able to queue for the same slot and both be told yes.
     *
     * @param  array<string, mixed>  $data
     */
    public function request(Vehicle $vehicle, array $data, ?User $client): Booking
    {
        return DB::transaction(function () use ($vehicle, $data, $client) {
            $start = Carbon::parse($data['start_date'])->toDateString();
            $end = Carbon::parse($data['end_date'])->toDateString();

            if (! $this->availability->isAvailableForUpdate($vehicle, $start, $end)) {
                throw new RuntimeException(
                    $this->availability->reasonUnavailable($vehicle, $start, $end)
                        ?? 'Ce véhicule n’est plus disponible sur ces dates.'
                );
            }

            // The client is created rather than demanded (§15): asking someone
            // to open an account before they know the car is free loses them.
            $client ??= $this->findOrCreateClient($data);

            $days = $this->countDays($start, $end);
            $withDriver = (bool) ($data['with_driver'] ?? false) && $vehicle->with_driver_available;
            $quote = $this->pricing->quote($vehicle, $days, $withDriver);

            $booking = Booking::create([
                'booking_reference' => $this->nextReference(),
                'vehicle_id' => $vehicle->id,
                'agency_id' => $vehicle->agency_id,
                'client_id' => $client?->id,

                'start_date' => $start,
                'end_date' => $end,
                'total_days' => $days,
                'pickup_location' => $data['pickup_location'] ?? $vehicle->pickupCommune?->name_fr,
                'dropoff_location' => $data['dropoff_location'] ?? $vehicle->pickupCommune?->name_fr,

                // Contact details are copied, not referenced: the client may
                // edit their profile later, the booking keeps what was agreed.
                'client_name' => $data['client_name'],
                'client_phone' => $data['client_phone'],
                'client_email' => $data['client_email'] ?? $client?->email,
                'driver_license_number' => $data['driver_license_number'] ?? null,
                'client_note' => $data['client_note'] ?? null,

                'with_driver' => $withDriver,
                'vehicle_price_dzd' => $quote['vehicle'],
                'driver_price_dzd' => $quote['driver'],
                'total_price_dzd' => $quote['total'],
                'deposit_dzd' => (int) $vehicle->agency->default_deposit_dzd,
                // Frozen: an agency that raises its rates tomorrow must not
                // change the price of a request made today.
                'price_breakdown' => $quote['lines'],

                'status' => Booking::STATUS_PENDING,
                // The agency has 24 h to answer (§6.2); past that a scheduled
                // command releases the dates.
                'expires_at' => now()->addDay(),
            ]);

            $this->stats->recordBooking($booking);
            $vehicle->agency->user->notify(new BookingRequested($booking));

            return $booking;
        });
    }

    /**
     * The agency accepts. Availability is checked a second time, under lock
     * (§6.3): between the request and this click, another booking may have
     * been confirmed on the same days.
     */
    public function confirm(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $this->assertStatus($booking, [Booking::STATUS_PENDING], 'accepter');

            // Only bookings the agency has already committed to stand in the
            // way. Another request pending on the same days is a competitor,
            // not an obstacle: accepting this one is precisely what settles it.
            $free = $booking->vehicle && $this->availability->isAvailableForUpdate(
                $booking->vehicle,
                $booking->start_date->toDateString(),
                $booking->end_date->toDateString(),
                $booking->id,
                AvailabilityService::COMMITTED_STATUSES,
            );

            if (! $free) {
                throw new RuntimeException(
                    'Ces dates viennent d’être prises par une autre réservation. Refusez celle-ci.'
                );
            }

            $booking->update([
                'status' => Booking::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            $booking->client?->notify(new BookingConfirmed($booking));

            return $booking->refresh();
        });
    }

    /** The agency refuses. The motive is mandatory and travels to the client. */
    public function refuse(Booking $booking, string $reason): Booking
    {
        $this->assertStatus($booking, [Booking::STATUS_PENDING], 'refuser');

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_by' => 'agency',
            'cancelled_at' => now(),
            'expires_at' => null,
        ]);

        $booking->client?->notify(new BookingRefused($booking, $reason));

        return $booking->refresh();
    }

    /** Vehicle handed over. */
    public function start(Booking $booking): Booking
    {
        $this->assertStatus($booking, [Booking::STATUS_CONFIRMED], 'marquer comme parti');

        $booking->update(['status' => Booking::STATUS_IN_PROGRESS, 'started_at' => now()]);

        return $booking->refresh();
    }

    /**
     * Vehicle returned. The dates stop blocking the calendar at this point,
     * which is what lets an agency re-rent a car brought back early.
     */
    public function complete(Booking $booking): Booking
    {
        $this->assertStatus($booking, [Booking::STATUS_IN_PROGRESS], 'marquer comme rendu');

        $booking->update(['status' => Booking::STATUS_COMPLETED, 'completed_at' => now()]);

        return $booking->refresh();
    }

    /**
     * Cancellation by either side. The motive is mandatory in both directions:
     * a client who cancels without a word is an agency that plans blind.
     */
    public function cancel(Booking $booking, string $reason, string $by): Booking
    {
        if (! $booking->isCancellable()) {
            throw new RuntimeException(
                'Une réservation en cours ou terminée ne s’annule pas : contactez l’autre partie.'
            );
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_by' => $by,
            'cancelled_at' => now(),
            'expires_at' => null,
        ]);

        $notifiable = $by === 'client' ? $booking->agency->user : $booking->client;
        $notifiable?->notify(new BookingCancelled($booking, $reason, $by));

        return $booking->refresh();
    }

    /**
     * Requests the agency never answered. Called by the scheduled command; the
     * dates go back to the catalogue rather than staying frozen forever.
     */
    public function expirePending(): int
    {
        $expired = 0;

        Booking::where('status', Booking::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['client', 'agency.user'])
            ->chunkById(100, function ($bookings) use (&$expired) {
                foreach ($bookings as $booking) {
                    $booking->update([
                        'status' => Booking::STATUS_EXPIRED,
                        'cancellation_reason' => 'Sans réponse de l’agence sous 24 heures.',
                        'cancelled_by' => 'system',
                        'cancelled_at' => now(),
                    ]);

                    $booking->client?->notify(new BookingCancelled(
                        $booking,
                        'L’agence n’a pas répondu dans les 24 heures. Vos dates sont de nouveau libres.',
                        'system'
                    ));

                    $expired++;
                }
            });

        return $expired;
    }

    /**
     * The day-before reminder of specification 10, to both sides.
     *
     * Sent for confirmed bookings only: reminding someone of a rental the
     * agency has not accepted would promise a car nobody has agreed to hand
     * over. `reminded_at` keeps the hourly command from repeating itself.
     *
     * @return int  bookings reminded
     */
    public function sendReminders(): int
    {
        $count = 0;

        Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminded_at')
            ->whereDate('start_date', now()->addDay()->toDateString())
            ->with(['client', 'agency.user', 'vehicle'])
            ->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $booking->client?->notify(new BookingReminder($booking));
                    $booking->agency?->user?->notify(new BookingReminder($booking, forAgency: true));
                    $booking->update(['reminded_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    /** Both bounds included: from Monday to Monday is one day, not zero. */
    public function countDays(string $start, string $end): int
    {
        return Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1;
    }

    /**
     * A client who books without an account gets one, with a random password
     * they set through the "forgotten password" flow. An address that already
     * exists is reused rather than refused: the booking is what matters, not
     * the account.
     *
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateClient(array $data): ?User
    {
        $email = $data['client_email'] ?? null;

        if (! $email) {
            // Booking by phone only stays possible: the details are copied on
            // the booking itself, and the agency calls back.
            return null;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $user = User::create([
            'name' => $data['client_name'],
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'phone' => $data['client_phone'],
            'role' => User::ROLE_CLIENT,
        ]);
        $user->syncRoles([User::ROLE_CLIENT]);

        return $user;
    }

    /**
     * `DZ-2026-00147`. The counter restarts every year and is read inside the
     * transaction that creates the booking, so two simultaneous requests
     * cannot claim the same number.
     */
    private function nextReference(): string
    {
        $year = now()->year;

        $last = Booking::where('booking_reference', 'like', "DZ-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('booking_reference')
            ->value('booking_reference');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('DZ-%d-%05d', $year, $next);
    }

    /** @param  array<int, string>  $allowed */
    private function assertStatus(Booking $booking, array $allowed, string $action): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            throw new RuntimeException(
                "Impossible d’{$action} une réservation dans l’état « {$booking->status} »."
            );
        }
    }
}
