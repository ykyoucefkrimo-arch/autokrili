<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\ListingStat;
use App\Models\Vehicle;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily aggregates per listing (specification 5, `listing_stats`) and the
 * figures the agency dashboard draws from them (§8).
 *
 * Counters are incremented on one row per listing per day rather than logging
 * every hit: an agency wants to know that a car was seen forty times last
 * Tuesday, not who saw it. It is also the only shape that stays cheap when the
 * catalogue grows.
 */
class StatsService
{
    /** What each plan level unlocks (§4.1). Ordered from least to most. */
    public const LEVELS = ['basic', 'advanced', 'premium'];

    public function recordView(Vehicle $vehicle): void
    {
        $this->increment($vehicle, 'views');
    }

    /** A click on the phone or WhatsApp button: the strongest public signal. */
    public function recordContactClick(Vehicle $vehicle): void
    {
        $this->increment($vehicle, 'contact_clicks');
    }

    public function recordBooking(Booking $booking): void
    {
        if ($booking->vehicle) {
            $this->increment($booking->vehicle, 'bookings_count');
        }
    }

    /**
     * One row per listing per day, created on first sight.
     *
     * `updateOrInsert` plus a raw increment rather than read-modify-write: two
     * visitors landing on the same listing in the same second must not lose a
     * view between them.
     */
    private function increment(Vehicle $vehicle, string $column): void
    {
        $date = now()->toDateString();

        $affected = DB::table('listing_stats')
            ->where('vehicle_id', $vehicle->id)
            ->where('date', $date)
            ->increment($column);

        if ($affected === 0) {
            // insertOrIgnore: two first-time visitors racing here would both
            // find no row, and the unique index is what settles it.
            DB::table('listing_stats')->insertOrIgnore([
                'vehicle_id' => $vehicle->id,
                'agency_id' => $vehicle->agency_id,
                'date' => $date,
                $column => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The ignored insert means the row appeared meanwhile: count now.
            DB::table('listing_stats')
                ->where('vehicle_id', $vehicle->id)
                ->where('date', $date)
                ->where($column, 0)
                ->increment($column);
        }
    }

    /**
     * Everything the agency dashboard shows, filtered by what the plan allows.
     *
     * @return array<string, mixed>
     */
    public function forAgency(Agency $agency, int $days = 30): array
    {
        $level = $agency->currentPlan()?->stats_level ?? 'basic';
        $from = now()->subDays($days - 1)->startOfDay();

        $data = [
            'level' => $level,
            'days' => $days,
            'totals' => $this->totals($agency, $from),
            'daily' => $this->daily($agency, $from, $days),
            'topVehicles' => $this->topVehicles($agency, $from),
        ];

        // Les blocs verrouillés sont calculés quand même : la page les floute
        // et propose la montée en gamme (§8). Envoyer des chiffres vides
        // derrière le flou donnerait un aperçu mensonger.
        $data['conversion'] = $this->conversion($agency, $from);
        $data['weekdays'] = $this->weekdays($agency, $from);
        $data['wilayaComparison'] = $level === 'premium'
            ? $this->wilayaComparison($agency, $from)
            : null;

        return $data;
    }

    /** @return array<string, int|float> */
    private function totals(Agency $agency, Carbon $from): array
    {
        $stats = ListingStat::where('agency_id', $agency->id)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('COALESCE(SUM(views),0) v, COALESCE(SUM(contact_clicks),0) c, COALESCE(SUM(bookings_count),0) b')
            ->first();

        $bookings = $agency->bookings()->where('created_at', '>=', $from);

        return [
            'views' => (int) $stats->v,
            'contact_clicks' => (int) $stats->c,
            'bookings' => (int) $bookings->clone()->count(),
            'confirmed' => (int) $bookings->clone()->whereIn('status', [
                Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED,
            ])->count(),
            'revenue' => (int) $agency->bookings()
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED])
                ->where('start_date', '>=', $from->toDateString())
                ->sum('total_price_dzd'),
        ];
    }

