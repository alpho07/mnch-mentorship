<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemTrainingLinkResource\Pages;
use App\Models\ItemTrainingLink;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;

class ItemTrainingLinkResource extends Resource
{
    protected static ?string $model = ItemTrainingLink::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('inventory_item_id')
                ->relationship('inventoryItem', 'name')
                ->required(),
            Forms\Components\Select::make('program_id')
                ->relationship('program', 'name'),
            Forms\Components\Select::make('module_id')
                ->relationship('module', 'name'),
            Forms\Components\Select::make('topic_id')
                ->relationship('topic', 'name'),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('inventoryItem.name')->label('Item'),
            Tables\Columns\TextColumn::make('program.name')->label('Program'),
            Tables\Columns\TextColumn::make('module.name')->label('Module'),
            Tables\Columns\TextColumn::make('topic.name')->label('Topic'),
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
            'index' => Pages\ListItemTrainingLinks::route('/'),
            'create' => Pages\CreateItemTrainingLink::route('/create'),
            'edit' => Pages\EditItemTrainingLink::route('/{record}/edit'),
        ];
    }
}
