<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\RoutesThroughConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent seven days before a plan expires (specification 4.3). It says plainly
 * what the agency loses: an agency that discovers its listings archived on the
 * morning of the expiry has been badly served.
 */
class SubscriptionExpiring extends Notification implements ShouldQueue
{
    use Queueable;
    use RoutesThroughConfiguredChannels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly bool $forAdmin = false,
    ) {
    }


    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->subscription;
        $agency = $s->agency;
        $active = $agency->vehicles()
            ->whereIn('status', \App\Services\ListingQuotaService::OCCUPYING_STATUSES)
            ->count();

        if ($this->forAdmin) {
            // L'administrateur relance : il lui faut le nom, la date et de quoi
            // ouvrir le dossier, pas le discours adresse a l'agence.
            return (new MailMessage)
                ->subject("Echeance : {$agency->commercial_name} - {$s->plan->name} le "
                    .$s->ends_at->format('d/m/Y'))
                ->greeting('Bonjour,')
                ->line("La formule {$s->plan->name} de « {$agency->commercial_name} » expire le "
                    .$s->ends_at->translatedFormat('d F Y').'.')
                ->line("{$active} annonce(s) active(s). Sans renouvellement, l'agence repasse en "
                    .'Silver et ce qui depasse est archive.')
                ->action('Ouvrir les formules', route('admin.plans.index'))
                ->salutation("L'equipe Autokrili");
        }

        $message = (new MailMessage)
            ->subject("Votre formule {$s->plan->name} expire le ".$s->ends_at->format('d/m/Y'))
            ->greeting("Bonjour {$agency->manager_name},")
            ->line("La formule {$s->plan->name} de « {$agency->commercial_name} » arrive a echeance le "
                .$s->ends_at->translatedFormat('d F Y').'.')
            ->line('Sans renouvellement, votre compte repasse en Silver.');

        // Le quota Silver se lit en base, jamais en dur (§4.1) : l'administrateur
        // peut le changer, et cet email dirait alors un chiffre faux.
        $silverMax = \App\Models\Plan::where('slug', \App\Models\Plan::SILVER)->value('max_listings');

        // Le chiffre exact vaut mieux qu'un avertissement general : il dit a
        // l'agence si elle a quelque chose a perdre, et combien.
        if ($silverMax !== null && $active > $silverMax) {
            $message->line("**Attention : vous avez {$active} annonces actives. La formule Silver en "
                ."autorise {$silverMax} : les ".($active - $silverMax)." plus anciennes seront archivees.**")
                ->line('Archivees, pas supprimees : photos et tarifs sont conserves, vous pourrez les '
                    .'republier en changeant de formule ou en archivant d\'autres annonces.');
        }

        return $message
            ->action('Voir mon abonnement', route('agency.subscription'))
            ->salutation("L'equipe Autokrili");
    }
}
