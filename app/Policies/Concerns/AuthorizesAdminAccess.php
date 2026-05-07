<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesAdminAccess
{
    protected function isAdmin(User $user): bool
    {
        return $user->is_admin;
    }
}
