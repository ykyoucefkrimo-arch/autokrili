<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The agency's bookings (specification 8): the queue to answer, the details
 * with the client's contact, and the four buttons that move a rental along.
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    public function index(Request $request): Response
    {
        $agency = $request->user()->activeAgency();
        $status = $request->input('status');

        $bookings = $agency->bookings()
            ->with(['vehicle:id,brand,model,year'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->input('search'), fn ($q, $search) => $q->where(
                fn ($sub) => $sub->where('client_name', 'like', "%{$search}%")
                    ->orWhere('booking_reference', 'like', "%{$search}%")
                    ->orWhere('client_phone', 'like', "%{$search}%")
            ))
            // Pending first, oldest of them at the top: the agency has 24 h to
            // answer, and the one closest to expiring is the urgent one.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'pending' THEN expires_at END")
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Booking $b) => $this->row($b));

        return Inertia::render('Agency/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => ['status' => $status, 'search' => $request->input('search')],
            'counts' => $agency->bookings()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'canWrite' => ! $agency->isSuspended(),
        ]);
    }

    public function show(Booking $booking): Response
    {
        $this->authorize('view', $booking);
        $booking->load(['vehicle.coverPhoto', 'client:id,name,email']);

        return Inertia::render('Agency/Bookings/Show', [
            'booking' => $this->detail($booking),
            'canWrite' => ! $booking->agency->isSuspended(),
        ]);
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        return $this->transition($booking, fn () => $this->bookings->confirm($booking),
            'Réservation confirmée : le client est prévenu par email.');
    }

    public function refuse(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Le motif est obligatoire.',
            'reason.min' => 'Le motif doit être assez explicite pour que le client sache quoi faire.',
        ]);

        return $this->transition($booking, fn () => $this->bookings->refuse($booking, $data['reason']),
            'Demande refusée : les dates sont de nouveau libres.');
    }

    public function start(Booking $booking): RedirectResponse
    {
        return $this->transition($booking, fn () => $this->bookings->start($booking),
            'Départ enregistré.');
    }

    public function complete(Booking $booking): RedirectResponse
    {
        return $this->transition($booking, fn () => $this->bookings->complete($booking),
            'Retour enregistré : le véhicule est de nouveau disponible.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], ['reason.required' => 'Le motif est obligatoire.']);

        return $this->transition($booking, fn () => $this->bookings->cancel($booking, $data['reason'], 'agency'),
            'Réservation annulée : le client est prévenu.');
    }

    /**
     * The bookings as a CSV, for the accounting the agency keeps outside the
     * platform. Streamed rather than built in memory: a busy agency's year is
     * thousands of rows.
     */
    public function export(Request $request): StreamedResponse
    {
        $agency = $request->user()->activeAgency();
        $filename = 'reservations-'.$agency->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($agency) {
            $out = fopen('php://output', 'w');
            // BOM: without it Excel in French Windows reads the accents wrong.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Référence', 'Statut', 'Véhicule', 'Client', 'Téléphone',
                'Départ', 'Retour', 'Jours', 'Total DA', 'Caution DA'], ';');

            $agency->bookings()->with('vehicle')->chunk(200, function ($bookings) use ($out) {
                foreach ($bookings as $b) {
                    fputcsv($out, [
                        $b->booking_reference, $b->status, $b->vehicle?->title(),
                        $b->client_name, $b->client_phone,
                        $b->start_date->format('d/m/Y'), $b->end_date->format('d/m/Y'),
                        $b->total_days, $b->total_price_dzd, $b->deposit_dzd,
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Every transition is guarded by the same policy and turns a refused move
     * into a message rather than a stack trace: the agency clicking "Départ"
     * on an already-started rental has made a mistake, not caused an error.
     */
    private function transition(Booking $booking, callable $action, string $success): RedirectResponse
    {
        $this->authorize('manage', $booking);

        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }

    private function row(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->booking_reference,
            'status' => $booking->status,
            'vehicle' => $booking->vehicle?->title(),
            'client_name' => $booking->client_name,
            'client_phone' => $booking->client_phone,
            'start_date' => $booking->start_date->translatedFormat('d M Y'),
            'end_date' => $booking->end_date->translatedFormat('d M Y'),
            'total_days' => $booking->total_days,
            'total_price_dzd' => $booking->total_price_dzd,
            'expires_in_hours' => $booking->status === Booking::STATUS_PENDING && $booking->expires_at
                ? max(0, (int) now()->diffInHours($booking->expires_at, false))
                : null,
        ];
    }

    private function detail(Booking $booking): array
    {
        return $this->row($booking) + [
            'client_email' => $booking->client_email,
            'driver_license_number' => $booking->driver_license_number,
            'client_note' => $booking->client_note,
            'with_driver' => $booking->with_driver,
            'pickup_location' => $booking->pickup_location,
            'dropoff_location' => $booking->dropoff_location,
            'vehicle_price_dzd' => $booking->vehicle_price_dzd,
            'driver_price_dzd' => $booking->driver_price_dzd,
            'deposit_dzd' => $booking->deposit_dzd,
            'price_breakdown' => $booking->price_breakdown ?? [],
            'cancellation_reason' => $booking->cancellation_reason,
            'cancelled_by' => $booking->cancelled_by,
            'created_at' => $booking->created_at->translatedFormat('d F Y à H:i'),
            'confirmed_at' => $booking->confirmed_at?->translatedFormat('d F Y à H:i'),
            'cover_url' => $booking->vehicle?->coverPhoto
                ? Storage::disk('public')->url($booking->vehicle->coverPhoto->path_card ?? $booking->vehicle->coverPhoto->path)
                : null,
        ];
    }
}
