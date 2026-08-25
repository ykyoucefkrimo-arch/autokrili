<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only by intent: the specification requires the audit trail to be
 * consultable and never modifiable. Nothing in the application updates a row.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'admin_id', 'action', 'model_type', 'model_id',
        'old_values', 'new_values', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
