<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    protected $fillable = ['wilaya_id', 'name_fr', 'name_ar', 'slug', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'pickup_commune_id');
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name_fr;
    }
}
