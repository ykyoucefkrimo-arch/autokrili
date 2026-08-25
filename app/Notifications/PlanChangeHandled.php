<?php

namespace App\Notifications;

use App\Models\PlanChangeRequest;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The answer to a plan request, accepted or refused. A refusal carries its
 * reason: an agency that asked and gets silence assumes the platform is dead.
 */
class PlanChangeHandled extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(public readonly PlanChangeRequest $request)
    {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;
        $accepted = $r->status === PlanChangeRequest::STATUS_ACCEPTED;

        return (new MailMessage)
            ->subject($accepted
                ? "Votre formule {$r->requestedPlan?->name} est active"
                : 'Votre demande de formule')
            ->greeting("Bonjour {$r->agency?->manager_name},")
            ->line($accepted
                ? "La formule {$r->requestedPlan?->name} est desormais active sur votre compte."
                : "Votre demande pour la formule {$r->requestedPlan?->name} n'a pas ete retenue.")
            ->line('**'.($accepted ? 'Note' : 'Motif').' :** '.$r->admin_response)
            ->action('Voir mon abonnement', route('agency.subscription'))
            ->salutation("L'equipe Autokrili");
    }
}
