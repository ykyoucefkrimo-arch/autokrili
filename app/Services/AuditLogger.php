<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the administrative trail. Every moderation decision passes through
 * here so the journal cannot drift from what actually happened: a caller that
 * forgets to log is a decision nobody can account for later.
 */
class AuditLogger
{
    public function log(string $action, ?Model $subject = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'admin_id' => Auth::id(),
            'action' => $action,
            'model_type' => $subject ? $subject::class : null,
            'model_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => Request::ip(),
        ]);
    }

    /** Records only the attributes that actually changed. */
    public function logChange(string $action, Model $subject, array $before): AuditLog
    {
        $after = $subject->getAttributes();
        $changed = array_keys(array_diff_assoc(
            array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $after),
            array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $before)
        ));

        return $this->log(
            $action,
            $subject,
            array_intersect_key($before, array_flip($changed)),
            array_intersect_key($after, array_flip($changed))
        );
    }
}
