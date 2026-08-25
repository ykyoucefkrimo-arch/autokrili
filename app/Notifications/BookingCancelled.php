<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cancellation, sent to whichever side did not trigger it. The same class
 * serves the client, the agency and the automatic expiry: the wording changes,
 * the fact does not.
 */
class BookingCancelled extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $reason,
        public readonly string $by,
    ) {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking;

        $who = match ($this->by) {
            'client' => 'Le client a annule',
            'agency' => "L'agence a annule",
            default => 'La demande a expire pour',
        };

        return (new MailMessage)
            ->subject("Reservation annulee - {$b->booking_reference}")
            ->greeting('Bonjour,')
            ->line("{$who} la reservation {$b->booking_reference} ("
                .$b->vehicle?->title().', du '
                .$b->start_date->translatedFormat('d F Y').' au '
                .$b->end_date->translatedFormat('d F Y').').')
            ->line('**Motif :** '.$this->reason)
            ->line('Les dates sont de nouveau disponibles.')
            ->salutation("L'equipe Autokrili");
    }
}
