<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'agency_id', 'plan_id', 'starts_at', 'ends_at', 'status',
        'admin_note', 'created_by', 'expiry_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A NULL end date means the plan never expires (the Silver fallback). */
    public function hasExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->ends_at?->diffInDays(now(), false) * -1;
    }
}
