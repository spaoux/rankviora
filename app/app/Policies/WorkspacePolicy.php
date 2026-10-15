<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->users()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->users()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists();
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }
}
