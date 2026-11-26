<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesRecordDeletion
{
    public function mountAction(string $name, array $arguments = []): mixed
    {
        if ($name === 'delete') {
            Gate::authorize('delete', $this->getRecord());
        }

        return parent::mountAction($name, $arguments);
    }

    public function callMountedAction(array $arguments = []): mixed
    {
        if ($this->getMountedAction()?->getName() === 'delete') {
            Gate::authorize('delete', $this->getRecord());
        }

        return parent::callMountedAction($arguments);
    }
}
