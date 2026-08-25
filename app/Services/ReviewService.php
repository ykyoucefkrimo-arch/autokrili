<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\Review;
use App\Notifications\ReviewInvitation;
use App\Notifications\ReviewPublished;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Client reviews, moderated before publication (§9).
 *
 * A review is attached to a booking, never to an agency directly: only someone
 * who actually rented can rate, and the unique index on `booking_id` is what
 * stops a rating being inflated by repetition.
 */
class ReviewService
{
    /** Hours after the return before the client is invited to review (§6.2). */
    public const INVITATION_DELAY_HOURS = 24;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function create(Booking $booking, int $rating, ?string $comment): Review
    {
        if ($booking->status !== Booking::STATUS_COMPLETED) {
            throw new RuntimeException(
                'Un avis se dépose une fois la location terminée.'
            );
        }

        if ($booking->review()->exists()) {
            throw new RuntimeException('Vous avez déjà laissé un avis pour cette location.');
        }

        // En attente de modération : un avis diffamatoire ou hors sujet ne doit
        // pas s'afficher le temps qu'un administrateur le voie (§9).
        return $booking->review()->create([
            'agency_id' => $booking->agency_id,
            'client_id' => $booking->client_id,
            'rating' => $rating,
            'comment' => $comment,
            'status' => Review::STATUS_PENDING,
        ]);
    }

    public function approve(Review $review): Review
    {
        $before = $review->getAttributes();

        $review->update(['status' => Review::STATUS_APPROVED, 'rejection_reason' => null]);
        $this->recomputeAgencyRating($review->agency);

        $this->audit->logChange('review.approved', $review, $before);
        $review->agency->user?->notify(new ReviewPublished($review->fresh()));

        return $review->refresh();
    }

    public function reject(Review $review, string $reason): Review
    {
        $before = $review->getAttributes();

        $review->update(['status' => Review::STATUS_REJECTED, 'rejection_reason' => $reason]);
        // Un avis retiré après publication doit cesser de peser sur la note.
        $this->recomputeAgencyRating($review->agency);

        $this->audit->logChange('review.rejected', $review, $before);

        return $review->refresh();
    }

    /** The agency's public answer, if its plan allows it (§4.1). */
    public function reply(Review $review, string $reply): Review
    {
        if (! $review->agency->currentPlan()?->can_reply_reviews) {
            throw new RuntimeException(
                'Votre formule ne permet pas de répondre aux avis. Les formules Gold et Platinium l’autorisent.'
            );
        }

        if ($review->status !== Review::STATUS_APPROVED) {
            throw new RuntimeException('Cet avis n’est pas encore publié.');
        }

        $review->update(['agency_reply' => $reply, 'replied_at' => now()]);

        return $review->refresh();
    }

    /**
     * The average and the count, recomputed from the approved reviews only.
     *
     * Stored on the agency rather than computed on read: it is displayed on
     * every card of every search result, and a rating that costs a join per
     * row is a rating nobody displays.
     */
    public function recomputeAgencyRating(Agency $agency): void
    {
        $row = Review::where('agency_id', $agency->id)
            ->where('status', Review::STATUS_APPROVED)
            ->selectRaw('COUNT(*) n, COALESCE(AVG(rating), 0) avg')
            ->first();

        $agency->forceFill([
            'reviews_count' => (int) $row->n,
            'average_rating' => round((float) $row->avg, 2),
        ])->save();
    }

    /**
     * Invites clients to review, 24 h after the vehicle came back (§6.2).
     *
     * `review_invited_at` makes it idempotent, and a booking whose client
     * already reviewed is skipped: nagging someone who answered is the fastest
     * way to have the next email ignored.
     *
     * @return int  invitations sent
     */
    public function sendInvitations(): int
    {
        $count = 0;

        Booking::query()
            ->where('status', Booking::STATUS_COMPLETED)
            ->whereNull('review_invited_at')
            ->whereNotNull('client_id')
            ->where('completed_at', '<=', now()->subHours(self::INVITATION_DELAY_HOURS))
            ->whereDoesntHave('review')
            ->with(['client', 'agency', 'vehicle'])
            ->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $booking->client?->notify(new ReviewInvitation($booking));
                    $booking->update(['review_invited_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    /** Rebuilds every agency rating — used after a bulk moderation pass. */
    public function recomputeAll(): void
    {
        DB::table('agencies')->orderBy('id')->select('id')->chunk(100, function ($agencies) {
            foreach ($agencies as $row) {
                $agency = Agency::find($row->id);

                if ($agency) {
                    $this->recomputeAgencyRating($agency);
                }
            }
        });
    }
}
