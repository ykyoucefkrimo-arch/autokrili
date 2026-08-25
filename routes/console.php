<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
| Une demande sans réponse gèle le calendrier d'un véhicule et laisse le
| client sans nouvelle : elle doit expirer d'elle-même (§6.2).
*/
Schedule::command('bookings:expire')
    ->hourly()
    ->withoutOverlapping();

/*
| Formules : prevenir a sept jours, retrograder apres echeance (§4.3). Une
| fois par jour suffit — une formule expire a la journee, pas a l'heure — et
| tot le matin pour que l'agence trouve l'email en arrivant.
*/
Schedule::command('subscriptions:refresh')
    ->dailyAt('06:00')
    ->withoutOverlapping();

/*
| Avis : inviter 24 h apres le retour (§6.2). Toutes les heures, parce que le
| delai se compte depuis le retour de chaque vehicule, pas depuis minuit.
*/
Schedule::command('reviews:invite')
    ->hourly()
    ->withoutOverlapping();

/*
| Rappel J-1 (§10). En fin d'apres-midi : le client prepare ses papiers le
| soir, pas a sept heures du matin.
*/
Schedule::command('bookings:remind')
    ->dailyAt('17:00')
    ->withoutOverlapping();
