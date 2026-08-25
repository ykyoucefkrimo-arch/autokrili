<?php

use App\Models\Booking;
use App\Models\Plan;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ReviewInvitation;
use App\Services\BookingService;
use App\Services\ReviewService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);
    $this->admin->syncRoles([User::ROLE_ADMIN]);

    $this->agency = agencyOn(Plan::GOLD);
    $this->agency->update(['buffer_hours' => 0]);
    $this->vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
    $this->vehicle->update(['published_at' => now()]);

    $this->reviews = app(ReviewService::class);
});

/** A booking taken all the way to `completed`. */
function completedBooking($agency, $vehicle): Booking
{
    Notification::fake();
    $bookings = app(BookingService::class);

    $booking = $bookings->request($vehicle, [
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'client_name' => 'Yacine Haddad',
        'client_phone' => '0555112233',
        'client_email' => 'yacine@test.dz',
    ], null);

    $bookings->confirm($booking);
    $bookings->start($booking->fresh());
    $bookings->complete($booking->fresh());

    return $booking->fresh();
}

/* --- Dépôt ---------------------------------------------------------------- */

it('n’accepte un avis qu’une fois la location terminée', function () {
    Notification::fake();
    $booking = app(BookingService::class)->request($this->vehicle, [
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'client_name' => 'Yacine', 'client_phone' => '0555112233',
        'client_email' => 'yacine@test.dz',
    ], null);

    // C'est ce qui distingue un avis d'une opinion.
    actingAs($booking->client)
        ->post(route('bookings.review', $booking), ['rating' => 5])
        ->assertSessionHasErrors('review');

    expect(Review::count())->toBe(0);
});

it('dépose un avis en attente de modération', function () {
    $booking = completedBooking($this->agency, $this->vehicle);

    actingAs($booking->client)
        ->post(route('bookings.review', $booking), ['rating' => 4, 'comment' => 'Voiture propre, accueil correct.'])
        ->assertRedirect();

    $review = Review::first();

    // En attente : un avis diffamatoire ne doit pas s'afficher le temps qu'un
    // administrateur le voie.
    expect($review->status)->toBe(Review::STATUS_PENDING)
        ->and($review->rating)->toBe(4)
        ->and($this->agency->fresh()->reviews_count)->toBe(0);
});

it('n’accepte qu’un seul avis par réservation', function () {
    $booking = completedBooking($this->agency, $this->vehicle);

    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 5]);
    actingAs($booking->client)
        ->post(route('bookings.review', $booking), ['rating' => 1])
        ->assertSessionHasErrors('review');

    // Une note ne se gonfle pas par répétition.
    expect(Review::count())->toBe(1)
        ->and(Review::first()->rating)->toBe(5);
});

it('interdit de noter la location de quelqu’un d’autre', function () {
    $booking = completedBooking($this->agency, $this->vehicle);

    $intrus = User::create([
        'name' => 'Curieux', 'email' => 'curieux@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);

    actingAs($intrus)->post(route('bookings.review', $booking), ['rating' => 1])->assertForbidden();
    expect(Review::count())->toBe(0);
});

/* --- Modération ----------------------------------------------------------- */

it('ne compte l’avis dans la note qu’une fois publié', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 4]);
    $review = Review::first();

    expect($this->agency->fresh()->average_rating)->toBe('0.00');

    actingAs($this->admin)->post(route('admin.reviews.approve', $review))->assertRedirect();

    expect($this->agency->fresh()->reviews_count)->toBe(1)
        ->and((float) $this->agency->fresh()->average_rating)->toBe(4.0);
});

it('retire de la note un avis écarté après publication', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 1]);
    $review = Review::first();

    actingAs($this->admin)->post(route('admin.reviews.approve', $review));
    expect($this->agency->fresh()->reviews_count)->toBe(1);

    actingAs($this->admin)->post(route('admin.reviews.reject', $review), [
        'reason' => 'Propos insultants.',
    ])->assertRedirect();

    expect($this->agency->fresh()->reviews_count)->toBe(0)
        ->and((float) $this->agency->fresh()->average_rating)->toBe(0.0);
});

it('exige un motif pour écarter un avis', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 2]);

    actingAs($this->admin)
        ->post(route('admin.reviews.reject', Review::first()), [])
        ->assertSessionHasErrors('reason');
});

