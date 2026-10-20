<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->is_active;
    }

    /** Opening balances and manual balance adjustments are admin-only. */
    public function manageOpeningBalance(User $user, Vendor $vendor): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function adjustBalance(User $user, Vendor $vendor): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
