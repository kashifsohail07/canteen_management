<?php

namespace App\Policies;

use App\Models\RequiredItem;
use App\Models\User;

class RequiredItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, RequiredItem $item): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function fulfill(User $user, RequiredItem $item): bool
    {
        return $user->is_active && $item->isPending();
    }

    public function delete(User $user, RequiredItem $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
