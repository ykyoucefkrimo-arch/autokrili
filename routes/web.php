<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MailboxController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Agency\DashboardController as AgencyDashboardController;
use App\Http\Controllers\Agency\BookingController as AgencyBookingController;
use App\Http\Controllers\Agency\CalendarController;
use App\Http\Controllers\Agency\PendingController;
use App\Http\Controllers\Agency\ReviewController as AgencyReviewController;
use App\Http\Controllers\Agency\SettingsController;
use App\Http\Controllers\Agency\StatsController;
use App\Http\Controllers\Agency\SubscriptionController;
use App\Http\Controllers\Agency\VehicleController;
use App\Http\Controllers\Agency\VehiclePhotoController;
use App\Http\Controllers\Auth\AgencyRegistrationController;
use App\Http\Controllers\Public\CatalogController;
use App\Http\Controllers\Client\BookingController as ClientBookingController;
use App\Http\Controllers\Public\BookingController as PublicBookingController;
use App\Http\Controllers\Public\AgencyController as PublicAgencyController;
use App\Http\Controllers\Public\CommuneController;
use App\Http\Controllers\Public\ContactClickController;
use App\Http\Controllers\Public\LocaleController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureAgencyIsApproved;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Site public
|--------------------------------------------------------------------------
| Les URLs de recherche portent la wilaya et la commune dans le chemin
| (/location-voiture/alger/bab-ezzouar) : c'est la forme demandee au 7.2, et
| c'est celle qu'un moteur de recherche indexe. Les filtres restent en
| parametres de requete, ils ne decrivent pas un lieu.
*/
Route::get('/', [CatalogController::class, 'home'])->name('home');

Route::get('location-voiture/{wilaya:slug}/{commune:slug}', [CatalogController::class, 'search'])
    ->name('search.commune');
Route::get('location-voiture/{wilaya:slug}', [CatalogController::class, 'search'])
    ->name('search.wilaya');
Route::get('location-voiture', [CatalogController::class, 'search'])->name('search');

// Les communes d'une wilaya, chargees au choix de la wilaya : 1541 communes
// expediees avec chaque page couteraient ~90 Ko a chaque premier affichage.
Route::get('communes/{wilaya:slug}', CommuneController::class)->name('communes.index');

// Clic sur le telephone ou WhatsApp : le signal le plus fort du site public,
// et le navigateur est seul a savoir qu'il a eu lieu.
Route::post('vehicule/{vehicle}/contact', ContactClickController::class)
    ->middleware('throttle:60,1')
    ->name('vehicles.contact');

// Fiche agence : /agence-de-location/alger-prestige-cars
Route::get('agence-de-location/{agency:slug}', [PublicAgencyController::class, 'show'])
    ->name('agency.public');

