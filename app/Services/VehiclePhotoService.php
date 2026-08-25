<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

/**
 * Photo storage for the listings. Three derivatives are written at upload
 * rather than resized on request: the search page shows dozens of cards at
 * once, and a connection in Algeria is the wrong place to send a 4 MB photo
 * straight from a phone camera.
 */
class VehiclePhotoService
{
    /** Longest edge of each derivative, in pixels. */
    private const SIZES = [
        'path' => 1600,   // full view on the vehicle page
        'path_card' => 800,    // search result card
        'path_thumb' => 240,    // gallery strip and dashboard list
    ];

    public function __construct(private readonly ListingQuotaService $quotas)
    {
    }

    /**
     * Stores one photo and its derivatives. The quota is checked here and not
     * only in the request: an upload arriving by any other route must hit the
     * same wall.
     */
    public function add(Vehicle $vehicle, UploadedFile $file): VehiclePhoto
    {
        if (! $this->quotas->canAddPhoto($vehicle)) {
            throw new RuntimeException($this->quotas->photoLimitMessage($vehicle->agency));
        }

        $basename = Str::uuid()->toString();
        $directory = "vehicles/{$vehicle->id}";
        $paths = [];

        foreach (self::SIZES as $column => $edge) {
            $encoded = Image::decodeSplFileInfo($file)
                // scaleDown never enlarges: a small photo stays small rather
                // than being blown up into blur.
                ->scaleDown(width: $edge)
                // WebP for every derivative: a third of the bytes of the JPEG
                // the agency uploaded, for the same picture on screen.
                ->encode(new WebpEncoder(quality: 82));

            $path = "{$directory}/{$basename}-{$edge}.webp";
            Storage::disk('public')->put($path, (string) $encoded);
            $paths[$column] = $path;
        }

        return DB::transaction(function () use ($vehicle, $paths) {
            $isFirst = $vehicle->photos()->count() === 0;

            return $vehicle->photos()->create($paths + [
                'sort_order' => (int) $vehicle->photos()->max('sort_order') + 1,
                // The first photo becomes the cover on its own: a listing with
                // photos but no cover would show a blank card.
                'is_cover' => $isFirst,
            ]);
        });
    }

    public function delete(VehiclePhoto $photo): void
    {
        $vehicle = $photo->vehicle;

        DB::transaction(function () use ($photo, $vehicle) {
            $wasCover = $photo->is_cover;
            $this->deleteFiles($photo);
            $photo->delete();

            if ($wasCover) {
                $vehicle->photos()->orderBy('sort_order')->first()?->update(['is_cover' => true]);
            }
        });
    }

    public function setCover(VehiclePhoto $photo): void
    {
        DB::transaction(function () use ($photo) {
            $photo->vehicle->photos()->update(['is_cover' => false]);
            $photo->update(['is_cover' => true]);
        });
    }

    /** @param  array<int, int>  $orderedIds */
    public function reorder(Vehicle $vehicle, array $orderedIds): void
    {
        DB::transaction(function () use ($vehicle, $orderedIds) {
            foreach (array_values($orderedIds) as $position => $id) {
                $vehicle->photos()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * Removes every file of a listing. Called when the listing itself goes:
     * orphaned derivatives are invisible and would grow without limit.
     */
    public function deleteAll(Vehicle $vehicle): void
    {
        $vehicle->photos->each(fn (VehiclePhoto $photo) => $this->deleteFiles($photo));
        $vehicle->photos()->delete();
        Storage::disk('public')->deleteDirectory("vehicles/{$vehicle->id}");
    }

    private function deleteFiles(VehiclePhoto $photo): void
    {
        Storage::disk('public')->delete(array_filter([
            $photo->path, $photo->path_card, $photo->path_thumb,
        ]));
    }
}
