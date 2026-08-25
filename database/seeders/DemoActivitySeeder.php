<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\ListingStat;
use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demonstration activity (specification 13): thirty days of views, a handful of
 * bookings across the statuses, and reviews.
 *
 * Without it the statistics screens open on zeros, and a chart of zeros teaches
 * nothing about whether the chart works.
 *
 * The figures are plausible rather than random: views follow a weekly rhythm —
 * Algerian weekends fall on Friday and Saturday — and the busier plans get more
 * of them, which is what the plan priorities are supposed to produce.
 */
class DemoActivitySeeder extends Seeder
{
    private const COMMENTS = [
        5 => ['Voiture impeccable, agence très réactive.', 'Rien à redire, je recommande.',
            'Accueil chaleureux et véhicule propre.'],
        4 => ['Bonne expérience, petit retard au départ.', 'Véhicule conforme à l’annonce.'],
        3 => ['Correct sans plus. La voiture méritait un lavage.',
            'Prix honnête, mais accueil un peu froid.'],
        2 => ['Attente longue à l’agence, véhicule livré avec peu de carburant.'],
    ];

    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function run(): void
    {
        $agencies = Agency::where('status', Agency::STATUS_APPROVED)
            ->with(['vehicles' => fn ($q) => $q->where('status', Vehicle::STATUS_PUBLISHED)])
            ->get();

        foreach ($agencies as $agency) {
            $this->seedStats($agency);
            $this->seedBookings($agency);
        }

        $this->seedPlanRequest();
        $this->reviews->recomputeAll();

        $this->command?->info(sprintf(
            'Démonstration : %d lignes de statistiques, %d réservations, %d avis.',
            ListingStat::count(),
            Booking::count(),
            Review::count()
        ));
    }

    /**
     * Une demande de formule en attente, pour que la file du back office ait
     * quelque chose à montrer sur une installation neuve (§13).
     */
    private function seedPlanRequest(): void
    {
        $agency = Agency::whereHas('subscriptions', fn ($q) => $q
            ->where('status', 'active')
            ->whereIn('plan_id', Plan::where('slug', Plan::SILVER)->select('id')))
            ->first();

        if (! $agency || $agency->planChangeRequests()->where('status', 'pending')->exists()) {
            return;
        }

        $agency->planChangeRequests()->create([
            'requested_plan_id' => Plan::where('slug', Plan::GOLD)->value('id'),
            'status' => \App\Models\PlanChangeRequest::STATUS_PENDING,
            'agency_message' => 'Nous avons cinq véhicules supplémentaires à publier avant la saison.',
        ]);
    }

    /** Thirty days of daily counters per published listing. */
    private function seedStats(Agency $agency): void
    {
        // Une formule qui paie la priorité doit se voir dans les chiffres,
        // sinon la démonstration contredit l'argument de vente.
        $appetite = match ($agency->currentPlan()?->slug) {
            Plan::PLATINIUM => 3.0,
            Plan::GOLD => 1.8,
            default => 1.0,
        };

        foreach ($agency->vehicles as $vehicle) {
            for ($day = 29; $day >= 0; $day--) {
                $date = now()->subDays($day);
                // Vendredi et samedi : le week-end algérien, la demande retombe.
                $weekend = in_array($date->dayOfWeek, [5, 6], true);

                $views = (int) round(random_int(2, 14) * $appetite * ($weekend ? 0.5 : 1));

                ListingStat::updateOrCreate(
                    ['vehicle_id' => $vehicle->id, 'date' => $date->toDateString()],
                    [
                        'agency_id' => $agency->id,
                        'views' => $views,
                        // Un contact pour une dizaine de vues, une demande pour
                        // trois contacts : des ordres de grandeur realistes.
                        'contact_clicks' => (int) round($views / random_int(8, 14)),
                        'bookings_count' => random_int(0, 100) < 12 ? 1 : 0,
                    ]
                );
            }
        }
    }

