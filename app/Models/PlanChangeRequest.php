<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REFUSED = 'refused';

    protected $fillable = [
        'agency_id', 'requested_plan_id', 'status',
        'agency_message', 'admin_response', 'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }
}