Route::get('a-propos', [PageController::class, 'about'])->name('pages.about');
Route::get('conditions-generales', [PageController::class, 'terms'])->name('pages.terms');
Route::get('confidentialite', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('questions-frequentes', [PageController::class, 'faq'])->name('pages.faq');
Route::get('contact', [PageController::class, 'contact'])->name('pages.contact');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

// Changement de langue (§7.2) : garde en session, pour ne pas dupliquer les
// URLs indexables.
Route::post('langue', LocaleController::class)->name('locale.switch');

Route::get('vehicule/{vehicle}-{slug}', [CatalogController::class, 'show'])
    ->where(['vehicle' => '[0-9]+'])
    ->name('vehicle.show');

/*
|--------------------------------------------------------------------------
| Tunnel de reservation
|--------------------------------------------------------------------------
| Ouvert aux visiteurs : le compte client est cree au passage (§15). Exiger
| une inscription avant de savoir si la voiture est libre fait perdre le
| client.
*/
Route::get('vehicule/{vehicle}/reserver', [PublicBookingController::class, 'create'])
    ->name('bookings.create');
Route::get('vehicule/{vehicle}/devis', [PublicBookingController::class, 'quote'])
    ->name('bookings.quote');
Route::post('vehicule/{vehicle}/reserver', [PublicBookingController::class, 'store'])
    // Chaque envoi peut creer un compte : le formulaire est limite.
    ->middleware('throttle:10,1')
    ->name('bookings.store');

Route::middleware('auth')->group(function () {
    Route::get('reservation/{booking:booking_reference}', [PublicBookingController::class, 'confirmation'])
        ->name('bookings.confirmation');

    Route::get('mes-reservations', [ClientBookingController::class, 'index'])->name('bookings.index');
    Route::post('mes-reservations/{booking}/annuler', [ClientBookingController::class, 'cancel'])
        ->name('bookings.cancel');
    Route::get('mes-reservations/{booking}/bon', [ClientBookingController::class, 'voucher'])
        ->name('bookings.voucher');
    Route::post('mes-reservations/{booking}/avis', [ClientBookingController::class, 'review'])
        ->name('bookings.review');
});

/*
|--------------------------------------------------------------------------
| Inscription des agences
|--------------------------------------------------------------------------
| Distincte de l'inscription client : elle collecte le registre de commerce
| et place le compte en attente de modération.
*/
Route::middleware('guest')->group(function () {
    Route::get('inscription-agence', [AgencyRegistrationController::class, 'create'])
        ->name('agency.register');
    Route::post('inscription-agence', [AgencyRegistrationController::class, 'store'])
        // Rate limited: this form creates an account and writes two files.
        ->middleware('throttle:6,1');
});

/*
|--------------------------------------------------------------------------
| Espace agence
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('agence')->name('agency.')->group(function () {
    // Reachable while pending: it is the screen that explains the wait.
    Route::get('en-attente', PendingController::class)->name('pending');

    Route::middleware(EnsureAgencyIsApproved::class)->group(function () {
        Route::get('/', AgencyDashboardController::class)->name('dashboard');

        // Annonces. Le middleware laisse passer les verbes sûrs d'une agence
        // suspendue : elle consulte ses annonces sans pouvoir en publier.
        Route::get('vehicules', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicules/nouveau', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('vehicules', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('vehicules/{vehicle}/modifier', [VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('vehicules/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicules/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::post('vehicules/{vehicle}/soumettre', [VehicleController::class, 'submit'])->name('vehicles.submit');
        Route::post('vehicules/{vehicle}/archiver', [VehicleController::class, 'archive'])->name('vehicles.archive');

        Route::post('vehicules/{vehicle}/photos', [VehiclePhotoController::class, 'store'])
            // Chaque upload écrit trois dérivés sur le disque.
            ->middleware('throttle:30,1')
            ->name('vehicles.photos.store');
        Route::post('vehicules/{vehicle}/photos/ordre', [VehiclePhotoController::class, 'reorder'])
            ->name('vehicles.photos.reorder');
        Route::post('vehicules/{vehicle}/photos/{photo}/couverture', [VehiclePhotoController::class, 'cover'])
            ->name('vehicles.photos.cover');
        Route::delete('vehicules/{vehicle}/photos/{photo}', [VehiclePhotoController::class, 'destroy'])
            ->name('vehicles.photos.destroy');

        // Reservations
        Route::get('reservations', [AgencyBookingController::class, 'index'])->name('bookings.index');
        Route::get('reservations/export', [AgencyBookingController::class, 'export'])->name('bookings.export');
        Route::get('reservations/{booking}', [AgencyBookingController::class, 'show'])->name('bookings.show');
        Route::post('reservations/{booking}/accepter', [AgencyBookingController::class, 'confirm'])->name('bookings.confirm');
        Route::post('reservations/{booking}/refuser', [AgencyBookingController::class, 'refuse'])->name('bookings.refuse');
        Route::post('reservations/{booking}/depart', [AgencyBookingController::class, 'start'])->name('bookings.start');
        Route::post('reservations/{booking}/retour', [AgencyBookingController::class, 'complete'])->name('bookings.complete');
        Route::post('reservations/{booking}/annuler', [AgencyBookingController::class, 'cancel'])->name('bookings.cancel');

        // Statistiques (§8), filtrees par le niveau de la formule.
        Route::get('statistiques', StatsController::class)->name('stats');

        // Avis : consultation, et reponse publique si la formule l'autorise.
        Route::get('avis', [AgencyReviewController::class, 'index'])->name('reviews.index');
        Route::post('avis/{review}/reponse', [AgencyReviewController::class, 'reply'])->name('reviews.reply');

        // Mon abonnement : consulter, comparer, demander un changement.
        Route::get('abonnement', [SubscriptionController::class, 'show'])->name('subscription');
        Route::post('abonnement/demande', [SubscriptionController::class, 'request'])
            ->name('subscription.request');

        // Parametres de l'agence : profil, position, horaires, conditions.
        Route::get('parametres', [SettingsController::class, 'edit'])->name('settings');
        Route::post('parametres', [SettingsController::class, 'update'])->name('settings.update');

        // Calendrier et blocages manuels
        Route::get('calendrier', [CalendarController::class, 'index'])->name('calendar');
        Route::post('calendrier/blocages', [CalendarController::class, 'store'])->name('blocks.store');
        Route::delete('calendrier/blocages/{block}', [CalendarController::class, 'destroy'])->name('blocks.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('agences', [AgencyController::class, 'index'])->name('agencies.index');
    Route::get('agences/{agency}', [AgencyController::class, 'show'])->name('agencies.show');
    Route::get('agences/{agency}/registre-commerce', [AgencyController::class, 'tradeRegister'])
        ->name('agencies.trade-register');

    Route::post('agences/{agency}/approuver', [AgencyController::class, 'approve'])->name('agencies.approve');
    Route::post('agences/{agency}/rejeter', [AgencyController::class, 'reject'])->name('agencies.reject');
    Route::post('agences/{agency}/suspendre', [AgencyController::class, 'suspend'])->name('agencies.suspend');
    Route::post('agences/{agency}/reactiver', [AgencyController::class, 'reinstate'])->name('agencies.reinstate');
    Route::post('agences/{agency}/confiance', [AgencyController::class, 'trust'])->name('agencies.trust');

    /*
    | Moderation des annonces. Le traitement par lot passe par une route
    | dediee plutot que par la route unitaire : approuver vingt annonces ne
    | doit pas dependre de vingt requetes qui peuvent echouer a mi-chemin.
    */
    Route::get('annonces', [AdminVehicleController::class, 'index'])->name('vehicles.index');
    Route::post('annonces/lot', [AdminVehicleController::class, 'bulk'])->name('vehicles.bulk');
    Route::get('annonces/{vehicle}', [AdminVehicleController::class, 'show'])->name('vehicles.show');
    Route::post('annonces/{vehicle}/approuver', [AdminVehicleController::class, 'approve'])->name('vehicles.approve');
    Route::post('annonces/{vehicle}/rejeter', [AdminVehicleController::class, 'reject'])->name('vehicles.reject');

    /*
    | Formules. La matrice du 4.1 vit en base et se modifie ici : c'est ce qui
    | rend vraie la promesse « jamais codees en dur ».
    */
    Route::get('avis', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('avis/{review}/approuver', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('avis/{review}/rejeter', [AdminReviewController::class, 'reject'])->name('reviews.reject');

    /*
    | Boite d'envoi : ce que la plateforme a produit comme emails. N'existe
    | que lorsque le mailer « file » est actif (demonstration, developpement).
    */
    Route::get('emails', [MailboxController::class, 'index'])->name('mailbox.index');
    Route::get('emails/{file}', [MailboxController::class, 'show'])->name('mailbox.show');
    Route::delete('emails', [MailboxController::class, 'destroy'])->name('mailbox.destroy');

    Route::get('formules', [PlanController::class, 'index'])->name('plans.index');
    Route::put('formules/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::post('formules/attribuer/{agency}', [PlanController::class, 'grant'])->name('plans.grant');
    Route::post('formules/demandes/{planChangeRequest}/refuser', [PlanController::class, 'refuseRequest'])
        ->name('plans.requests.refuse');
});

/*
|--------------------------------------------------------------------------
| Espace connecté commun
|--------------------------------------------------------------------------
| `dashboard` est le point d'arrivée après connexion : il aiguille chacun
| vers son espace plutôt que d'afficher un écran vide.
*/
Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isAgency()) {
        return redirect()->route(
            $user->activeAgency()?->isApproved() ? 'agency.dashboard' : 'agency.pending'
        );
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
