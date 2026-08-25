<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Wilaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The communes of one wilaya, fetched when a visitor picks it.
 *
 * The country has 1541 communes. Shipping them all with every page would add
 * roughly 90 KB to each first paint for a list of which the visitor reads one
 * wilaya's worth — and the specification asks for mobile-first, on connections
 * where that is not a rounding error.
 */
class CommuneController extends Controller
{
    public function __invoke(Wilaya $wilaya): JsonResponse
    {
        // Reference data: it changes when a territorial reform is voted, not
        // when a visitor loads a page.
        $communes = Cache::remember(
            "wilaya:{$wilaya->id}:communes",
            now()->addDay(),
            fn () => $wilaya->communes()
                ->orderBy('name_fr')
                ->get(['id', 'name_fr', 'name_ar', 'slug'])
        );

        return response()->json($communes)
            ->setPublic()
            ->setMaxAge(86400);
    }
}
