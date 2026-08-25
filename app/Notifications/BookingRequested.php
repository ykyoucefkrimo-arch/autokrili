<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the agency: a request is waiting, and the clock is running. */
class BookingRequested extends Notification implements ShouldQueue
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
            ->subject("Nouvelle demande de reservation {$b->booking_reference}")
            ->greeting('Bonjour,')
            ->line("{$b->client_name} demande {$b->vehicle?->title()} du "
                .$b->start_date->translatedFormat('d F Y').' au '
                .$b->end_date->translatedFormat('d F Y')." ({$b->total_days} jour(s)).")
            ->line('**Total : '.number_format($b->total_price_dzd, 0, ',', ' ').' DA**')
            ->line("Telephone du client : {$b->client_phone}")
            ->line('Vous avez 24 heures pour repondre : passe ce delai, les dates sont liberees automatiquement.')
            ->action('Voir la demande', route('agency.bookings.show', $b))
            ->salutation("L'equipe Autokrili");
    }

    /** Un SMS coute par segment de 160 signes : dire l'essentiel, une fois. */
    public function toSms(object $notifiable): string
    {
        $b = $this->booking;

        return "Autokrili : nouvelle demande {$b->booking_reference} de {$b->client_name} "
            .'du '.$b->start_date->format('d/m').' au '.$b->end_date->format('d/m')
            .". Reponse sous 24h, sinon les dates sont liberees. Tel client : {$b->client_phone}";
    }
}
