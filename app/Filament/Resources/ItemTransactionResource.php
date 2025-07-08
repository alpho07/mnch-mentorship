<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemTransactionResource\Pages;
use App\Models\InventoryItem;
use App\Models\ItemTransaction;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;

class ItemTransactionResource extends Resource
{
    protected static ?string $model = ItemTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
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
            Forms\Components\Select::make('type')->options([
                'receipt' => 'Receipt',
                'issue' => 'Issue',
                'return' => 'Return',
                'adjustment' => 'Adjustment',
                'transfer' => 'Transfer',
            ])->required(),
            Forms\Components\TextInput::make('quantity')->numeric()->required(),
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->label('Actioned By'),
            Forms\Components\Textarea::make('remarks'),
            Forms\Components\DateTimePicker::make('transaction_date')->required(),
            Forms\Components\TextInput::make('latitude')->numeric()->step(0.0000001),
            Forms\Components\TextInput::make('longitude')->numeric()->step(0.0000001),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('inventoryItem.name')->label('Item'),
            Tables\Columns\TextColumn::make('location.name')->label('Location'),
            Tables\Columns\TextColumn::make('batch.batch_no')->label('Batch'),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('quantity'),
            Tables\Columns\TextColumn::make('user.name')->label('Actioned By'),
            Tables\Columns\TextColumn::make('transaction_date')->dateTime(),
        ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'receipt' => 'Receipt',
                    'issue' => 'Issue',
                    'return' => 'Return',
                    'adjustment' => 'Adjustment',
                    'transfer' => 'Transfer',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('transfer')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->url(fn(InventoryItem $record) => route('filament.admin.resources.stock-transfers.create', [
                        'inventory_item_id' => $record->id,
                    ])),
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
            'index' => Pages\ListItemTransactions::route('/'),
            'create' => Pages\CreateItemTransaction::route('/create'),
            'edit' => Pages\EditItemTransaction::route('/{record}/edit'),
            //'view' => Pages\ViewItemTransaction::route('/{record}'),
        ];
    }
}
