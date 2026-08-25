<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Availability of a vehicle over a date range (specification 6.1).
 *
 * A vehicle is free on [start, end] when nothing overlaps it: no booking that
 * still holds its dates, and no manual block. Two ranges overlap when
 * `A.start <= B.end AND A.end >= B.start` — the closed-interval test, because
 * a rental that ends the day another begins is a same-day handover, not a free
 * slot.
 *
 * The agency's `buffer_hours` widen every existing occupation on both sides:
 * the car has to be cleaned and checked between two clients.
 */
class AvailabilityService
{
    /**
     * Statuses that stand in the way of a confirmation.
     *
     * Deliberately narrower than BLOCKING_STATUSES: a second request pending
     * on the same days must not stop the agency from accepting the first. Two
     * clients may queue for a slot; only one of them can be given it.
     */
    public const COMMITTED_STATUSES = [
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_IN_PROGRESS,
    ];

    /** @param  array<int, string>|null  $statuses */
    public function isAvailable(
        Vehicle $vehicle,
        string $start,
        string $end,
        ?int $ignoreBookingId = null,
        ?array $statuses = null,
    ): bool {
        [$start, $end] = $this->normalise($start, $end);

        return ! $this->bookingsQuery($vehicle, $start, $end, $ignoreBookingId, $statuses)->exists()
            && ! $this->blocksQuery($vehicle, $start, $end)->exists();
    }

    /**
     * Same question, asked inside a transaction with the rows locked
     * (specification 6.3). Two clients confirming the same slot at the same
     * instant must not both be told yes: the second waits for the first to
     * commit, then reads what it wrote.
     */
    /** @param  array<int, string>|null  $statuses */
    public function isAvailableForUpdate(
        Vehicle $vehicle,
        string $start,
        string $end,
        ?int $ignoreBookingId = null,
        ?array $statuses = null,
    ): bool {
        [$start, $end] = $this->normalise($start, $end);

        $taken = $this->bookingsQuery($vehicle, $start, $end, $ignoreBookingId, $statuses)
            ->lockForUpdate()
            ->exists();

        return ! $taken && ! $this->blocksQuery($vehicle, $start, $end)->lockForUpdate()->exists();
    }

    /**
     * The dates the calendar greys out, as `Y-m-d` strings.
     *
     * Returned as a flat list rather than as ranges: the browser only has to
     * test membership, and a month of unavailability is 30 short strings.
     *
     * @return array<int, string>
     */
    public function unavailableDates(Vehicle $vehicle, ?string $from = null, ?string $to = null): array
    {
        $from = Carbon::parse($from ?? 'today')->startOfDay();
        $to = Carbon::parse($to ?? $from->copy()->addMonths(6))->endOfDay();

        $ranges = $vehicle->bookings()
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get(['start_date', 'end_date'])
            ->concat(
                $vehicle->availabilityBlocks()
                    ->where('start_date', '<=', $to->toDateString())
                    ->where('end_date', '>=', $from->toDateString())
                    ->get(['start_date', 'end_date'])
            );

        $buffer = $this->bufferDays($vehicle);
        $dates = [];

        foreach ($ranges as $range) {
            $start = Carbon::parse($range->start_date)->subDays($buffer)->max($from);
            $end = Carbon::parse($range->end_date)->addDays($buffer)->min($to);

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $dates[$day->toDateString()] = true;
            }
        }

        $keys = array_keys($dates);
        sort($keys);

        return $keys;
    }

    /**
     * Why a range was refused, in words the client can act on. Returning only
     * false would leave them clicking through the calendar to find out.
     */
    public function reasonUnavailable(Vehicle $vehicle, string $start, string $end): ?string
    {
        [$start, $end] = $this->normalise($start, $end);

        if (Carbon::parse($start)->startOfDay()->isBefore(Carbon::today())) {
            return 'La date de départ est déjà passée.';
        }

        if ($this->bookingsQuery($vehicle, $start, $end)->exists()) {
            return 'Ce véhicule est déjà réservé sur une partie de ces dates.';
        }

        if ($this->blocksQuery($vehicle, $start, $end)->exists()) {
            return 'L’agence a rendu ce véhicule indisponible sur une partie de ces dates.';
        }

        return null;
    }

    /**
     * The buffer is expressed in hours but availability is counted in whole
     * days: four hours between two rentals still costs the calendar a day,
     * since the car cannot be handed over twice on the same date.
     */
    private function bufferDays(Vehicle $vehicle): int
    {
        $hours = (int) ($vehicle->agency?->buffer_hours ?? 0);

        return $hours > 0 ? (int) ceil($hours / 24) : 0;
    }

    /** @param  array<int, string>|null  $statuses */
    private function bookingsQuery(
        Vehicle $vehicle,
        string $start,
        string $end,
        ?int $ignore = null,
        ?array $statuses = null,
    ): HasMany {
        $buffer = $this->bufferDays($vehicle);

        return $vehicle->bookings()
            ->whereIn('status', $statuses ?? Booking::BLOCKING_STATUSES)
            ->when($ignore, fn ($q, $id) => $q->whereKeyNot($id))
            // The buffer widens the requested range rather than each stored
            // booking: one comparison instead of one per row, same result.
            ->overlapping(
                Carbon::parse($start)->subDays($buffer)->toDateString(),
                Carbon::parse($end)->addDays($buffer)->toDateString()
            );
    }

    private function blocksQuery(Vehicle $vehicle, string $start, string $end): HasMany
    {
        return $vehicle->availabilityBlocks()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }

    /** @return array{0: string, 1: string} */
    private function normalise(string $start, string $end): array
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();

        // A reversed range is a typo, not an empty one: swapping it is kinder
        // than answering "available" for a period nobody asked about.
        return $from->lessThanOrEqualTo($to)
            ? [$from->toDateString(), $to->toDateString()]
            : [$to->toDateString(), $from->toDateString()];
    }
}
