<?php

namespace App\Filament\Resources\SchoolResource\Pages;

use App\Filament\Concerns\AuthorizesRestrictedTableActions;
use App\Filament\Resources\SchoolResource;
use Filament\Resources\Pages\ListRecords;

class ListSchools extends ListRecords
{
    use AuthorizesRestrictedTableActions;

    protected static string $resource = SchoolResource::class;
}
