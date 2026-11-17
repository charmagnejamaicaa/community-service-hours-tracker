<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Any token a user already holds was issued for their OLD role/identity.
     * If role or student_id changes, old tokens could still act with the
     * previous permissions for up to TOKEN_MINUTES. Revoking them here forces
     * a fresh sign-in, so a role/identity change takes effect immediately.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('role') || $user->wasChanged('student_id')) {
            $user->tokens()->delete();
        }
    }
}