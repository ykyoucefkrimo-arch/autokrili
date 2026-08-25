<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data first: everything else points at it.
        $this->call([
            RoleSeeder::class,
            WilayaSeeder::class,
            // Complete le decoupage : 69 wilayas et 1541 communes, importees
            // depuis database/data/cities.json.
            AlgeriaDivisionsSeeder::class,
            PlanSeeder::class,
            AdminSeeder::class,
            AgencySeeder::class,
            VehicleSeeder::class,
            // Trente jours de statistiques, quelques reservations et avis (§13) :
            // un graphique de zeros n'apprend rien sur le graphique.
            DemoActivitySeeder::class,
        ]);
    }
}
