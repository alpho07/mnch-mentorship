<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Models\InventoryTransaction;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title = 'Transaction History';
    protected static ?string $modelLabel = 'Transaction';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
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
                            ->required()
                            ->live(),

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
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('from_location_id')
                            ->label('From Location')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('type') === 'transfer'),

                        Forms\Components\Select::make('to_location_id')
                            ->label('To Location')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('type') === 'transfer'),

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_description')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (InventoryTransaction $record): string => $record->status_color)
                    ->formatStateUsing(fn (string $state): string =>
                        InventoryTransaction::TRANSACTION_TYPES[$state] ?? 'Unknown'),

                Tables\Columns\TextColumn::make('quantity')
                    ->alignCenter()
                    ->sortable()
                    ->prefix(fn (InventoryTransaction $record): string =>
                        $record->isStockIncrease() ? '+' : '-')
                    ->color(fn (InventoryTransaction $record): string =>
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
                    ->visible(fn (): bool => request()->get('type') === 'transfer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('to_location_name')
                    ->label('To')
                    ->searchable()
                    ->visible(fn (): bool => request()->get('type') === 'transfer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(fn (?string $state): string =>
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
                    ->getStateUsing(fn (InventoryTransaction $record): bool =>
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
                Tables\Filters\SelectFilter::make('type')
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

                Tables\Filters\SelectFilter::make('location')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('stock_in')
                    ->label('Stock In Transactions')
                    ->query(fn (Builder $query): Builder => $query->stockIn())
                    ->toggle(),

                Tables\Filters\Filter::make('stock_out')
                    ->label('Stock Out Transactions')
                    ->query(fn (Builder $query): Builder => $query->stockOut())
                    ->toggle(),

                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Transactions')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDate('transaction_date', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereBetween('transaction_date', [
                            now()->startOfWeek(),
                            now()->endOfWeek()
                        ]))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Create Transaction')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        if (!isset($data['transaction_date'])) {
                            $data['transaction_date'] = now();
                        }
                        if (isset($data['unit_cost']) && isset($data['quantity'])) {
                            $data['total_cost'] = $data['unit_cost'] * $data['quantity'];
                        }
                        return $data;
                    })
                    ->after(function (InventoryTransaction $record): void {
                        // Update stock levels after creating transaction
                        $inventoryItem = $record->inventoryItem;
                        $locationId = $record->location_id ?? 1; // Default to main store

                        if ($record->isStockIncrease()) {
                            $inventoryItem->adjustStock($locationId, $record->quantity, $record->remarks);
                        } elseif ($record->isStockDecrease()) {
                            $inventoryItem->adjustStock($locationId, -$record->quantity, $record->remarks);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn (InventoryTransaction $record): string =>
                        "Transaction #{$record->id}"),

                Tables\Actions\EditAction::make()
                    ->visible(fn (InventoryTransaction $record): bool =>
                        $record->type === 'adjustment' && auth()->user()->can('edit', $record))
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['unit_cost']) && isset($data['quantity'])) {
                            $data['total_cost'] = $data['unit_cost'] * $data['quantity'];
                        }
                        return $data;
                    }),

                Tables\Actions\Action::make('view_reference')
                    ->label('View Reference')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->visible(fn (InventoryTransaction $record): bool =>
                        !empty($record->reference_type) && !empty($record->reference_id))
                    ->url(function (InventoryTransaction $record): ?string {
                        if (!$record->reference_type || !$record->reference_id) {
                            return null;
                        }

                        return match($record->reference_type) {
                            'App\Models\StockRequest' => route('filament.admin.resources.stock-requests.view', $record->reference_id),
                            'App\Models\StockTransfer' => route('filament.admin.resources.stock-transfers.view', $record->reference_id),
                            default => null,
                        };
                    })
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (InventoryTransaction $record): bool =>
                        $record->type === 'adjustment' && auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('delete', InventoryTransaction::class)),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->emptyStateHeading('No transactions')
            ->emptyStateDescription('No transactions have been recorded for this item yet.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
