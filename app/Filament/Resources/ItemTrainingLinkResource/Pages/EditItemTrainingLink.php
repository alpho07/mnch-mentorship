<?php

namespace App\Filament\Resources\ItemTrainingLinkResource\Pages;

use App\Filament\Resources\ItemTrainingLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditItemTrainingLink extends EditRecord
{
    protected static string $resource = ItemTrainingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
