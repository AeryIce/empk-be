<?php

namespace App\Filament\Resources\NewsPostResource\Pages;

use App\Filament\Concerns\AuthorizesRecordDeletion;
use App\Filament\Resources\NewsPostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsPost extends EditRecord
{
    use AuthorizesRecordDeletion;

    protected static string $resource = NewsPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
