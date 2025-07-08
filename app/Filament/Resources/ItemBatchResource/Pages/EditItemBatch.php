<?php

namespace App\Filament\Resources\ItemBatchResource\Pages;

use App\Filament\Resources\ItemBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditItemBatch extends EditRecord
{
    protected static string $resource = ItemBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
