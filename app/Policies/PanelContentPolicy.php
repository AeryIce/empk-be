<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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

    public function update(User $user, Model $record): bool
    {
        if ($record instanceof School && $record->is_published) {
            return $user->isPanelModerator();
        }

        return $user->canManagePanelContent();
    }

    public function delete(User $user): bool
    {
        return $user->isPanelModerator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isPanelModerator();
    }

    public function syncDapo(User $user, School $school): bool
    {
        return $user->isPanelModerator() && ! $school->is_published;
    }
}
