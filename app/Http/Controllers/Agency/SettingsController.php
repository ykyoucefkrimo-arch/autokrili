<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\SettingsRequest;
use App\Models\Agency;
use App\Models\Wilaya;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Les jours dans l'ordre de la semaine algérienne, dimanche ouvré. */
    public const DAYS = [
        'sunday' => 'Dimanche',
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
    ];

    public function __construct(private readonly ReferenceDataService $reference)
    {
    }

    public function edit(Request $request): Response
    {
        $agency = $request->user()->activeAgency();

        return Inertia::render('Agency/Settings', [
            'agency' => [
                'commercial_name' => $agency->commercial_name,
                'manager_name' => $agency->manager_name,
                'trade_register_number' => $agency->trade_register_number,
                'nif' => $agency->nif,
                'wilaya_id' => $agency->wilaya_id,
                'commune_id' => $agency->commune_id,
                'address' => $agency->address,
                'phone' => $agency->phone,
                'whatsapp' => $agency->whatsapp,
                'description' => $agency->description,
                'latitude' => $agency->latitude !== null ? (float) $agency->latitude : null,
                'longitude' => $agency->longitude !== null ? (float) $agency->longitude : null,
                'rental_conditions' => $agency->rental_conditions,
                'min_driver_age' => $agency->min_driver_age,
                'default_deposit_dzd' => $agency->default_deposit_dzd,
                'buffer_hours' => $agency->buffer_hours,
                'opening_hours' => $this->normaliseHours($agency->opening_hours),
                'logo_url' => $agency->logo_path ? Storage::disk('public')->url($agency->logo_path) : null,
                'slug' => $agency->slug,
                'public_url' => route('agency.public', $agency->slug),
            ],
            'wilayas' => $this->reference->wilayas(),
            'days' => self::DAYS,
            // Le repère de la carte quand l'agence n'a pas encore de position :
            // le chef-lieu de sa wilaya, qui est au pire à quelques kilomètres.
            'fallbackPosition' => [
                'latitude' => $agency->wilaya?->latitude !== null ? (float) $agency->wilaya->latitude : 36.7538,
                'longitude' => $agency->wilaya?->longitude !== null ? (float) $agency->wilaya->longitude : 3.0588,
            ],
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $agency = $request->user()->activeAgency();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            // L'ancien logo n'a plus de raison d'occuper le disque : il est
            // remplacé partout où il s'affichait.
            if ($agency->logo_path) {
                Storage::disk('public')->delete($agency->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('agencies/logos', 'public');
        }

        unset($data['logo']);
        $data['opening_hours'] = $this->cleanHours($data['opening_hours'] ?? []);

        $agency->update($data);

        return back()->with('success', 'Paramètres enregistrés.');
    }

    /**
     * Toujours les sept jours, dans l'ordre : un tableau à trous obligerait la
     * page à réinventer les jours manquants.
     */
    private function normaliseHours(?array $stored): array
    {
        $hours = [];

        foreach (self::DAYS as $key => $label) {
            $day = $stored[$key] ?? [];

            $hours[$key] = [
                'closed' => (bool) ($day['closed'] ?? false),
                'from' => $day['from'] ?? '08:00',
                'to' => $day['to'] ?? '18:00',
            ];
        }

        return $hours;
    }

    /**
     * Un jour fermé garde ses horaires en base mais ne les montre pas : si
     * l'agence rouvre le vendredi, elle retrouve ceux qu'elle avait saisis.
     */
    private function cleanHours(array $hours): array
    {
        $clean = [];

        foreach (self::DAYS as $key => $label) {
            if (! isset($hours[$key])) {
                continue;
            }

            $clean[$key] = [
                'closed' => (bool) ($hours[$key]['closed'] ?? false),
                'from' => $hours[$key]['from'] ?? '08:00',
                'to' => $hours[$key]['to'] ?? '18:00',
            ];
        }

        return $clean;
    }
}
