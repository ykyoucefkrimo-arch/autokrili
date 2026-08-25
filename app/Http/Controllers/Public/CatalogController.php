<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\AgencyController;
use App\Models\Agency;
use App\Models\Commune;
use App\Models\PricingRule;
use App\Models\Vehicle;
use App\Models\Wilaya;
use App\Services\ReferenceDataService;
use App\Services\PublicListingService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of specification 7: home page, search results and vehicle
 * page. Everything it shows goes through PublicListingService, which is what
 * keeps the plan priorities from being re-implemented three times.
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly PublicListingService $listings,
        private readonly StatsService $stats,
        private readonly ReferenceDataService $reference,
    ) {
    }

    public function home(): Response
    {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'stats' => [
                'wilayas' => Wilaya::count(),
                'agencies' => Agency::where('status', Agency::STATUS_APPROVED)->count(),
                'vehicles' => Vehicle::visible()->count(),
            ],
            'featured' => $this->listings->homepageFeatured()->map(fn (Vehicle $v) => $this->card($v)),
            'latest' => $this->listings->latest()->map(fn (Vehicle $v) => $this->card($v)),
            // Only wilayas that actually have something to rent: a link that
            // lands on an empty result page costs more trust than it saves.
            'wilayas' => Wilaya::query()
                ->withCount(['vehicles' => fn ($q) => $q->where('status', Vehicle::STATUS_PUBLISHED)])
                ->whereHas('vehicles', fn ($q) => $q->where('status', Vehicle::STATUS_PUBLISHED))
                ->orderByDesc('vehicles_count')
                ->limit(8)
                ->get(['id', 'name_fr', 'slug']),
            'categories' => Vehicle::visible()
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->pluck('total', 'category'),
            'searchWilayas' => $this->reference->wilayas(),
        ]);
    }

    /**
     * Search results. The wilaya and commune may arrive either as path
     * segments — the SEO form of specification 7.2 — or as query parameters
     * when the visitor uses the filters.
     */
    public function search(Request $request, ?Wilaya $wilaya = null, ?Commune $commune = null): Response
    {
        // A commune of another wilaya in the URL is a broken link, not a search.
        abort_if($commune && $wilaya && $commune->wilaya_id !== $wilaya->id, 404);

        $filters = $request->only([
            'category', 'transmission', 'fuel', 'seats', 'air_conditioning',
            'with_driver', 'search', 'price_min', 'price_max', 'wilaya_id', 'commune_id',
        ]);

        $filters['wilaya_id'] = $wilaya?->id ?? ($filters['wilaya_id'] ?? null);
        $filters['commune_id'] = $commune?->id ?? ($filters['commune_id'] ?? null);

        $query = $this->listings->filter($this->listings->query(), $filters);
        $query = $this->listings->sort($query, $filters, $request->input('sort'));

        return Inertia::render('Public/Search', [
            'vehicles' => $query->paginate(12)->withQueryString()
                ->through(fn (Vehicle $v) => $this->card($v, $filters)),
            'filters' => $filters + ['sort' => $request->input('sort')],
            'place' => [
                'wilaya' => $wilaya?->only(['id', 'name_fr', 'slug']),
                'commune' => $commune?->only(['id', 'name_fr', 'slug']),
            ],
            'options' => [
                'categories' => Vehicle::CATEGORIES,
                'transmissions' => Vehicle::TRANSMISSIONS,
                'fuels' => Vehicle::FUELS,
                'sorts' => PublicListingService::SORTS,
            ],
            'wilayas' => $this->reference->wilayas(),
        ]);
    }

    /** The vehicle page. `{id}-{slug}` per specification 7.2. */
    public function show(Request $request, Vehicle $vehicle): Response
    {
        // Only what the public may legitimately see: a listing pulled from the
        // catalogue must stop answering, even to someone holding its URL.
        abort_unless(
            $vehicle->isPublished() && $vehicle->agency?->status === Agency::STATUS_APPROVED,
            404
        );

        $vehicle->load(['photos', 'pricingRules', 'agency.wilaya:id,name_fr',
            'agency.commune:id,name_fr', 'pickupWilaya:id,name_fr,slug', 'pickupCommune:id,name_fr,slug']);

        // Le compteur total sert au tri et a la fiche ; l'agregat journalier
        // alimente les statistiques de l'agence (§8).
        $vehicle->incrementQuietly('views_count');
        $this->stats->recordView($vehicle);

        return Inertia::render('Public/Vehicle', [
            'vehicle' => [
                'id' => $vehicle->id,
                'slug' => $vehicle->slug,
                'title' => $vehicle->title(),
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'category' => $vehicle->category,
                'transmission' => $vehicle->transmission,
                'fuel' => $vehicle->fuel,
                'seats' => $vehicle->seats,
                'doors' => $vehicle->doors,
                'air_conditioning' => $vehicle->air_conditioning,
                'mileage_limit_per_day' => $vehicle->mileage_limit_per_day,
                'description' => $vehicle->description,
                'with_driver_available' => $vehicle->with_driver_available,
                'driver_price_per_day' => $vehicle->driver_price_per_day,
                'pickup' => [
                    'commune' => $vehicle->pickupCommune?->name_fr,
                    'wilaya' => $vehicle->pickupWilaya?->name_fr,
                    'wilaya_slug' => $vehicle->pickupWilaya?->slug,
                ],
                'photos' => $vehicle->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => Storage::disk('public')->url($p->path),
                    'thumb' => Storage::disk('public')->url($p->path_thumb ?? $p->path),
                ]),
                'pricing' => $vehicle->pricingRules->map(fn (PricingRule $r) => [
                    'duration_type' => $r->duration_type,
                    'price_dzd' => $r->price_dzd,
                    'min_days' => $r->min_days,
                    // What the same period would cost at the daily rate, so the
                    // discount is visible instead of merely claimed.
                    'daily_equivalent' => (int) round($r->price_dzd / max(1, $r->unitDays())),
                ]),
                'agency' => [
                    'slug' => $vehicle->agency->slug,
                    'commercial_name' => $vehicle->agency->commercial_name,
                    'wilaya' => $vehicle->agency->wilaya?->name_fr,
                    'commune' => $vehicle->agency->commune?->name_fr,
                    'phone' => $vehicle->agency->phone,
                    'whatsapp' => $vehicle->agency->whatsapp,
                    'average_rating' => $vehicle->agency->average_rating,
                    'reviews_count' => $vehicle->agency->reviews_count,
                    'min_driver_age' => $vehicle->agency->min_driver_age,
                    'default_deposit_dzd' => $vehicle->agency->default_deposit_dzd,
                    'rental_conditions' => $vehicle->agency->rental_conditions,
                    'logo_url' => $vehicle->agency->logo_path
                        ? Storage::disk('public')->url($vehicle->agency->logo_path)
                        : null,
                ],
            ],
            // Donnees structurees Schema.org (§7.2) : les robots ne lisent pas
            // le Vue rendu par le navigateur, elles se construisent ici.
            'structuredData' => AgencyController::vehicleStructuredData($vehicle),
            // Same wilaya, same category first: what a visitor looks at next.
            'similar' => $this->listings->query()
                ->where('vehicles.id', '!=', $vehicle->id)
                ->where('pickup_wilaya_id', $vehicle->pickup_wilaya_id)
                ->orderByRaw('category = ? desc', [$vehicle->category])
                ->limit(4)
                ->get()
                ->map(fn (Vehicle $v) => $this->card($v)),
        ]);
    }

    /**
     * One shape of card for every public list. The "Sponsorisé" flag is set
     * here and not in the page: a listing lifted by its plan has to say so
     * (§4.2), and that must not depend on which template renders it.
     */
    private function card(Vehicle $vehicle, array $filters = []): array
    {
        $wilayaId = $filters['wilaya_id'] ?? null;
        $communeId = $filters['commune_id'] ?? null;

        $sponsored = ($wilayaId && (int) $vehicle->pickup_wilaya_id === (int) $wilayaId && $vehicle->plan_wilaya_priority)
            || ($communeId && (int) $vehicle->pickup_commune_id === (int) $communeId && $vehicle->plan_commune_priority);

        return [
            'id' => $vehicle->id,
            'slug' => $vehicle->slug,
            'title' => $vehicle->title(),
            'category' => $vehicle->category,
            'transmission' => $vehicle->transmission,
            'fuel' => $vehicle->fuel,
            'seats' => $vehicle->seats,
            'air_conditioning' => $vehicle->air_conditioning,
            'daily_price' => $vehicle->daily_price !== null ? (int) $vehicle->daily_price : null,
            'commune' => $vehicle->pickupCommune?->name_fr,
            'wilaya' => $vehicle->pickupWilaya?->name_fr,
            'agency' => $vehicle->agency?->commercial_name,
            'rating' => $vehicle->agency?->average_rating,
            'reviews_count' => $vehicle->agency?->reviews_count,
            'badge' => $vehicle->plan_badge,
            'badge_color' => $vehicle->plan_badge_color,
            'sponsored' => (bool) $sponsored,
            'cover_url' => $vehicle->coverPhoto
                ? Storage::disk('public')->url($vehicle->coverPhoto->path_card ?? $vehicle->coverPhoto->path)
                : null,
        ];
    }
}
