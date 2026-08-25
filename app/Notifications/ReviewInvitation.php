<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent 24 h after the vehicle came back (specification 6.2). */
class ReviewInvitation extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(public readonly Booking $booking)
    {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking;

        return (new MailMessage)
            ->subject("Comment s'est passee votre location ?")
            ->greeting("Bonjour {$b->client_name},")
            ->line("Vous avez loue {$b->vehicle?->title()} chez {$b->agency->commercial_name} "
                .'du '.$b->start_date->translatedFormat('d F').' au '
                .$b->end_date->translatedFormat('d F Y').'.')
            ->line('Votre avis aide les prochains clients a choisir, et l\'agence a s\'ameliorer. '
                .'Deux minutes suffisent.')
            ->action('Laisser un avis', route('bookings.index'))
            ->line('Les avis sont relus avant publication.')
            ->salutation("L'equipe Autokrili");
    }
}
