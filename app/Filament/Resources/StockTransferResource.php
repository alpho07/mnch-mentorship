<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\ItemTransaction;
use App\Models\StockBalance;
use Filament\Notifications\Notification;
use App\Filament\Resources\StockTransferResource\Pages;


class StockTransferResource extends Resource
{
    protected static ?string $model = ItemTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $slug = 'stock-transfers';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('inventory_item_id')
                ->label('Item')
                ->options(InventoryItem::all()->pluck('name', 'id'))
                ->required(),

            Forms\Components\Select::make('from_location_id')
                ->label('From Store')
                ->options(Location::all()->pluck('name', 'id'))
                ->required(),

            Forms\Components\Select::make('to_location_id')
                ->label('To Store')
                ->options(Location::all()->pluck('name', 'id'))
                ->required()
                ->different('from_location_id'),

            Forms\Components\TextInput::make('quantity')
                ->label('Quantity')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\Textarea::make('remarks'),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('inventoryItem.name')->label('Item'),
            Tables\Columns\TextColumn::make('fromLocation.name')->label('From'),
            Tables\Columns\TextColumn::make('toLocation.name')->label('To'),
            Tables\Columns\TextColumn::make('quantity'),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
        ];
    }
}
