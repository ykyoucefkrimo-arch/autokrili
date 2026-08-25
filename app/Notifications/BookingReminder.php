<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The day-before reminder of specification 10, sent to both sides.
 *
 * The wording changes with the recipient: the client needs to know what to
 * bring, the agency which car to prepare. One class rather than two because
 * the fact — a rental starts tomorrow — is the same.
 */
class BookingReminder extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Booking $booking,
        public readonly bool $forAgency = false,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking;

        if ($this->forAgency) {
            return (new MailMessage)
                ->subject("Depart demain - {$b->booking_reference}")
                ->greeting('Bonjour,')
                ->line("Depart demain ".$b->start_date->translatedFormat('l d F')." : "
                    .$b->vehicle?->title()." pour {$b->client_name}.")
                ->line("Retrait a {$b->pickup_location}. Retour prevu le "
                    .$b->end_date->translatedFormat('d F').".")
                ->line("Telephone du client : {$b->client_phone}")
                ->action('Voir la reservation', route('agency.bookings.show', $b))
                ->salutation("L'equipe Autokrili");
        }

        $message = (new MailMessage)
            ->subject("Votre location commence demain - {$b->booking_reference}")
            ->greeting("Bonjour {$b->client_name},")
            ->line("Votre location de ".$b->vehicle?->title()." commence demain, "
                .$b->start_date->translatedFormat('l d F').".")
            ->line("Retrait a {$b->pickup_location}.")
            // Ce qu'il faut apporter : c'est la question du client la veille au
            // soir, et l'oubli d'une piece fait perdre la location.
            ->line('**A apporter :** ce bon de reservation, une piece d\'identite et votre '
                .'permis de conduire.');

        if ($b->deposit_dzd > 0) {
            $message->line('**Caution demandee au depart :** '
                .number_format($b->deposit_dzd, 0, ',', ' ').' DA');
        }

        return $message
            ->line("Total a regler sur place : ".number_format($b->total_price_dzd, 0, ',', ' ').' DA')
            ->line("Un imprevu ? Appelez l'agence au {$b->agency->phone}.")
            ->action('Telecharger mon bon', route('bookings.voucher', $b))
            ->salutation("L'equipe Autokrili");
    }

    public function toSms(object $notifiable): string
    {
        $b = $this->booking;

        if ($this->forAgency) {
            return "Autokrili : depart demain, ".$b->vehicle?->title()
                ." pour {$b->client_name} ({$b->client_phone}), ref {$b->booking_reference}.";
        }

        return "Autokrili : votre location ".$b->vehicle?->title()." commence demain a "
            ."{$b->pickup_location}. Apportez piece d'identite, permis et le bon "
            ."{$b->booking_reference}. Agence : {$b->agency->phone}";
    }
}
