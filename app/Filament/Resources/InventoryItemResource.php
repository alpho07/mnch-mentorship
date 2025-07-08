<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Location;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Components\Toggle;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;
      protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('serial_number')->unique()->maxLength(60),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Textarea::make('description')->rows(2),
            Forms\Components\Select::make('category_id')
                ->label('Category')->relationship('category', 'name')->required(),
            Forms\Components\TextInput::make('unit_of_measure')->required(),
            Forms\Components\Select::make('supplier_id')
                ->label('Supplier')->relationship('supplier', 'name'),
            Forms\Components\TextInput::make('image_url'),
            Forms\Components\TextInput::make('price')->numeric()->step(0.01),
            Forms\Components\Select::make('status')
                ->options([
                    'available' => 'Available',
                    'in_use' => 'In Use',
                    'maintenance' => 'Maintenance',
                    'disposed' => 'Disposed',
                    'lost' => 'Lost',
                ])->required(),
            Toggle::make('is_borrowable')->label('Can Be Borrowed')->default(true),
            Forms\Components\Select::make('current_location_id')
                ->label('Current Location')->relationship('location', 'name'),
            Forms\Components\TextInput::make('latitude')->numeric()->step(0.0000001),
            Forms\Components\TextInput::make('longitude')->numeric()->step(0.0000001),
            Forms\Components\DateTimePicker::make('last_tracked_at'),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('serial_number')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable(),
            Tables\Columns\TextColumn::make('unit_of_measure'),
            Tables\Columns\TextColumn::make('supplier.name')->label('Supplier'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\BooleanColumn::make('is_borrowable')->label('Borrowable'),
            Tables\Columns\TextColumn::make('location.name')->label('Current Location'),
            Tables\Columns\TextColumn::make('latitude'),
            Tables\Columns\TextColumn::make('longitude'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('category_id')->relationship('category', 'name'),
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'available' => 'Available',
                    'in_use' => 'In Use',
                    'maintenance' => 'Maintenance',
                    'disposed' => 'Disposed',
                    'lost' => 'Lost',
                ]),
            Tables\Filters\TernaryFilter::make('is_borrowable')->label('Borrowable'),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
            //'view' => Pages\ViewInventoryItem::route('/{record}'),
        ];
    }
}
