<?php

namespace App\Filament\Resources\FacilityTypeResource\Pages;

use App\Filament\Resources\FacilityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFacilityType extends EditRecord
{
    protected static string $resource = FacilityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
