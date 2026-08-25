<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Wilaya;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The reference lists of specification 12: wilayas and the plan matrix.
 *
 * They sit on almost every page and change when a territorial reform is voted
 * or an administrator edits the matrix — not when a visitor loads a page. The
 * cache is cleared explicitly on those two events rather than being given a
 * short lifetime, so a modification is visible immediately.
 */
class ReferenceDataService
{
    private const TTL_DAYS = 7;

    public const KEY_WILAYAS = 'reference.wilayas';
    public const KEY_PLANS = 'reference.plans';

    /** @return Collection<int, Wilaya> */
    public function wilayas(): Collection
    {
        return Cache::remember(
            self::KEY_WILAYAS,
            now()->addDays(self::TTL_DAYS),
            fn () => Wilaya::orderBy('code')->get(['id', 'code', 'name_fr', 'name_ar', 'slug'])
        );
    }

    /** Active plans, in display order. @return Collection<int, Plan> */
    public function activePlans(): Collection
    {
        return Cache::remember(
            self::KEY_PLANS,
            now()->addDays(self::TTL_DAYS),
            fn () => Plan::where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    /** Called when the matrix or the divisions change. */
    public function forget(): void
    {
        Cache::forget(self::KEY_WILAYAS);
        Cache::forget(self::KEY_PLANS);

        // Les communes sont mises en cache par wilaya : leur liste dépend du
        // même découpage administratif.
        Wilaya::pluck('id')->each(fn ($id) => Cache::forget("wilaya:{$id}:communes"));
    }
}
