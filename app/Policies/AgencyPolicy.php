<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

/**
 * An agency may never read or write another agency's data (specification 11).
 * Ownership is the only thing that grants access, plus the administrator.
 */
class AgencyPolicy
{
    /** Administrators pass every check without the rest of the class running. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Agency $agency): bool
    {
        return $this->belongsTo($user, $agency);
    }

    public function update(User $user, Agency $agency): bool
    {
        // A suspended agency keeps read access only.
        return $this->belongsTo($user, $agency) && ! $agency->isSuspended();
    }

    public function viewTradeRegister(User $user, Agency $agency): bool
    {
        // Identity documents: the owner and the administrator, nobody else.
        return $this->belongsTo($user, $agency);
    }

    public function moderate(User $user, Agency $agency): bool
    {
        // Only reachable through before(); an agency never moderates itself.
        return false;
    }

    private function belongsTo(User $user, Agency $agency): bool
    {
        return $agency->user_id === $user->id
            || $agency->members()->whereKey($user->id)->exists();
    }
}
