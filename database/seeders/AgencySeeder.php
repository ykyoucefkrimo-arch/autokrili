<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Commune;
use App\Models\Plan;
use App\Models\User;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Five demonstration agencies spread over the three plans (specification 13),
 * plus one still pending and one rejected so the moderation screens have
 * something to show on a fresh install.
 */
class AgencySeeder extends Seeder
{
    /**
     * Coordonnées du chef-lieu de chaque agence, par code wilaya.
     *
     * Sans elles la fiche publique n'affiche aucune carte : le composant ne
     * s'affiche que si l'agence a réellement posé son point (§7.1), et une
     * démonstration sans carte donnerait à croire qu'elle n'existe pas.
     */
    private const POSITIONS = [
        '16' => [36.7166, 3.1833],   // Bab Ezzouar
        '31' => [35.7000, -0.6167],  // Bir El Djir
        '25' => [36.2631, 6.6947],   // El Khroub
        '19' => [36.1531, 5.6906],   // El Eulma
        '23' => [36.8500, 7.7000],   // El Bouni
        '06' => [36.4589, 4.5389],   // Akbou
        '13' => [34.8783, -1.3150],  // Mansourah
    ];

    /** Horaires courants d'une agence algérienne : fermée le vendredi. */
    private const HOURS = [
        'sunday' => ['closed' => false, 'from' => '08:00', 'to' => '18:00'],
        'monday' => ['closed' => false, 'from' => '08:00', 'to' => '18:00'],
        'tuesday' => ['closed' => false, 'from' => '08:00', 'to' => '18:00'],
        'wednesday' => ['closed' => false, 'from' => '08:00', 'to' => '18:00'],
        'thursday' => ['closed' => false, 'from' => '08:00', 'to' => '18:00'],
        'friday' => ['closed' => true, 'from' => '08:00', 'to' => '18:00'],
        'saturday' => ['closed' => false, 'from' => '09:00', 'to' => '16:00'],
    ];

    /** [commercial_name, manager, wilaya code, commune, plan slug, status] */
    private const AGENCIES = [
        ['Alger Prestige Cars', 'Karim Belhadi', '16', 'Bab Ezzouar', Plan::PLATINIUM, Agency::STATUS_APPROVED],
        ['Oran Auto Location', 'Nadia Cherif', '31', 'Bir El Djir', Plan::GOLD, Agency::STATUS_APPROVED],
        ['Constantine Rent', 'Yacine Meziane', '25', 'El Khroub', Plan::GOLD, Agency::STATUS_APPROVED],
        ['Sétif Drive', 'Samir Boudjemaa', '19', 'El Eulma', Plan::SILVER, Agency::STATUS_APPROVED],
        ['Annaba Wheels', 'Lamia Bensalem', '23', 'El Bouni', Plan::SILVER, Agency::STATUS_APPROVED],
        ['Béjaïa Car Services', 'Rachid Amrani', '06', 'Akbou', null, Agency::STATUS_PENDING],
        ['Tlemcen Location Express', 'Fouad Berrahal', '13', 'Mansourah', null, Agency::STATUS_REJECTED],
    ];

    public function run(): void
    {
        foreach (self::AGENCIES as $index => [$name, $manager, $wilayaCode, $communeName, $planSlug, $status]) {
            $wilaya = Wilaya::where('code', $wilayaCode)->firstOrFail();
            $commune = Commune::where('wilaya_id', $wilaya->id)
                ->where('name_fr', $communeName)
                ->firstOrFail();

            $email = Str::slug($name, '.').'@demo.dz';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $manager,
                    'password' => Hash::make('password'),
                    'phone' => '+213 5 55 '.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT).' 20 30',
                    'role' => User::ROLE_AGENCY,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([User::ROLE_AGENCY]);

            $agency = Agency::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'commercial_name' => $name,
                    'slug' => Str::slug($name),
                    'manager_name' => $manager,
                    'trade_register_number' => '16/00-'.(1000000 + $index * 137),
                    'nif' => '0009'.(16000000 + $index * 4211),
                    'wilaya_id' => $wilaya->id,
                    'commune_id' => $commune->id,
                    'address' => ($index + 3).' rue des Frères Bouadou',
                    'phone' => $user->phone,
                    'whatsapp' => $user->phone,
                    'description' => "Agence de location basée à {$communeName}. Véhicules récents, entretien suivi, livraison possible.",
                    // No file on disk: the seeded document would be a fake the
                    // administrator could open and mistake for a real register.
                    'trade_register_file' => null,
                    'status' => $status,
                    'approved_at' => $status === Agency::STATUS_APPROVED ? now()->subDays(30 - $index) : null,
                    'rejection_reason' => $status === Agency::STATUS_REJECTED
                        ? 'Le registre de commerce transmis est illisible. Merci de le renvoyer en PDF net.'
                        : null,
                    'is_trusted' => $planSlug === Plan::PLATINIUM,
                    'default_deposit_dzd' => 20000 + $index * 5000,
                    'min_driver_age' => 21 + ($index % 3),
                    'buffer_hours' => 4,
                    'latitude' => self::POSITIONS[$wilayaCode][0] ?? null,
                    'longitude' => self::POSITIONS[$wilayaCode][1] ?? null,
                    'opening_hours' => self::HOURS,
                    'rental_conditions' => "Permis de conduire de plus de deux ans et pièce d'identité "
                        ."exigés au départ. Le véhicule est rendu avec le même niveau de carburant. "
                        ."Circulation hors du territoire national interdite sans accord écrit.",
                ]
            );

            if ($planSlug && ! $agency->activeSubscription()->exists()) {
                $agency->subscriptions()->create([
                    'plan_id' => Plan::where('slug', $planSlug)->firstOrFail()->id,
                    'starts_at' => now()->subDays(30)->toDateString(),
                    // Silver is the free fallback and never expires; the paid
                    // plans carry a date so the expiry job has something to act on.
                    'ends_at' => $planSlug === Plan::SILVER ? null : now()->addMonths(3)->toDateString(),
                    'status' => 'active',
                    'admin_note' => $planSlug === Plan::SILVER ? null : 'Versement reçu — abonnement de démonstration.',
                ]);
            }
        }
    }
}
