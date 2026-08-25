<?php

namespace App\Notifications;

use App\Models\Vehicle;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VehicleApproved extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(public readonly Vehicle $vehicle)
    {
    }


    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Votre annonce est en ligne — {$this->vehicle->title()}")
            ->greeting("Bonjour {$this->vehicle->agency->manager_name},")
            ->line("L'annonce « {$this->vehicle->title()} » est validée et visible par les clients.")
            ->action('Voir mes annonces', route('agency.vehicles.index'))
            ->salutation("L'équipe Autokrili");
    }
}
