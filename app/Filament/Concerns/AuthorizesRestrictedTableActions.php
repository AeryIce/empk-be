<?php

namespace App\Filament\Concerns;

use App\Models\School;
use Illuminate\Support\Facades\Gate;

trait AuthorizesRestrictedTableActions
{
    public function mountTableAction(string $name, ?string $record = null, array $arguments = []): mixed
    {
        if ($name === 'harvy_sync') {
            $this->authorizeHarvySync($record);
        }

        return parent::mountTableAction($name, $record, $arguments);
    }

    public function callMountedTableAction(array $arguments = []): mixed
    {
        if ($this->getMountedTableAction()?->getName() === 'harvy_sync') {
            $this->authorizeHarvySync($this->getMountedTableActionRecordKey());
        }

        return parent::callMountedTableAction($arguments);
    }

    public function mountTableBulkAction(string $name, ?array $selectedRecords = null): mixed
    {
        if ($name === 'delete') {
            Gate::authorize('deleteAny', $this->getTable()->getModel());
        }

        return parent::mountTableBulkAction($name, $selectedRecords);
    }

    public function callMountedTableBulkAction(array $arguments = []): mixed
    {
        if ($this->getMountedTableBulkAction()?->getName() === 'delete') {
            Gate::authorize('deleteAny', $this->getTable()->getModel());
        }

        return parent::callMountedTableBulkAction($arguments);
    }

    protected function authorizeHarvySync(int|string|null $key): void
    {
        Gate::authorize('manage-school-internals');

        abort_unless(ctype_digit((string) $key), 404);
        $school = $this->getTableRecord((string) $key);
        abort_unless($school instanceof School, 404);

        Gate::authorize('syncDapo', $school);
    }
}
