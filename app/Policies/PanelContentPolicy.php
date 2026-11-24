<?php

namespace App\Policies;

use App\Models\User;

class PanelContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManagePanelContent();
    }

    public function view(User $user): bool
    {
        return $user->canManagePanelContent();
    }

    public function create(User $user): bool
    {
        return $user->canManagePanelContent();
    }

    public function update(User $user): bool
    {
        return $user->canManagePanelContent();
    }

    public function delete(User $user): bool
    {
        return $user->canManagePanelContent();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManagePanelContent();
    }
}
