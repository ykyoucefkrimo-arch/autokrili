<?php

namespace App\Notifications;

use App\Models\Agency;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the agency itself and to the administrators (specification 10). */
class AgencyRegistered extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Agency $agency,
        public readonly bool $forAdmin = false,
    ) {
    }


    public function toMail(object $notifiable): MailMessage
    {
        if ($this->forAdmin) {
            return (new MailMessage)
                ->subject("Nouvelle inscription d'agence — {$this->agency->commercial_name}")
                ->line("L'agence « {$this->agency->commercial_name} » ({$this->agency->wilaya?->name_fr}) attend une décision.")
                ->line("Registre de commerce : {$this->agency->trade_register_number}")
                ->action('Examiner la demande', url("/admin/agences/{$this->agency->id}"));
        }

        return (new MailMessage)
            ->subject('Votre inscription est enregistrée — Autokrili')
            ->greeting("Bonjour {$this->agency->manager_name},")
            ->line("Nous avons bien reçu la demande d'inscription de « {$this->agency->commercial_name} ».")
            ->line('Votre registre de commerce est en cours de vérification. Vous recevrez une réponse sous 48 heures ouvrées.')
            ->salutation("L'équipe Autokrili");
    }
}
