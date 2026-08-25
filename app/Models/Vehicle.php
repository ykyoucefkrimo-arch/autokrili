<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    public const CATEGORIES = ['citadine', 'berline', 'suv', 'utilitaire', '4x4', 'luxe', 'minibus'];
    public const TRANSMISSIONS = ['manuelle', 'automatique'];
    public const FUELS = ['essence', 'diesel', 'gpl', 'hybride', 'electrique'];

    protected $fillable = [
        'agency_id', 'brand', 'model', 'year', 'slug', 'category', 'transmission',
        'fuel', 'seats', 'doors', 'air_conditioning', 'mileage_limit_per_day',
        'description', 'pickup_wilaya_id', 'pickup_commune_id',
        'with_driver_available', 'driver_price_per_day',
        'status', 'rejection_reason', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'air_conditioning' => 'boolean',
            'with_driver_available' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class)->orderBy('sort_order');
    }

    public function coverPhoto(): HasOne
    {
        return $this->hasOne(VehiclePhoto::class)->where('is_cover', true);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function pickupWilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class, 'pickup_wilaya_id');
    }

    public function pickupCommune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'pickup_commune_id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(ListingStat::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function title(): string
    {
        return "{$this->brand} {$this->model} {$this->year}";
    }

    /** Scope used by every public query: only what a visitor may legitimately see. */
    public function scopeVisible($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereHas('agency', fn ($q) => $q->where('status', Agency::STATUS_APPROVED));
    }
}
