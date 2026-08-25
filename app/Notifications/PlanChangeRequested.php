<?php

namespace App\Notifications;

use App\Models\PlanChangeRequest;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the administrators: an agency wants to change plan. */
class PlanChangeRequested extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(public readonly PlanChangeRequest $request)
    {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;

        $message = (new MailMessage)
            ->subject("Demande de formule - {$r->agency?->commercial_name}")
            ->greeting('Bonjour,')
            ->line("{$r->agency?->commercial_name} demande la formule {$r->requestedPlan?->name}.");

        if ($r->agency_message) {
            $message->line('**Message de l\'agence :** '.$r->agency_message);
        }

        return $message
            ->line('Aucun paiement n\'est encaisse en ligne : attribuez la formule une fois le '
                .'reglement recu.')
            ->action('Traiter la demande', route('admin.plans.index'))
            ->salutation("L'equipe Autokrili");
    }
}
