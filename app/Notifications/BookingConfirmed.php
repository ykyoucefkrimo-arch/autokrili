<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the client when the agency accepts. Carries the voucher link and,
 * above all, the agency's phone number: at this point the client's next
 * question is where and when to pick the car up.
 */
class BookingConfirmed extends Notification implements ShouldQueue
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
            ->subject("Réservation confirmée — {$b->booking_reference}")
            ->greeting("Bonjour {$b->client_name},")
            ->line("L’agence {$b->agency->commercial_name} a confirmé votre réservation.")
            ->line("**{$b->vehicle?->title()}** du "
                .$b->start_date->translatedFormat('d F Y').' au '
                .$b->end_date->translatedFormat('d F Y')." ({$b->total_days} jour(s))")
            ->line('**Total : '.number_format($b->total_price_dzd, 0, ',', ' ').' DA**'
                .($b->deposit_dzd > 0
                    ? ' — caution de '.number_format($b->deposit_dzd, 0, ',', ' ').' DA au départ'
                    : ''))
            ->line("Retrait : {$b->pickup_location}")
            ->line("Téléphone de l’agence : {$b->agency->phone}")
            ->line('Le règlement se fait à l’agence, au moment du départ.')
            ->action('Télécharger le bon de réservation', route('bookings.voucher', $b))
            ->salutation("L'équipe Autokrili");
    }

    public function toSms(object $notifiable): string
    {
        $b = $this->booking;

        return "Autokrili : reservation {$b->booking_reference} confirmee par "
            ."{$b->agency->commercial_name}. Retrait le ".$b->start_date->format('d/m')
            ." a {$b->pickup_location}. Reglement sur place. Tel : {$b->agency->phone}";
    }
}
