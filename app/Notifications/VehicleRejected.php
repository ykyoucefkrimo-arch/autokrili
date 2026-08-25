<?php

namespace App\Notifications;

use App\Models\Vehicle;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VehicleRejected extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly string $reason,
    ) {
    }


    /**
     * The listing is not deleted, only refused: the email says so, because an
     * agency that believes its work is gone starts over from scratch.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Annonce à corriger — {$this->vehicle->title()}")
            ->greeting("Bonjour {$this->vehicle->agency->manager_name},")
            ->line("L'annonce « {$this->vehicle->title()} » n'a pas été publiée en l'état.")
            ->line('**Motif :** '.$this->reason)
            ->line('Elle reste dans votre espace : corrigez les points signalés puis soumettez-la de nouveau.')
            ->action('Corriger l’annonce', route('agency.vehicles.edit', $this->vehicle))
            ->salutation("L'équipe Autokrili");
    }
}
