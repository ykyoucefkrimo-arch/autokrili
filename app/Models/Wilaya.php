<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wilaya extends Model
{
    protected $fillable = ['code', 'name_fr', 'name_ar', 'slug', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'pickup_wilaya_id');
    }

    /** Display name for the requested locale, falling back to French. */
    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name_fr;
    }
}
