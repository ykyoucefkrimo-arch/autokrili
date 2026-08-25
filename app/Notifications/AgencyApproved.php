<?php

namespace App\Notifications;

use App\Models\Agency;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencyApproved extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(public readonly Agency $agency)
    {
    }


    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre agence est approuvée — Autokrili')
            ->greeting("Bonjour {$this->agency->manager_name},")
            ->line("Le compte de l'agence « {$this->agency->commercial_name} » vient d'être approuvé.")
            ->line('Vous pouvez dès maintenant publier vos véhicules et recevoir des demandes de réservation.')
            ->line('Votre formule actuelle est **Silver** : 5 annonces et 1 photo par annonce.')
            ->action('Accéder à mon tableau de bord', url('/agence'))
            ->line("Merci d'avoir rejoint Autokrili.");
    }
}
