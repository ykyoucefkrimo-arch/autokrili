<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review moderation before publication (specification 9).
 *
 * A review carries someone's name and a public judgement on a business: it is
 * read before it is shown, not after a complaint.
 */
class ReviewController extends Controller
{
    /** Motifs de refus les plus courants, pour que deux administrateurs
     *  refusent la même chose de la même façon. */
    public const REASONS = [
        'insultes' => 'Propos insultants ou diffamatoires',
        'hors_sujet' => 'Hors sujet : ne parle pas de la location',
        'donnees' => 'Contient des données personnelles',
        'concurrence' => 'Avis manifestement déloyal ou concurrentiel',
    ];

    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->input('status', Review::STATUS_PENDING);

        $reviews = Review::query()
            ->with(['agency:id,commercial_name', 'client:id,name', 'booking:id,booking_reference'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->oldest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status,
                'rejection_reason' => $review->rejection_reason,
                'agency' => $review->agency?->commercial_name,
                'agency_id' => $review->agency_id,
                'client' => $review->client?->name ?? 'Compte supprimé',
                'reference' => $review->booking?->booking_reference,
                'created_at' => $review->created_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => ['status' => $status],
            'reasons' => self::REASONS,
            'counts' => ['pending' => Review::where('status', Review::STATUS_PENDING)->count()],
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $this->reviews->approve($review);

        return back()->with('success', 'Avis publié : la note de l’agence est recalculée.');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['reason.required' => 'Indiquez pourquoi cet avis est écarté : le journal d’audit le conserve.']);

        $this->reviews->reject($review, $data['reason']);

        return back()->with('success', 'Avis écarté : il ne compte plus dans la note.');
    }
}
