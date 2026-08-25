<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Initial values of the matrix in specification 4.1. They are seeded, not
 * hard-coded: the administrator edits them afterwards without a deployment.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Silver',
                'slug' => Plan::SILVER,
                'price_dzd' => 0,
                'max_listings' => 5,
                'max_photos' => 1,
                'max_users' => 1,
                'has_commune_priority' => false,
                'has_wilaya_priority' => false,
                'has_homepage_feature' => false,
                'can_reply_reviews' => false,
                'stats_level' => 'basic',
                'badge_label' => null,
                'badge_color' => null,
                'description' => 'Pour démarrer : 5 annonces, 1 photo par annonce, visibilité sur tout le répertoire.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Gold',
                'slug' => Plan::GOLD,
                'price_dzd' => 12000,
                'max_listings' => 20,
                'max_photos' => 5,
                'max_users' => 3,
                'has_commune_priority' => true,
                'has_wilaya_priority' => false,
                'has_homepage_feature' => false,
                'can_reply_reviews' => true,
                'stats_level' => 'advanced',
                'badge_label' => 'Gold',
                'badge_color' => '#D4AF37',
                'description' => '20 annonces, 5 photos, priorité sur votre commune et réponse aux avis.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Platinium',
                'slug' => Plan::PLATINIUM,
                // NULL, not a large number: unlimited must never be a value a
                // user could reach or a comparison could silently cap.
                'max_listings' => null,
                'price_dzd' => 28000,
                'max_photos' => 10,
                'max_users' => 10,
                'has_commune_priority' => true,
                'has_wilaya_priority' => true,
                'has_homepage_feature' => true,
                'can_reply_reviews' => true,
                'stats_level' => 'premium',
                'badge_label' => 'Platinium',
                'badge_color' => '#5B21B6',
                'description' => 'Annonces illimitées, 10 photos, priorité wilaya et mise en avant sur la page d’accueil.',
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
