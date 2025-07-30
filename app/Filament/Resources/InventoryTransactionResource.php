<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryTransactionResource\Pages;
use App\Models\InventoryTransaction;
use App\Models\InventoryItem;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $label = 'Transactions';

       public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Select::make('inventory_item_id')
                            ->label('Inventory Item')
                            ->relationship('inventoryItem', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->options([
                                'in' => 'Stock In',
                                'out' => 'Stock Out',
                                'transfer' => 'Transfer',
                                'adjustment' => 'Adjustment',
                                'request' => 'Request',
                                'issue' => 'Issue',
                                'return' => 'Return',
                                'damage' => 'Damage',
                                'loss' => 'Loss',
                                'disposal' => 'Disposal',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost')
                            ->prefix('$')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->required()
                            ->default(now()),

                        Forms\Components\Textarea::make('remarks')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Location Information')
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->label('Primary Location')
                            ->relationship('facility', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('from_location_id')
                            ->label('From Location')
                            ->relationship('fromFacility', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn(Forms\Get $get): bool => $get('type') === 'transfer'),

                        Forms\Components\Select::make('to_location_id')
                            ->label('To Location')
                            ->relationship('toFacility', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn(Forms\Get $get): bool => $get('type') === 'transfer'),

                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn(InventoryTransaction $record): string => $record->status_color)
                    ->formatStateUsing(fn(string $state): string =>
                    InventoryTransaction::TRANSACTION_TYPES[$state] ?? 'Unknown'),

                Tables\Columns\TextColumn::make('quantity')
                    ->alignCenter()
                    ->sortable()
                    ->prefix(fn(InventoryTransaction $record): string =>
                    $record->isStockIncrease() ? '+' : '-')
                    ->color(fn(InventoryTransaction $record): string =>
                    $record->isStockIncrease() ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location_name')
                    ->label('Location')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('from_location_name')
                    ->label('From')
                    ->searchable()
                    ->visible(fn(): bool => request()->get('type') === 'transfer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('to_location_name')
                    ->label('To')
                    ->searchable()
                    ->visible(fn(): bool => request()->get('type') === 'transfer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(fn(?string $state): string =>
                    $state ? class_basename($state) : 'Manual')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('coordinates')
                    ->label('GPS')
                    ->boolean()
                    ->getStateUsing(fn(InventoryTransaction $record): bool =>
                    !empty($record->coordinates))
                    ->trueIcon('heroicon-o-map-pin')
                    ->falseIcon('heroicon-o-x-mark')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'in' => 'Stock In',
                        'out' => 'Stock Out',
                        'transfer' => 'Transfer',
                        'adjustment' => 'Adjustment',
                        'request' => 'Request',
                        'issue' => 'Issue',
                        'return' => 'Return',
                        'damage' => 'Damage',
                        'loss' => 'Loss',
                        'disposal' => 'Disposal',
                    ]),

                SelectFilter::make('inventory_item')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user')
                    ->relationship('user', 'full_name')
                    ->searchable()
                    ->preload(),

                Filter::make('stock_in')
                    ->label('Stock In Transactions')
                    ->query(fn(Builder $query): Builder => $query->stockIn())
                    ->toggle(),

                Filter::make('stock_out')
                    ->label('Stock Out Transactions')
                    ->query(fn(Builder $query): Builder => $query->stockOut())
                    ->toggle(),

                Filter::make('transfers')
                    ->label('Transfer Transactions')
                    ->query(fn(Builder $query): Builder => $query->transfers())
                    ->toggle(),

                Filter::make('today')
                    ->label('Today\'s Transactions')
                    ->query(fn(Builder $query): Builder =>
                    $query->whereDate('transaction_date', today()))
                    ->toggle(),

                Filter::make('this_week')
                    ->label('This Week\'s Transactions')
                    ->query(fn(Builder $query): Builder =>
                    $query->whereBetween('transaction_date', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ]))
                    ->toggle(),

                Filter::make('has_gps')
                    ->label('Has GPS Coordinates')
                    ->query(fn(Builder $query): Builder =>
                    $query->whereNotNull('latitude')->whereNotNull('longitude'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(InventoryTransaction $record): bool =>
                    $record->type === 'adjustment' && auth()->user()->can('edit', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete', InventoryTransaction::class)),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransactions::route('/'),
            'create' => Pages\CreateInventoryTransaction::route('/create'),
            //'view' => Pages\ViewInventoryTransaction::route('/{record}'),
            'edit' => Pages\EditInventoryTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('created_at', today())->count();
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', InventoryTransaction::class);
    }
}
