<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Agency\SettingsController;
use App\Models\Agency;
use App\Models\PricingRule;
use App\Models\Review;
use App\Models\Vehicle;
use App\Services\PublicListingService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public agency page (specification 7.1): who they are, where they are,
 * and everything they currently have to rent.
 */
class AgencyController extends Controller
{
    public function __construct(private readonly PublicListingService $listings)
    {
    }

    public function show(Agency $agency): Response
    {
        // A suspended or pending agency has no public page: its listings are
        // hidden, and a page advertising them would contradict that.
        abort_unless($agency->status === Agency::STATUS_APPROVED, 404);

        $agency->load(['wilaya:id,name_fr,slug', 'commune:id,name_fr']);

        $vehicles = $this->listings->query()
            ->where('vehicles.agency_id', $agency->id)
            ->orderByDesc('published_at')
            ->paginate(12)
            ->through(fn (Vehicle $v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'title' => $v->title(),
                'category' => $v->category,
                'transmission' => $v->transmission,
                'fuel' => $v->fuel,
                'seats' => $v->seats,
                'air_conditioning' => $v->air_conditioning,
                'daily_price' => $v->daily_price !== null ? (int) $v->daily_price : null,
                'commune' => $v->pickupCommune?->name_fr,
                'wilaya' => $v->pickupWilaya?->name_fr,
                'agency' => $agency->commercial_name,
                'rating' => $agency->average_rating,
                'reviews_count' => $agency->reviews_count,
                'badge' => $v->plan_badge,
                'badge_color' => $v->plan_badge_color,
                'sponsored' => false,
                'cover_url' => $v->coverPhoto
                    ? Storage::disk('public')->url($v->coverPhoto->path_card ?? $v->coverPhoto->path)
                    : null,
            ]);

        $cheapest = $this->listings->query()
            ->where('vehicles.agency_id', $agency->id)
            ->reorder()
            ->get()
            ->pluck('daily_price')
            ->filter()
            ->min();

        return Inertia::render('Public/Agency', [
            'agency' => [
                'id' => $agency->id,
                'slug' => $agency->slug,
                'commercial_name' => $agency->commercial_name,
                'description' => $agency->description,
                'address' => $agency->address,
                'commune' => $agency->commune?->name_fr,
                'wilaya' => $agency->wilaya?->name_fr,
                'wilaya_slug' => $agency->wilaya?->slug,
                'phone' => $agency->phone,
                'whatsapp' => $agency->whatsapp,
                'latitude' => $agency->latitude !== null ? (float) $agency->latitude : null,
                'longitude' => $agency->longitude !== null ? (float) $agency->longitude : null,
                'average_rating' => (float) $agency->average_rating,
                'reviews_count' => $agency->reviews_count,
                'min_driver_age' => $agency->min_driver_age,
                'default_deposit_dzd' => $agency->default_deposit_dzd,
                'rental_conditions' => $agency->rental_conditions,
                'opening_hours' => $this->readableHours($agency->opening_hours),
                'logo_url' => $agency->logo_path ? Storage::disk('public')->url($agency->logo_path) : null,
                'member_since' => $agency->approved_at?->translatedFormat('F Y'),
                'vehicles_count' => $vehicles->total(),
                'cheapest_price' => $cheapest ? (int) $cheapest : null,
            ],
            'vehicles' => $vehicles,
            // Seuls les avis approuves : la moderation passe avant la
            // publication, pas apres une plainte (§9).
            'reviews' => $agency->reviews()
                ->where('status', Review::STATUS_APPROVED)
                ->with('client:id,name')
                ->latest()
                ->take(20)
                ->get()
                ->map(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    // Prenom et initiale : le nom complet d'un client n'a rien
                    // a faire sur une page publique indexee.
                    'author' => $this->initials($review->client?->name),
                    'agency_reply' => $review->agency_reply,
                    'created_at' => $review->created_at->translatedFormat('F Y'),
                ]),
            'ratingDistribution' => $agency->reviews()
                ->where('status', Review::STATUS_APPROVED)
                ->selectRaw('rating, count(*) total')
                ->groupBy('rating')
                ->pluck('total', 'rating'),
            // Schema.org LocalBusiness (§7.2), construit côté serveur : les
            // robots ne lisent pas le Vue rendu par le navigateur.
            'structuredData' => $this->structuredData($agency, $cheapest),
        ]);
    }

    /** « Yacine H. » : de quoi reconnaitre un avis sans exposer un nom entier. */
    private function initials(?string $name): string
    {
        if (! $name) {
            return 'Client';
        }

        $parts = preg_split('/\s+/', trim($name));
        $first = array_shift($parts);

        return $parts === []
            ? $first
            : $first.' '.mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)).'.';
    }

    /** @return array<int, array{label: string, hours: string}> */
    private function readableHours(?array $stored): array
    {
        if (! $stored) {
            return [];
        }

        $readable = [];

        foreach (SettingsController::DAYS as $key => $label) {
            if (! isset($stored[$key])) {
                continue;
            }

            $day = $stored[$key];

            $readable[] = [
                'label' => $label,
                // `__()` lit les memes lang/*.json que le navigateur : un
                // libelle construit cote serveur doit suivre la langue lui aussi.
                'hours' => ($day['closed'] ?? false)
                    ? __('Fermé')
                    : ($day['from'] ?? '08:00').' – '.($day['to'] ?? '18:00'),
            ];
        }

        return $readable;
    }

    /** @return array<string, mixed> */
    private function structuredData(Agency $agency, ?int $cheapest): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'AutoRental',
            'name' => $agency->commercial_name,
            'telephone' => $agency->phone,
            'url' => route('agency.public', $agency->slug),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $agency->address,
                'addressLocality' => $agency->commune?->name_fr,
                'addressRegion' => $agency->wilaya?->name_fr,
                'addressCountry' => 'DZ',
            ]),
        ];

        if ($agency->description) {
            $data['description'] = $agency->description;
        }

        if ($agency->logo_path) {
            $data['image'] = Storage::disk('public')->url($agency->logo_path);
        }

        if ($agency->latitude !== null && $agency->longitude !== null) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $agency->latitude,
                'longitude' => (float) $agency->longitude,
            ];
        }

        // Une note agrégée sans avis serait une étoile inventée : Google la
        // sanctionne, et le client la découvre en cliquant.
        if ($agency->reviews_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $agency->average_rating,
                'reviewCount' => $agency->reviews_count,
                'bestRating' => 5,
            ];
        }

        if ($cheapest) {
            $data['priceRange'] = "À partir de {$cheapest} DZD / jour";
        }

        return $data;
    }

    /** Shared with the vehicle page: one Car object per listing. */
    public static function vehicleStructuredData(Vehicle $vehicle): array
    {
        $daily = $vehicle->pricingRules->firstWhere('duration_type', PricingRule::DAILY);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Car',
            'name' => $vehicle->title(),
            'brand' => ['@type' => 'Brand', 'name' => $vehicle->brand],
            'model' => $vehicle->model,
            'vehicleModelDate' => (string) $vehicle->year,
            'vehicleTransmission' => $vehicle->transmission,
            'fuelType' => $vehicle->fuel,
            'seatingCapacity' => $vehicle->seats,
            'numberOfDoors' => $vehicle->doors,
            'url' => route('vehicle.show', ['vehicle' => $vehicle->id, 'slug' => $vehicle->slug]),
        ];

        if ($vehicle->description) {
            $data['description'] = $vehicle->description;
        }

        $photos = $vehicle->photos->map(fn ($p) => Storage::disk('public')->url($p->path))->all();
        if ($photos !== []) {
            $data['image'] = $photos;
        }

        if ($daily) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => $daily->price_dzd,
                'priceCurrency' => 'DZD',
                'availability' => 'https://schema.org/InStock',
                'seller' => [
                    '@type' => 'AutoRental',
                    'name' => $vehicle->agency->commercial_name,
                    'url' => route('agency.public', $vehicle->agency->slug),
                ],
            ];
        }

        return $data;
    }
}
