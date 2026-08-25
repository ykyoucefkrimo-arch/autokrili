<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Statuses that hold a vehicle's dates. The availability engine reads this
     * list, so adding a status here is the only change needed to make it block.
     */
    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $fillable = [
        'booking_reference', 'vehicle_id', 'agency_id', 'client_id',
        'start_date', 'end_date', 'pickup_location', 'dropoff_location', 'total_days',
        'client_name', 'client_phone', 'client_email', 'driver_license_number',
        'with_driver', 'vehicle_price_dzd', 'driver_price_dzd', 'total_price_dzd',
        'deposit_dzd', 'price_breakdown', 'status', 'cancellation_reason', 'cancelled_by',
        'client_note', 'confirmed_at', 'started_at', 'completed_at', 'cancelled_at',
        'expires_at', 'review_invited_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'with_driver' => 'boolean',
            'price_breakdown' => 'array',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'review_invited_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function isBlocking(): bool
    {
        return in_array($this->status, self::BLOCKING_STATUSES, true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    /** Bookings whose dates overlap the given range, closed interval on both ends. */
    public function scopeOverlapping($query, string $start, string $end)
    {
        return $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }
}