    /** A few bookings per agency, spread across the statuses of §6.2. */
    private function seedBookings(Agency $agency): void
    {
        $vehicles = $agency->vehicles;

        if ($vehicles->isEmpty()) {
            return;
        }

        $statuses = [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_PENDING,
        ];

        foreach ($statuses as $index => $status) {
            $vehicle = $vehicles[$index % $vehicles->count()];

            // Les terminées dans le passé, les autres devant : une réservation
            // confirmée pour la semaine dernière n'aurait aucun sens.
            $start = $status === Booking::STATUS_COMPLETED
                ? now()->subDays(random_int(10, 40))
                : now()->addDays(random_int(3, 25));

            $days = random_int(2, 6);
            $end = $start->copy()->addDays($days - 1);

            $client = $this->client($index);
            $daily = $vehicle->pricingRules->firstWhere('duration_type', PricingRule::DAILY)?->price_dzd ?? 4500;

            $reference = $this->nextReference($start->year);

            $booking = Booking::updateOrCreate(
                ['booking_reference' => $reference],
                [
                    'vehicle_id' => $vehicle->id,
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'total_days' => $days,
                    'pickup_location' => $vehicle->pickupCommune?->name_fr,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone,
                    'client_email' => $client->email,
                    'vehicle_price_dzd' => $daily * $days,
                    'total_price_dzd' => $daily * $days,
                    'deposit_dzd' => $agency->default_deposit_dzd,
                    'price_breakdown' => [[
                        'label' => 'Journée', 'quantity' => $days,
                        'unit_price' => $daily, 'total' => $daily * $days,
                    ]],
                    'status' => $status,
                    'confirmed_at' => $status === Booking::STATUS_PENDING ? null : $start->copy()->subDays(2),
                    'completed_at' => $status === Booking::STATUS_COMPLETED ? $end->copy()->addHours(10) : null,
                    'expires_at' => $status === Booking::STATUS_PENDING ? now()->addDay() : null,
                    'created_at' => $start->copy()->subDays(3),
                ]
            );

            if ($status === Booking::STATUS_COMPLETED) {
                $this->seedReview($booking);
            }
        }
    }

    private function seedReview(Booking $booking): void
    {
        if ($booking->review()->exists()) {
            return;
        }

        $rating = [5, 5, 4, 4, 3, 2][random_int(0, 5)];

        $booking->review()->create([
            'agency_id' => $booking->agency_id,
            'client_id' => $booking->client_id,
            'rating' => $rating,
            'comment' => self::COMMENTS[$rating][array_rand(self::COMMENTS[$rating])],
            // La plupart publiés, un sur quatre laissé en modération pour que
            // l'écran d'administration ait quelque chose à montrer.
            'status' => random_int(0, 3) === 0 ? Review::STATUS_PENDING : Review::STATUS_APPROVED,
            'created_at' => $booking->completed_at?->copy()->addDay(),
        ]);

        $booking->update(['review_invited_at' => $booking->completed_at?->copy()->addDay()]);
    }

    private function client(int $index): User
    {
        $names = ['Nadir Belkacem', 'Amel Ferhat', 'Sofiane Bouzid', 'Lynda Ait Ali',
            'Hakim Zerrouki', 'Meriem Slimani'];
        $name = $names[$index % count($names)];
        $email = Str::slug($name, '.').'@demo.dz';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'phone' => '0555'.str_pad((string) (100000 + $index * 137), 6, '0', STR_PAD_LEFT),
                'role' => User::ROLE_CLIENT,
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles([User::ROLE_CLIENT]);

        return $user;
    }

    private function nextReference(int $year): string
    {
        $last = Booking::where('booking_reference', 'like', "DZ-{$year}-%")
            ->orderByDesc('booking_reference')
            ->value('booking_reference');

        return sprintf('DZ-%d-%05d', $year, $last ? ((int) substr($last, -5)) + 1 : 1);
    }
}
