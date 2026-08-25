<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

/**
 * A listing belongs to one agency and to nobody else (specification 11).
 * Ownership is resolved through the agency, so an agency's extra members get
 * the same access as its owner without a second rule to keep in step.
 */
class VehiclePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->owns($user, $vehicle);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        // A suspended agency keeps read access to its dashboard; it must not
        // be able to push new content to a public site it is barred from.
        return $this->owns($user, $vehicle) && ! $vehicle->agency->isSuspended();
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }

    private function owns(User $user, Vehicle $vehicle): bool
    {
        $agency = $user->activeAgency();

        return $agency !== null && $agency->id === $vehicle->agency_id;
    }
}
