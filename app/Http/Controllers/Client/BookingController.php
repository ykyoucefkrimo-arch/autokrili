<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\ReviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The client's own bookings (specification 7.1): what is coming, what is past,
 * and the voucher to show at the counter.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly ReviewService $reviews,
    ) {
    }

    public function index(Request $request): Response
    {
        $bookings = Booking::where('client_id', $request->user()->id)
            ->with(['vehicle.coverPhoto', 'agency:id,commercial_name,phone,whatsapp', 'review'])
            ->latest('start_date')
            ->get()
            ->map(fn (Booking $b) => $this->card($b));

        // Split rather than filtered in the page: "what is coming" is the
        // reason to open this screen, and it must not be buried under history.
        return Inertia::render('Client/Bookings', [
            'upcoming' => $bookings->filter(fn ($b) => $b['is_active'])->values(),
            'past' => $bookings->reject(fn ($b) => $b['is_active'])->values(),
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required' => 'Indiquez brièvement pourquoi vous annulez : l’agence planifie avec.',
        ]);

        try {
            $this->bookings->cancel($booking, $data['reason'], 'client');
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('success', 'Réservation annulée. L’agence est prévenue.');
    }

    /**
     * Depose l'avis. Reserve au client de la reservation, et seulement une
     * fois la location terminee : c'est ce qui distingue un avis d'une opinion.
     */
    public function review(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('view', $booking);
        abort_unless($booking->client_id === $request->user()->id, 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ], [
            'rating.required' => 'Donnez une note de 1 a 5.',
        ]);

        try {
            $this->reviews->create($booking, $data['rating'], $data['comment'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('success',
            'Merci : votre avis est relu avant publication.');
    }

    /**
     * The voucher the client shows at the counter (§6.2). Generated on demand
     * rather than stored: it holds nothing that is not already in the booking,
     * and a stale file would contradict a cancellation.
     */
    public function voucher(Booking $booking): HttpResponse
    {
        $this->authorize('view', $booking);

        abort_unless(
            in_array($booking->status, [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_COMPLETED,
            ], true),
            404,
            'Le bon n’existe que pour une réservation confirmée.'
        );

        $booking->load(['vehicle', 'agency.wilaya', 'agency.commune']);

        $pdf = Pdf::loadView('pdf.booking-voucher', [
            'booking' => $booking,
            'logo' => $booking->agency->logo_path
                ? Storage::disk('public')->path($booking->agency->logo_path)
                : null,
        ])->setPaper('a4');

        return $pdf->download("bon-{$booking->booking_reference}.pdf");
    }

    private function card(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->booking_reference,
            'status' => $booking->status,
            'vehicle' => $booking->vehicle?->title(),
            'vehicle_id' => $booking->vehicle_id,
            'vehicle_slug' => $booking->vehicle?->slug,
            'start_date' => $booking->start_date->translatedFormat('d M Y'),
            'end_date' => $booking->end_date->translatedFormat('d M Y'),
            'total_days' => $booking->total_days,
            'total_price_dzd' => $booking->total_price_dzd,
            'deposit_dzd' => $booking->deposit_dzd,
            'pickup_location' => $booking->pickup_location,
            'cancellation_reason' => $booking->cancellation_reason,
            'cancelled_by' => $booking->cancelled_by,
            'can_cancel' => $booking->isCancellable(),
            'can_review' => $booking->status === Booking::STATUS_COMPLETED
                && $booking->review === null,
            'review' => $booking->review ? [
                'rating' => $booking->review->rating,
                'status' => $booking->review->status,
                'agency_reply' => $booking->review->agency_reply,
            ] : null,
            'has_voucher' => in_array($booking->status, [
                Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED,
            ], true),
            'is_active' => in_array($booking->status, [
                Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS,
            ], true),
            'agency' => $booking->agency->only(['commercial_name', 'phone', 'whatsapp']),
            'cover_url' => $booking->vehicle?->coverPhoto
                ? Storage::disk('public')->url($booking->vehicle->coverPhoto->path_card ?? $booking->vehicle->coverPhoto->path)
                : null,
        ];
    }
}
