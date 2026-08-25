<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Commune;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Demonstration listings for the approved agencies: enough of them, and in
 * enough states, that the agency screens and the moderation queue both have
 * something real to show on a fresh install.
 *
 * The photos are plain coloured placeholders, generated here. Unlike a fake
 * trade register — which an administrator could mistake for a real document —
 * a flat rectangle cannot be mistaken for a photograph of a car, and without
 * any image at all the moderation preview would show every listing as
 * unpublishable.
 */
class VehicleSeeder extends Seeder
{
    /** [brand, model, year, category, transmission, fuel, seats, daily price] */
    private const MODELS = [
        ['Renault', 'Clio 5', 2023, 'citadine', 'manuelle', 'diesel', 5, 4500],
        ['Volkswagen', 'Golf 8', 2022, 'berline', 'automatique', 'essence', 5, 7000],
        ['Hyundai', 'Tucson', 2023, 'suv', 'automatique', 'diesel', 5, 9500],
        ['Toyota', 'Land Cruiser', 2021, '4x4', 'automatique', 'diesel', 7, 18000],
        ['Peugeot', '208', 2022, 'citadine', 'manuelle', 'essence', 5, 4200],
        ['Dacia', 'Logan', 2021, 'berline', 'manuelle', 'gpl', 5, 3800],
        ['Mercedes', 'Classe C', 2023, 'luxe', 'automatique', 'essence', 5, 22000],
        ['Fiat', 'Doblo', 2020, 'utilitaire', 'manuelle', 'diesel', 3, 5000],
        ['Kia', 'Picanto', 2023, 'citadine', 'automatique', 'essence', 4, 4000],
        ['Toyota', 'Hiace', 2019, 'minibus', 'manuelle', 'diesel', 15, 12000],
    ];

    /** Background colour per category, so a list of cards is readable at a glance. */
    private const COLOURS = [
        'citadine' => '#2563eb', 'berline' => '#0f766e', 'suv' => '#7c3aed',
        'utilitaire' => '#b45309', '4x4' => '#166534', 'luxe' => '#111827',
        'minibus' => '#be123c',
    ];

    public function run(): void
    {
        $agencies = Agency::where('status', Agency::STATUS_APPROVED)->with('user')->get();

        foreach ($agencies as $index => $agency) {
            $plan = $agency->currentPlan();
            // Never more than the plan allows: a seeder that starts every
            // demonstration account over quota teaches the wrong lesson.
            $count = min($plan?->max_listings ?? 4, 4);

            for ($i = 0; $i < $count; $i++) {
                $spec = self::MODELS[($index * 3 + $i) % count(self::MODELS)];
                $this->createVehicle($agency, $spec, $this->statusFor($i), $plan?->max_photos ?? 1);
            }
        }
    }

    /**
     * One listing of each state per agency: published listings for the public
     * pages, one pending so the moderation queue is not empty, one rejected so
     * the agency sees what a refusal looks like.
     */
    private function statusFor(int $i): string
    {
        return match ($i) {
            0, 1 => Vehicle::STATUS_PUBLISHED,
            2 => Vehicle::STATUS_PENDING,
            default => Vehicle::STATUS_REJECTED,
        };
    }

    private function createVehicle(Agency $agency, array $spec, string $status, int $maxPhotos): void
    {
        [$brand, $model, $year, $category, $transmission, $fuel, $seats, $daily] = $spec;

        $commune = Commune::where('wilaya_id', $agency->wilaya_id)->inRandomOrder()->first()
            ?? Commune::where('wilaya_id', $agency->wilaya_id)->first();

        $slug = Str::slug("{$brand}-{$model}-{$year}-{$agency->id}");

        $vehicle = Vehicle::updateOrCreate(
            ['slug' => $slug],
            [
                'agency_id' => $agency->id,
                'brand' => $brand,
                'model' => $model,
                'year' => $year,
                'category' => $category,
                'transmission' => $transmission,
                'fuel' => $fuel,
                'seats' => $seats,
                'doors' => $seats > 7 ? 4 : 5,
                'air_conditioning' => true,
                'mileage_limit_per_day' => 200,
                'description' => "{$brand} {$model} {$year} en excellent état, entretien à jour. "
                    ."Carburant {$fuel}, boîte {$transmission}. Caution et pièce d'identité demandées au départ.",
                'pickup_wilaya_id' => $agency->wilaya_id,
                'pickup_commune_id' => $commune?->id ?? $agency->commune_id,
                'with_driver_available' => $category === 'minibus' || $category === 'luxe',
                'driver_price_per_day' => ($category === 'minibus' || $category === 'luxe') ? 3500 : 0,
                'status' => $status,
                'rejection_reason' => $status === Vehicle::STATUS_REJECTED
                    ? 'Photos de mauvaise qualité ou non conformes — les photos ne montrent pas le véhicule entier.'
                    : null,
                'views_count' => $status === Vehicle::STATUS_PUBLISHED ? random_int(20, 900) : 0,
                'published_at' => $status === Vehicle::STATUS_PUBLISHED ? now()->subDays(random_int(1, 60)) : null,
            ]
        );

        $this->seedPricing($vehicle, $daily);
        $this->seedPhotos($vehicle, min($maxPhotos, 3));
    }

    private function seedPricing(Vehicle $vehicle, int $daily): void
    {
        // Degressive: a week costs six days, a month twenty-four.
        foreach ([
            PricingRule::DAILY => $daily,
            PricingRule::WEEKLY => $daily * 6,
            PricingRule::MONTHLY => $daily * 24,
        ] as $type => $price) {
            $vehicle->pricingRules()->updateOrCreate(
                ['duration_type' => $type],
                ['price_dzd' => $price, 'min_days' => PricingRule::UNIT_DAYS[$type]]
            );
        }
    }

    private function seedPhotos(Vehicle $vehicle, int $count): void
    {
        if ($vehicle->photos()->exists()) {
            return;
        }

        $colour = self::COLOURS[$vehicle->category] ?? '#334155';

        for ($i = 0; $i < $count; $i++) {
            $basename = Str::uuid()->toString();
            $paths = [];

            foreach (['path' => 1600, 'path_card' => 800, 'path_thumb' => 240] as $column => $edge) {
                $image = Image::createImage($edge, (int) round($edge * 0.75))->fill($colour);
                $path = "vehicles/{$vehicle->id}/{$basename}-{$edge}.webp";
                Storage::disk('public')->put($path, (string) $image->encode(new WebpEncoder(quality: 80)));
                $paths[$column] = $path;
            }

            $vehicle->photos()->create($paths + [
                'sort_order' => $i + 1,
                'is_cover' => $i === 0,
            ]);
        }
    }
}
