<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';

    /** Days covered by one unit of each rule type. */
    public const UNIT_DAYS = [
        self::DAILY => 1,
        self::WEEKLY => 7,
        self::MONTHLY => 30,
    ];

    protected $fillable = ['vehicle_id', 'duration_type', 'price_dzd', 'min_days'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function unitDays(): int
    {
        return self::UNIT_DAYS[$this->duration_type] ?? 1;
    }
}
