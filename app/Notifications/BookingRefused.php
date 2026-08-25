<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the client when the agency declines. The motive is mandatory
 * upstream and carried verbatim: a refusal without one leaves the client
 * guessing whether to try other dates or another agency.
 */
class BookingRefused extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $reason,
    ) {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking;

        return (new MailMessage)
            ->subject("Demande non retenue - {$b->booking_reference}")
            ->greeting("Bonjour {$b->client_name},")
            ->line("L'agence {$b->agency->commercial_name} n'a pas pu retenir votre demande pour "
                .$b->vehicle?->title().'.')
            ->line('**Motif :** '.$this->reason)
            ->line('Vos dates sont de nouveau libres : d\'autres vehicules sont disponibles sur la plateforme.')
            ->action('Chercher un autre vehicule', route('search'))
            ->salutation("L'equipe Autokrili");
    }
}
