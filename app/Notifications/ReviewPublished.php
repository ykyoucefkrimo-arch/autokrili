<?php

namespace App\Notifications;

use App\Models\Review;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the agency a review is now public. It carries the rating and the
 * comment: an agency that has to log in to find out what was said about it
 * will find out too late.
 */
class ReviewPublished extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(public readonly Review $review)
    {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->review;

        $message = (new MailMessage)
            ->subject("Nouvel avis publie - {$r->rating}/5")
            ->greeting("Bonjour {$r->agency->manager_name},")
            ->line("Un client a laisse un avis de **{$r->rating}/5** sur votre agence.");

        if ($r->comment) {
            $message->line('> '.$r->comment);
        }

        $message->line("Votre note moyenne est desormais de {$r->agency->average_rating}/5 "
            ."sur {$r->agency->reviews_count} avis.");

        if ($r->agency->currentPlan()?->can_reply_reviews) {
            $message->action('Repondre publiquement', route('agency.reviews.index'));
        } else {
            $message->line('Les formules Gold et Platinium permettent de repondre publiquement '
                .'aux avis.');
        }

        return $message->salutation("L'equipe Autokrili");
    }
}
