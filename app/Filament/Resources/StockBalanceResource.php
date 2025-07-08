<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockBalanceResource\Pages;
use App\Models\StockBalance;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;

class StockBalanceResource extends Resource
{
    protected static ?string $model = StockBalance::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('inventory_item_id')
                ->relationship('inventoryItem', 'name')
                ->required(),
            Forms\Components\Select::make('location_id')
                ->relationship('location', 'name')
                ->required(),
            Forms\Components\Select::make('item_batch_id')
                ->relationship('batch', 'batch_no'),
            Forms\Components\TextInput::make('quantity')->numeric()->required(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('inventoryItem.name')->label('Item')->searchable(),
            Tables\Columns\TextColumn::make('location.name')->label('Location'),
            Tables\Columns\TextColumn::make('batch.batch_no')->label('Batch'),
            Tables\Columns\TextColumn::make('quantity')->sortable(),
        ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockBalances::route('/'),
            'create' => Pages\CreateStockBalance::route('/create'),
            'edit' => Pages\EditStockBalance::route('/{record}/edit'),
        ];
    }
}
