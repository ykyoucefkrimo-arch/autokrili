<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The agency's reviews (§8): read them all, answer publicly if the plan allows
 * it (§4.1).
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): Response
    {
        $agency = $request->user()->activeAgency();
        $plan = $agency->currentPlan();

        $reviews = $agency->reviews()
            ->with(['booking:id,booking_reference,start_date,end_date,vehicle_id', 'booking.vehicle:id,brand,model,year'])
            // Les avis publiés d'abord : ce sont ceux que le public voit.
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(15)
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status,
                'agency_reply' => $review->agency_reply,
                'replied_at' => $review->replied_at?->translatedFormat('d M Y'),
                'vehicle' => $review->booking?->vehicle
                    ? $review->booking->vehicle->title()
                    : 'Véhicule retiré du catalogue',
                'reference' => $review->booking?->booking_reference,
                'created_at' => $review->created_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('Agency/Reviews', [
            'reviews' => $reviews,
            'summary' => [
                'average' => (float) $agency->average_rating,
                'count' => $agency->reviews_count,
                'distribution' => $agency->reviews()
                    ->where('status', Review::STATUS_APPROVED)
                    ->selectRaw('rating, count(*) total')
                    ->groupBy('rating')
                    ->pluck('total', 'rating'),
                'pending' => $agency->reviews()->where('status', Review::STATUS_PENDING)->count(),
            ],
            'canReply' => (bool) $plan?->can_reply_reviews,
            'planName' => $plan?->name,
            'canWrite' => ! $agency->isSuspended(),
        ]);
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $agency = $request->user()->activeAgency();
        abort_unless($agency && $review->agency_id === $agency->id, 404);
        abort_if($agency->isSuspended(), 403);

        $data = $request->validate([
            'reply' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'reply.required' => 'Écrivez votre réponse : elle sera publique, sous l’avis.',
        ]);

        try {
            $this->reviews->reply($review, $data['reply']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reply' => $e->getMessage()]);
        }

        return back()->with('success', 'Réponse publiée sous l’avis.');
    }
}
