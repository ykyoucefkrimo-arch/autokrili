<?php

namespace App\Notifications;

use App\Models\Agency;
use App\Models\Plan;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent when a plan change archived listings (specification 4.3). It names them
 * one by one: "certaines de vos annonces" would leave the agency to guess
 * which, and guessing wrong means republishing the wrong car.
 */
class SubscriptionDowngraded extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Agency $agency,
        public readonly Plan $plan,
        public readonly Collection $archived,
    ) {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->archived->count();

        $message = (new MailMessage)
            ->subject("{$count} annonce(s) archivee(s) - formule {$this->plan->name}")
            ->greeting("Bonjour {$this->agency->manager_name},")
            ->line("Votre compte est desormais sur la formule {$this->plan->name}, qui autorise "
                .($this->plan->max_listings ?? 'un nombre illimite d\'')." annonces actives.")
            ->line("**{$count} annonce(s) ont ete archivee(s)**, les plus anciennes d'abord :");

        foreach ($this->archived as $vehicle) {
            $message->line('- '.$vehicle->title());
        }

        return $message
            ->line('Rien n\'est supprime : photos, tarifs et historique sont conserves. Vous pouvez '
                .'republier celles de votre choix en archivant d\'autres annonces, ou reprendre une '
                .'formule superieure.')
            ->action('Gerer mes annonces', route('agency.vehicles.index'))
            ->salutation("L'equipe Autokrili");
    }
}
