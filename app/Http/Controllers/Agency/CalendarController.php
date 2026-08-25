<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agency calendar (specification 8): every vehicle on one grid, with the
 * bookings that hold it and the blocks the agency added by hand.
 *
 * Manual blocks are what let an agency rent off-platform, or send a car to the
 * garage, without the site continuing to offer it.
 */
class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $agency = $request->user()->activeAgency();

        $month = Carbon::parse($request->input('month', 'first day of this month'))->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $vehicles = $agency->vehicles()
            ->whereIn('status', [Vehicle::STATUS_PUBLISHED, Vehicle::STATUS_PENDING])
            ->orderBy('brand')
            ->get(['id', 'brand', 'model', 'year']);

        $ids = $vehicles->pluck('id');

        $bookings = Booking::whereIn('vehicle_id', $ids)
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get(['id', 'vehicle_id', 'booking_reference', 'client_name', 'status', 'start_date', 'end_date']);

        $blocks = AvailabilityBlock::whereIn('vehicle_id', $ids)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get(['id', 'vehicle_id', 'reason', 'note', 'start_date', 'end_date']);

        return Inertia::render('Agency/Calendar', [
            'month' => [
                'value' => $month->format('Y-m'),
                'label' => $month->translatedFormat('F Y'),
                'days' => $month->daysInMonth,
                'previous' => $month->copy()->subMonth()->format('Y-m'),
                'next' => $month->copy()->addMonth()->format('Y-m'),
            ],
            'vehicles' => $vehicles->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'title' => $v->title(),
            ]),
            'bookings' => $bookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'vehicle_id' => $b->vehicle_id,
                'reference' => $b->booking_reference,
                'client_name' => $b->client_name,
                'status' => $b->status,
                'start_date' => $b->start_date->toDateString(),
                'end_date' => $b->end_date->toDateString(),
            ]),
            'blocks' => $blocks->map(fn (AvailabilityBlock $b) => [
                'id' => $b->id,
                'vehicle_id' => $b->vehicle_id,
                'reason' => $b->reason,
                'note' => $b->note,
                'start_date' => $b->start_date->toDateString(),
                'end_date' => $b->end_date->toDateString(),
            ]),
            'reasons' => [
                AvailabilityBlock::REASON_MAINTENANCE => 'Maintenance',
                AvailabilityBlock::REASON_OFF_PLATFORM => 'Loué hors plateforme',
                AvailabilityBlock::REASON_UNAVAILABLE => 'Indisponible',
            ],
            'canWrite' => ! $agency->isSuspended(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agency = $request->user()->activeAgency();

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('agency_id', $agency->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', Rule::in([
                AvailabilityBlock::REASON_MAINTENANCE,
                AvailabilityBlock::REASON_OFF_PLATFORM,
                AvailabilityBlock::REASON_UNAVAILABLE,
            ])],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'vehicle_id.exists' => 'Ce véhicule n’appartient pas à votre agence.',
        ]);

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        // A block laid over a confirmed booking would hide a rental the agency
        // has already promised. Refusing here is the honest answer: cancel the
        // booking first, with a motive the client receives.
        $conflict = $vehicle->bookings()
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->overlapping($data['start_date'], $data['end_date'])
            ->first();

        if ($conflict) {
            return back()->withErrors([
                'block' => "Une réservation ({$conflict->booking_reference}) occupe déjà ces dates. "
                    .'Annulez-la d’abord si le véhicule doit être retiré.',
            ]);
        }

        AvailabilityBlock::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Période bloquée : le véhicule n’apparaît plus sur ces dates.');
    }

    public function destroy(Request $request, AvailabilityBlock $block): RedirectResponse
    {
        $agency = $request->user()->activeAgency();

        abort_unless($block->vehicle?->agency_id === $agency->id, 404);

        $block->delete();

        return back()->with('success', 'Blocage levé : le véhicule est de nouveau proposé.');
    }
}
