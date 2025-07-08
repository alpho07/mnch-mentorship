<?php

namespace App\Filament\Resources\ItemBatchResource\Pages;

use App\Filament\Resources\ItemBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListItemBatches extends ListRecords
{
    protected static string $resource = ItemBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
