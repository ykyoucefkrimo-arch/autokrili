<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AvailabilityBlock;
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
     * Les vehicules occupes sur [start, end], pour filtrer une LISTE.
     *
     * Pose le 2026-10-18, quand l'accueil s'est mis a demander les dates : la
     * recherche ne doit plus proposer une voiture que la fiche refusera deux
     * ecrans plus loin.
     *
     * **Pourquoi deux temps plutot qu'une requete.** La marge de nettoyage
     * (`buffer_hours`) appartient a l'agence, pas au vehicule : l'appliquer en
     * SQL demanderait de l'arithmetique de dates differente sous MySQL et
     * sous SQLite, alors que les tests tournent sur l'un et la production sur
     * l'autre. On ratisse donc large avec la marge la PLUS grande, puis on
     * tranche en PHP avec la marge propre a chaque agence. Le resultat est
     * exact, et le volume reste celui des occupations qui touchent la periode
     * demandee — jamais le catalogue entier.
     *
     * @return array<int, int>
     */
    public function unavailableVehicleIds(string $start, string $end): array
    {
        [$start, $end] = $this->normalise($start, $end);

        $margeMax = (int) ceil(((int) Agency::max('buffer_hours')) / 24);
        $debut = Carbon::parse($start)->subDays($margeMax)->toDateString();
        $fin = Carbon::parse($end)->addDays($margeMax)->toDateString();

        $occupations = Booking::query()
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->overlapping($debut, $fin)
            ->get(['vehicle_id', 'agency_id', 'start_date', 'end_date'])
            ->concat(
                AvailabilityBlock::query()
                    ->where('start_date', '<=', $fin)
                    ->where('end_date', '>=', $debut)
                    ->get(['vehicle_id', 'start_date', 'end_date'])
            );

        // Un blocage manuel ne porte pas d'agence sur sa ligne : on passe par
        // le vehicule. Une passe pour tout le monde, pas une requete par ligne.
        $agenceDuVehicule = Vehicle::whereIn('id', $occupations->pluck('vehicle_id')->unique())
            ->pluck('agency_id', 'id');
        $marges = Agency::pluck('buffer_hours', 'id');

        $debutDemande = Carbon::parse($start);
        $finDemande = Carbon::parse($end);
        $occupes = [];

        foreach ($occupations as $occupation) {
            $agenceId = $occupation->agency_id ?? $agenceDuVehicule[$occupation->vehicle_id] ?? null;
            $heures = (int) ($marges[$agenceId] ?? 0);
            $marge = $heures > 0 ? (int) ceil($heures / 24) : 0;

            $depuis = Carbon::parse($occupation->start_date)->subDays($marge);
            $jusqua = Carbon::parse($occupation->end_date)->addDays($marge);

            // Le test des intervalles fermes, le meme qu'`isAvailable` : une
            // location qui finit le jour ou une autre commence est une remise
            // en main propre, pas un creneau libre.
            if ($depuis->lte($finDemande) && $jusqua->gte($debutDemande)) {
                $occupes[$occupation->vehicle_id] = true;
            }
        }

        return array_keys($occupes);
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
