<?php

namespace App\Policies;

use App\Models\DailySession;
use App\Models\User;

class DailySessionPolicy
{
    public function view(User $user, DailySession $session): bool
    {
        return $user->is_active;
    }

    /** Closing a day is admin-only. */
    public function close(User $user, DailySession $session): bool
    {
        return $user->is_active && $user->isAdmin() && $session->isOpen();
    }

    public function reopen(User $user, DailySession $session): bool
    {
        return $user->is_active && $user->isAdmin() && $session->isClosed();
    }
}
