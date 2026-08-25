<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityBlock extends Model
{
    public const REASON_MAINTENANCE = 'maintenance';
    public const REASON_OFF_PLATFORM = 'off_platform';
    public const REASON_UNAVAILABLE = 'unavailable';

    protected $fillable = ['vehicle_id', 'start_date', 'end_date', 'reason', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