    /**
     * The 30-day curve, with the empty days filled in. A chart that skips the
     * days nobody looked would flatter the agency by hiding them.
     *
     * @return array<int, array{date: string, label: string, views: int, bookings: int}>
     */
    private function daily(Agency $agency, Carbon $from, int $days): array
    {
        $rows = ListingStat::where('agency_id', $agency->id)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('date, SUM(views) v, SUM(contact_clicks) c, SUM(bookings_count) b')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $series = [];

        foreach (CarbonPeriod::create($from, now()) as $day) {
            $key = $day->toDateString();
            $row = $rows->get($key);

            $series[] = [
                'date' => $key,
                'label' => $day->translatedFormat('d M'),
                'views' => (int) ($row->v ?? 0),
                'contacts' => (int) ($row->c ?? 0),
                'bookings' => (int) ($row->b ?? 0),
            ];
        }

        return $series;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function topVehicles(Agency $agency, Carbon $from): Collection
    {
        return ListingStat::where('listing_stats.agency_id', $agency->id)
            ->where('date', '>=', $from->toDateString())
            ->join('vehicles', 'vehicles.id', '=', 'listing_stats.vehicle_id')
            ->groupBy('vehicles.id', 'vehicles.brand', 'vehicles.model', 'vehicles.year')
            ->orderByDesc('v')
            ->limit(8)
            ->get([
                'vehicles.id',
                'vehicles.brand',
                'vehicles.model',
                'vehicles.year',
                DB::raw('SUM(listing_stats.views) v'),
                DB::raw('SUM(listing_stats.bookings_count) b'),
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => "{$row->brand} {$row->model} {$row->year}",
                'views' => (int) $row->v,
                'bookings' => (int) $row->b,
                'rate' => $row->v > 0 ? round($row->b / $row->v * 100, 1) : 0.0,
            ]);
    }

    /** @return array<string, float|int> */
    private function conversion(Agency $agency, Carbon $from): array
    {
        $totals = $this->totals($agency, $from);

        return [
            'view_to_booking' => $totals['views'] > 0
                ? round($totals['bookings'] / $totals['views'] * 100, 1)
                : 0.0,
            'view_to_contact' => $totals['views'] > 0
                ? round($totals['contact_clicks'] / $totals['views'] * 100, 1)
                : 0.0,
            'acceptance' => $totals['bookings'] > 0
                ? round($totals['confirmed'] / $totals['bookings'] * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Which weekdays bring the demand — the "périodes de forte demande" of §8.
     *
     * @return array<int, array{label: string, bookings: int}>
     */
    private function weekdays(Agency $agency, Carbon $from): array
    {
        $bookings = $agency->bookings()
            ->where('created_at', '>=', $from)
            ->get(['created_at'])
            ->groupBy(fn (Booking $b) => (int) $b->created_at->dayOfWeek)
            ->map->count();

        // Semaine algérienne : elle commence le dimanche.
        $order = [0, 1, 2, 3, 4, 5, 6];
        $labels = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        return array_map(fn ($day) => [
            'label' => $labels[$day],
            'bookings' => (int) ($bookings[$day] ?? 0),
        ], $order);
    }

    /**
     * The agency against the average of its wilaya (§4.1, Platinium only).
     *
     * Compared per listing, not per agency: an agency with forty cars would
     * otherwise always look ahead of one with four, which says nothing about
     * how well either is doing.
     *
     * @return array<string, float|int>
     */
    private function wilayaComparison(Agency $agency, Carbon $from): array
    {
        $peers = Agency::where('wilaya_id', $agency->wilaya_id)
            ->where('status', Agency::STATUS_APPROVED)
            ->pluck('id');

        $row = ListingStat::whereIn('agency_id', $peers)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('agency_id, SUM(views) v, COUNT(DISTINCT vehicle_id) n')
            ->groupBy('agency_id')
            ->get();

        $perListing = $row
            ->filter(fn ($r) => $r->n > 0)
            ->map(fn ($r) => $r->v / $r->n);

        $mine = $row->firstWhere('agency_id', $agency->id);

        return [
            'agencies' => $peers->count(),
            'my_views_per_listing' => $mine && $mine->n > 0 ? round($mine->v / $mine->n, 1) : 0.0,
            'average_views_per_listing' => $perListing->isNotEmpty()
                ? round($perListing->avg(), 1)
                : 0.0,
        ];
    }
}
