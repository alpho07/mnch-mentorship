<?php

namespace App\Filament\Resources;

use App\Models\ItemBatch;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;

use App\Filament\Resources\ItemBatchResource\Pages;

class ItemBatchResource extends Resource
{
    protected static ?string $model = ItemBatch::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('inventory_item_id')->relationship('inventoryItem', 'name')->required(),
            Forms\Components\TextInput::make('batch_no')->required(),
            Forms\Components\DatePicker::make('expiry_date'),
            Forms\Components\TextInput::make('initial_quantity')->numeric()->required(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('inventoryItem.name'),
            Tables\Columns\TextColumn::make('batch_no'),
            Tables\Columns\TextColumn::make('expiry_date')->date(),
            Tables\Columns\TextColumn::make('initial_quantity'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItemBatches::route('/'),
            'create' => Pages\CreateItemBatch::route('/create'),
            'edit' => Pages\EditItemBatch::route('/{record}/edit'),
        ];
    }
}
