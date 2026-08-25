<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingStat extends Model
{
    protected $fillable = ['vehicle_id', 'agency_id', 'date', 'views', 'contact_clicks', 'bookings_count'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
