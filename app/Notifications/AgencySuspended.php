<?php

namespace App\Notifications;

use App\Models\Agency;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencySuspended extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Agency $agency,
        public readonly string $reason,
    ) {
    }


    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte agence est suspendu — Autokrili')
            ->greeting("Bonjour {$this->agency->manager_name},")
            ->line("Le compte « {$this->agency->commercial_name} » a été suspendu.")
            ->line('**Motif :** '.$this->reason)
            ->line('Vos annonces ne sont plus visibles du public. Votre tableau de bord reste consultable, en lecture seule.')
            ->line('Répondez à cet email pour régulariser la situation.')
            ->salutation("L'équipe Autokrili");
    }
}
