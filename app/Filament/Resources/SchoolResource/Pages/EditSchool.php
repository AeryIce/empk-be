<?php

namespace App\Filament\Resources\SchoolResource\Pages;

use App\Filament\Resources\SchoolResource;
use Filament\Resources\Pages\EditRecord;

class EditSchool extends EditRecord
{
    protected static string $resource = SchoolResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return SchoolResource::filterRestrictedFields($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SchoolResource::filterRestrictedFields($data);
    }
}
