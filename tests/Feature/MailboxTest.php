<?php

use App\Mail\Transport\FileTransport;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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

    config(['mail.default' => 'file']);
});

function storeMail(string $name, array $payload = []): void
{
    Storage::disk('local')->put(
        FileTransport::DIRECTORY."/{$name}.json",
        json_encode(array_merge([
            'id' => 'abc', 'date' => now()->toIso8601String(),
            'subject' => 'Réservation confirmée', 'to' => ['client@test.dz'],
            'html' => '<p>Bonjour</p>', 'text' => 'Bonjour',
        ], $payload), JSON_UNESCAPED_UNICODE)
    );
}

it('liste les emails écrits sur le disque', function () {
    Storage::fake('local');
    storeMail('20260101-120000-aaaaaaaa');
    storeMail('20260101-130000-bbbbbbbb', ['subject' => 'Demande de réservation']);

    actingAs($this->admin)
        ->get(route('admin.mailbox.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Mailbox')
            ->has('emails', 2)
            // Le nom porte l'horodatage : le plus récent arrive en tête.
            ->where('emails.0.subject', 'Demande de réservation'));
});

it('rend le corps HTML tel que le destinataire le verrait', function () {
    Storage::fake('local');
    storeMail('20260101-120000-aaaaaaaa', ['html' => '<h1>Votre bon</h1>']);

    $response = actingAs($this->admin)
        ->get(route('admin.mailbox.show', '20260101-120000-aaaaaaaa'))
        ->assertOk();

    expect($response->getContent())->toContain('<h1>Votre bon</h1>')
        // Un email est du HTML arbitraire : rien ne doit s'exécuter ni partir
        // sur le réseau depuis le back office.
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('ferme l’écran dès que les emails partent vraiment', function () {
    // En production le mailer est un SMTP : cette boîte n'a plus de sens, et
    // une liste vide se prendrait pour une panne d'envoi.
    config(['mail.default' => 'smtp']);

    actingAs($this->admin)->get(route('admin.mailbox.index'))->assertNotFound();
    actingAs($this->admin)->get(route('admin.mailbox.show', 'x'))->assertNotFound();
});

it('cache la boîte d’envoi à qui n’est pas administrateur', function () {
    $agency = agencyOn(Plan::GOLD);

    // Elle contient les coordonnées et les références de tous les clients.
    actingAs($agency->user)->get(route('admin.mailbox.index'))->assertNotFound();

    // 404 et non une redirection vers la connexion : tout le back office
    // répond ainsi, l'existence d'une administration n'a pas à être confirmée
    // à un visiteur anonyme.
    get(route('admin.mailbox.index'))->assertNotFound();
});

it('refuse de sortir du dossier des emails', function () {
    Storage::fake('local');
    storeMail('20260101-120000-aaaaaaaa');

    // basename() coupe la remontée d'arborescence : le fichier visé n'existe
    // pas sous ce nom, la réponse est 404 et non le contenu d'un autre fichier.
    actingAs($this->admin)
        ->get(route('admin.mailbox.show', '..%2F..%2Fdatabase%2Fdatabase'))
        ->assertNotFound();
});

it('vide la boîte à la demande', function () {
    Storage::fake('local');
    storeMail('20260101-120000-aaaaaaaa');

    actingAs($this->admin)->delete(route('admin.mailbox.destroy'))->assertRedirect();

    expect(Storage::disk('local')->files(FileTransport::DIRECTORY))->toBeEmpty();
});
