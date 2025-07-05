<?php

namespace App\Filament\Resources\SubcountyResource\Pages;

use App\Filament\Resources\SubcountyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubcounty extends EditRecord
{
    protected static string $resource = SubcountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
