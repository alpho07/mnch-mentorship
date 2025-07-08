<?php

namespace App\Filament\Resources\ItemTransactionResource\Pages;

use App\Filament\Resources\ItemTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListItemTransactions extends ListRecords
{
    protected static string $resource = ItemTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
