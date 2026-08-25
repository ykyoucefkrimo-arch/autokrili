<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id', 'commercial_name', 'slug', 'manager_name', 'trade_register_number',
        'nif', 'wilaya_id', 'commune_id', 'address', 'latitude', 'longitude',
        'phone', 'whatsapp', 'logo_path', 'trade_register_file', 'description',
        'status', 'rejection_reason', 'approved_at', 'is_trusted', 'buffer_hours',
        'min_driver_age', 'default_deposit_dzd', 'rental_conditions', 'opening_hours',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_trusted' => 'boolean',
            'opening_hours' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'average_rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agency_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function planChangeRequests(): HasMany
    {
        return $this->hasMany(PlanChangeRequest::class);
    }

    /**
     * The subscription currently in force.
     *
     * The status constraint goes *inside* the aggregate, not beside it:
     * `->where(...)->latestOfMany()` picks the newest row of all statuses and
     * only then filters, so an agency whose newest row is cancelled would come
     * back with no plan at all — and silently fall to Silver.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['starts_at' => 'MAX', 'id' => 'MAX'],
            fn ($query) => $query->where('status', Subscription::STATUS_ACTIVE)
        );
    }

    /**
     * The plan that governs quotas right now. An agency without an active
     * subscription falls back to Silver rather than to no plan at all: the
     * quota checks must always have an answer.
     */
    public function currentPlan(): ?Plan
    {
        $subscription = $this->relationLoaded('activeSubscription')
            ? $this->activeSubscription
            : $this->activeSubscription()->with('plan')->first();

        return $subscription?->plan ?? Plan::where('slug', Plan::SILVER)->first();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }
}
