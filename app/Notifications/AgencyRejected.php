<?php

namespace App\Notifications;

use App\Models\Agency;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencyRejected extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Agency $agency,
        public readonly string $reason,
    ) {
    }


    /**
     * The reason is mandatory upstream and carried here verbatim: a refusal
     * without a motive leaves the agency with nothing to correct.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Votre inscription n'a pas été retenue — Autokrili")
            ->greeting("Bonjour {$this->agency->manager_name},")
            ->line("La demande d'inscription de « {$this->agency->commercial_name} » n'a pas pu être validée.")
            ->line('**Motif :** '.$this->reason)
            ->line('Vous pouvez corriger les points signalés et nous répondre à cet email pour un nouvel examen.')
            ->salutation("L'équipe Autokrili");
    }
}
