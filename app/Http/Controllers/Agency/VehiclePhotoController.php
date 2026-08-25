<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use App\Services\ListingQuotaService;
use App\Services\VehiclePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class VehiclePhotoController extends Controller
{
    public function __construct(
        private readonly VehiclePhotoService $photos,
        private readonly ListingQuotaService $quotas,
    ) {
    }

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            // 8 Mo covers a phone photo without letting a raw export through.
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'photos.*.image' => 'Chaque fichier doit être une image (JPG, PNG ou WebP).',
            'photos.*.max' => 'Chaque photo doit peser moins de 8 Mo.',
        ]);

        $files = $request->file('photos');

        // Checked before storing anything: uploading four photos and keeping
        // the first one because the quota is one would be worse than refusing.
        if (! $this->quotas->canAddPhoto($vehicle, count($files))) {
            return back()->withErrors([
                'photos' => $this->quotas->photoLimitMessage($vehicle->agency)
                    .' Il vous reste '.$this->quotas->remainingPhotos($vehicle).' emplacement(s).',
            ]);
        }

        try {
            foreach ($files as $file) {
                $this->photos->add($vehicle, $file);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['photos' => $e->getMessage()]);
        }

        return back()->with('success', count($files) > 1 ? 'Photos ajoutées.' : 'Photo ajoutée.');
    }

    public function destroy(Vehicle $vehicle, VehiclePhoto $photo): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        abort_unless($photo->vehicle_id === $vehicle->id, 404);

        $this->photos->delete($photo);

        return back()->with('success', 'Photo supprimée.');
    }

    public function cover(Vehicle $vehicle, VehiclePhoto $photo): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        abort_unless($photo->vehicle_id === $vehicle->id, 404);

        $this->photos->setCover($photo);

        return back()->with('success', 'Photo de couverture mise à jour.');
    }

    public function reorder(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Only ids that belong to this listing survive: a forged payload must
        // not renumber a competitor's gallery.
        $owned = $vehicle->photos()->pluck('id')->all();
        $this->photos->reorder($vehicle, array_values(array_intersect($data['ids'], $owned)));

        return back();
    }
}