it('cache la modération des avis à qui n’est pas administrateur', function () {
    actingAs($this->agency->user)->get(route('admin.reviews.index'))->assertNotFound();
});

/* --- Réponse de l'agence (§4.1) ------------------------------------------ */

it('laisse répondre une agence dont la formule l’autorise', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 3]);
    $review = Review::first();
    actingAs($this->admin)->post(route('admin.reviews.approve', $review));

    actingAs($this->agency->user)
        ->post(route('agency.reviews.reply', $review), ['reply' => 'Merci pour votre retour.'])
        ->assertSessionHasNoErrors();

    expect($review->fresh()->agency_reply)->toBe('Merci pour votre retour.')
        ->and($review->fresh()->replied_at)->not->toBeNull();
});

it('refuse la réponse à une formule qui ne l’inclut pas', function () {
    $silver = agencyOn(Plan::SILVER, email: 'silver@test.dz');
    $silver->update(['buffer_hours' => 0]);
    $vehicle = readyVehicle($silver, Vehicle::STATUS_PUBLISHED);
    $vehicle->update(['published_at' => now()]);

    $booking = completedBooking($silver, $vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 5]);
    $review = Review::first();
    actingAs($this->admin)->post(route('admin.reviews.approve', $review));

    actingAs($silver->user)
        ->post(route('agency.reviews.reply', $review), ['reply' => 'Merci beaucoup.'])
        ->assertSessionHasErrors('reply');

    expect($review->fresh()->agency_reply)->toBeNull();
});

it('interdit de répondre à l’avis d’une concurrente', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 5]);
    $review = Review::first();
    actingAs($this->admin)->post(route('admin.reviews.approve', $review));

    $autre = agencyOn(Plan::GOLD, email: 'autre@test.dz');

    actingAs($autre->user)
        ->post(route('agency.reviews.reply', $review), ['reply' => 'Réponse usurpée.'])
        ->assertNotFound();
});

/* --- Publication publique ------------------------------------------------- */

it('n’affiche publiquement que les avis approuvés', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), [
        'rating' => 5, 'comment' => 'Excellente agence.',
    ]);

    get(route('agency.public', $this->agency->slug))
        ->assertInertia(fn ($page) => $page->has('reviews', 0));

    actingAs($this->admin)->post(route('admin.reviews.approve', Review::first()));

    get(route('agency.public', $this->agency->slug))
        ->assertInertia(fn ($page) => $page
            ->has('reviews', 1)
            ->where('reviews.0.comment', 'Excellente agence.')
            // Prénom et initiale : un nom complet n'a rien à faire sur une page
            // publique indexée.
            ->where('reviews.0.author', 'Yacine H.'));
});

it('publie la note agrégée dans les données structurées une fois des avis présents', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 5]);
    actingAs($this->admin)->post(route('admin.reviews.approve', Review::first()));

    get(route('agency.public', $this->agency->slug))
        ->assertInertia(fn ($page) => $page
            // JSON serialise 5.0 en 5 : c'est la valeur que lit le robot.
            ->where('structuredData.aggregateRating.ratingValue', 5)
            ->where('structuredData.aggregateRating.reviewCount', 1));
});

/* --- Invitation (§6.2) ---------------------------------------------------- */

it('invite le client 24 heures après le retour, une seule fois', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    $booking->update(['completed_at' => now()->subHours(25)]);

    Notification::fake();

    expect($this->reviews->sendInvitations())->toBe(1);
    Notification::assertSentTo($booking->client, ReviewInvitation::class);

    // Idempotent : la commande tourne toutes les heures.
    expect($this->reviews->sendInvitations())->toBe(0);
});

it('n’invite pas avant le délai', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    $booking->update(['completed_at' => now()->subHours(2)]);

    Notification::fake();

    // Demandé au comptoir, un client répond par politesse ; le lendemain, il
    // répond ce qu'il pense.
    expect($this->reviews->sendInvitations())->toBe(0);
    Notification::assertNothingSent();
});

it('n’invite pas un client qui a déjà donné son avis', function () {
    $booking = completedBooking($this->agency, $this->vehicle);
    actingAs($booking->client)->post(route('bookings.review', $booking), ['rating' => 5]);
    $booking->update(['completed_at' => now()->subHours(25)]);

    Notification::fake();

    expect($this->reviews->sendInvitations())->toBe(0);
});
