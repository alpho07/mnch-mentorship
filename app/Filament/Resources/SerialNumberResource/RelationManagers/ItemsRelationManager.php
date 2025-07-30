<?php

namespace App\Filament\Resources\StockTransferResource\RelationManagers;

use App\Models\StockTransferItem;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Transfer Items';
    protected static ?string $modelLabel = 'Transfer Item';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\Select::make('inventory_item_id')
                            ->label('Inventory Item')
                            ->relationship('inventoryItem', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                                if ($state) {
                                    $item = InventoryItem::find($state);
                                    if ($item) {
                                        $set('unit_cost', $item->cost_price);

                                        // Get available stock at source facility
                                        $stockTransfer = $this->getOwnerRecord();
                                        if ($stockTransfer->from_facility_id) {
                                            $availableStock = $item->getStockAtLocation($stockTransfer->from_facility_id, 'facility');
                                            $set('available_stock_info', "Available at source: {$availableStock} {$item->unit_of_measure}");
                                        } else {
                                            $availableStock = $item->getStockAtLocation(1, 'main_store');
                                            $set('available_stock_info', "Available at main store: {$availableStock} {$item->unit_of_measure}");
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Placeholder::make('available_stock_info')
                            ->label('Stock Information')
                            ->visible(fn (Forms\Get $get): bool => !empty($get('available_stock_info'))),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Transfer')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?int $state) {
                                $unitCost = $get('unit_cost');
                                if ($state && $unitCost) {
                                    $set('total_cost', $state * $unitCost);
                                }
                            }),

                        Forms\Components\TextInput::make('quantity_received')
                            ->label('Quantity Received')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->visible(fn (): bool =>
                                in_array($this->getOwnerRecord()->status, ['delivered', 'received'])),

                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost')
                            ->prefix('KES')
                            ->numeric()
                            ->step(0.01)
                            ->disabled(),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Cost')
                            ->prefix('KES')
                            ->numeric()
                            ->step(0.01)
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Batch & Serial Information')
                    ->schema([
                        Forms\Components\Select::make('batch_id')
                            ->label('Batch/Lot')
                            ->relationship('batch', 'batch_no')
                            ->searchable()
                            ->preload()
                            ->visible(function (Forms\Get $get){
                                if (!$get('inventory_item_id')) return false;
                                $item = InventoryItem::find($get('inventory_item_id'));
                                return $item && $item->requires_batch_tracking;
                            }),

                        Forms\Components\TagsInput::make('serial_numbers')
                            ->label('Serial Numbers')
                            ->placeholder('Add serial numbers...')
                            ->visible(function (Forms\Get $get) {
                                if (!$get('inventory_item_id')) return false;
                                $item = InventoryItem::find($get('inventory_item_id'));
                                return $item && $item->is_serialized;
                            })
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('condition_notes')
                            ->label('Condition Notes')
                            ->rows(2)
                            ->placeholder('Any notes about item condition...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inventoryItem.name')
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('inventoryItem.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Received')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn (StockTransferItem $record): string => match(true) {
                        $record->is_fully_received => 'success',
                        $record->is_partially_received => 'warning',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('quantity_pending')
                    ->label('Pending')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn (StockTransferItem $record): int => $record->quantity_pending),

                Tables\Columns\TextColumn::make('receipt_percentage')
                    ->label('Progress')
                    ->alignCenter()
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1) . '%')
                    ->color(fn (float $state): string => match(true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        $state > 0 => 'info',
                        default => 'gray'
                    })
                    ->getStateUsing(fn (StockTransferItem $record): float => $record->receipt_percentage),

                Tables\Columns\TextColumn::make('variance_quantity')
                    ->label('Variance')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => match(true) {
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray'
                    })
                    ->prefix(fn (int $state): string => $state > 0 ? '+' : '')
                    ->visible(fn (): bool =>
                        in_array($this->getOwnerRecord()->status, ['delivered', 'received']))
                    ->getStateUsing(fn (StockTransferItem $record): int => $record->variance_quantity),

                Tables\Columns\TextColumn::make('batch.batch_no')
                    ->label('Batch')
                    ->placeholder('No batch')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_numbers')
                    ->label('Serial Numbers')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('No serials')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (StockTransferItem $record): string => $record->status_color)
                    ->getStateUsing(fn (StockTransferItem $record): string => match($record->status) {
                        'pending' => 'Pending',
                        'partially_received' => 'Partially Received',
                        'received' => 'Received',
                        default => 'Unknown'
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('inventory_item')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('pending')
                    ->label('Pending Receipt')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('quantity_received', 0))
                    ->toggle(),

                Tables\Filters\Filter::make('partially_received')
                    ->label('Partially Received')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('quantity_received', '>', 0)
                              ->whereColumn('quantity_received', '<', 'quantity'))
                    ->toggle(),

                Tables\Filters\Filter::make('fully_received')
                    ->label('Fully Received')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereColumn('quantity_received', '>=', 'quantity'))
                    ->toggle(),

                Tables\Filters\Filter::make('has_variance')
                    ->label('Has Variance')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereRaw('quantity_received != quantity'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Add Item to Transfer')
                    ->visible(fn (): bool =>
                        in_array($this->getOwnerRecord()->status, ['draft', 'pending']))
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['unit_cost']) && isset($data['quantity'])) {
                            $data['total_cost'] = $data['unit_cost'] * $data['quantity'];
                        }
                        return $data;
                    })
                    ->after(function (): void {
                        // Recalculate transfer totals
                        $stockTransfer = $this->getOwnerRecord();
                        $stockTransfer->update([
                            'total_value' => $stockTransfer->calculateTotalValue(),
                            'total_items' => $stockTransfer->items()->count(),
                        ]);
                    }),

                Tables\Actions\Action::make('bulk_add_items')
                    ->label('Bulk Add Items')
                    ->icon('heroicon-o-plus-circle')
                    ->color('info')
                    ->visible(fn (): bool =>
                        in_array($this->getOwnerRecord()->status, ['draft', 'pending']))
                    ->form([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->relationship('inventoryItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->maxItems(20)
                            ->addActionLabel('Add Another Item'),
                    ])
                    ->action(function (array $data): void {
                        $stockTransfer = $this->getOwnerRecord();

                        foreach ($data['items'] as $itemData) {
                            $inventoryItem = InventoryItem::find($itemData['inventory_item_id']);

                            $stockTransfer->items()->create([
                                'inventory_item_id' => $itemData['inventory_item_id'],
                                'quantity' => $itemData['quantity'],
                                'unit_cost' => $inventoryItem->cost_price,
                                'total_cost' => $inventoryItem->cost_price * $itemData['quantity'],
                            ]);
                        }

                        // Recalculate transfer totals
                        $stockTransfer->update([
                            'total_value' => $stockTransfer->calculateTotalValue(),
                            'total_items' => $stockTransfer->items()->count(),
                        ]);

                        Notification::make()
                            ->title('Items added successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool =>
                        in_array($this->getOwnerRecord()->status, ['draft', 'pending']))
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['unit_cost']) && isset($data['quantity'])) {
                            $data['total_cost'] = $data['unit_cost'] * $data['quantity'];
                        }
                        return $data;
                    })
                    ->after(function (): void {
                        // Recalculate transfer totals
                        $stockTransfer = $this->getOwnerRecord();
                        $stockTransfer->update([
                            'total_value' => $stockTransfer->calculateTotalValue(),
                            'total_items' => $stockTransfer->items()->count(),
                        ]);
                    }),

                Tables\Actions\Action::make('check_availability')
                    ->label('Check Stock')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->action(function (StockTransferItem $record): void {
                        $stockTransfer = $this->getOwnerRecord();
                        $availableStock = $record->inventoryItem->getStockAtLocation(
                            $stockTransfer->from_facility_id ?: 1,
                            $stockTransfer->from_facility_id ? 'facility' : 'main_store'
                        );

                        $totalStock = $record->inventoryItem->total_stock;

                        $message = "Available at source: {$availableStock} {$record->inventoryItem->unit_of_measure}\n";
                        $message .= "Total system stock: {$totalStock} {$record->inventoryItem->unit_of_measure}";

                        if ($record->quantity > $availableStock) {
                            $message .= "\n⚠️ Transfer quantity exceeds available stock!";
                        }

                        Notification::make()
                            ->title('Stock Availability')
                            ->body($message)
                            ->color($record->quantity > $availableStock ? 'warning' : 'info')
                            ->duration(10000)
                            ->send();
                    }),

                Tables\Actions\Action::make('update_received')
                    ->label('Update Received')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (): bool =>
                        in_array($this->getOwnerRecord()->status, ['delivered', 'received']))
                    ->form([
                        Forms\Components\TextInput::make('quantity_received')
                            ->label('Quantity Received')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(fn (StockTransferItem $record): int => $record->quantity),

                        Forms\Components\Textarea::make('condition_notes')
                            ->label('Condition Notes')
                            ->placeholder('Any issues with received items...')
                            ->rows(3),
                    ])
                    ->action(function (StockTransferItem $record, array $data): void {
                        $record->update([
                            'quantity_received' => $data['quantity_received'],
                            'condition_notes' => $data['condition_notes'],
                        ]);

                        // Update stock levels at destination
                        $stockTransfer = $this->getOwnerRecord();
                        $record->inventoryItem->adjustStock(
                            $stockTransfer->to_facility_id,
                            $data['quantity_received'],
                            "Transfer receipt - {$stockTransfer->transfer_number}"
                        );

                        Notification::make()
                            ->title('Receipt updated successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool =>
                        in_array($this->getOwnerRecord()->status, ['draft', 'pending']))
                    ->after(function (): void {
                        // Recalculate transfer totals
                        $stockTransfer = $this->getOwnerRecord();
                        $stockTransfer->update([
                            'total_value' => $stockTransfer->calculateTotalValue(),
                            'total_items' => $stockTransfer->items()->count(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool =>
                            in_array($this->getOwnerRecord()->status, ['draft', 'pending']))
                        ->after(function (): void {
                            // Recalculate transfer totals
                            $stockTransfer = $this->getOwnerRecord();
                            $stockTransfer->update([
                                'total_value' => $stockTransfer->calculateTotalValue(),
                                'total_items' => $stockTransfer->items()->count(),
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('bulk_receive')
                        ->label('Bulk Receive')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn (): bool =>
                            in_array($this->getOwnerRecord()->status, ['delivered', 'received']))
                        ->form([
                            Forms\Components\Checkbox::make('receive_full_quantities')
                                ->label('Receive full quantities for all selected items')
                                ->default(true),

                            Forms\Components\Textarea::make('notes')
                                ->label('Receipt Notes')
                                ->placeholder('Any general notes about this receipt...')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $stockTransfer = $this->getOwnerRecord();

                            foreach ($records as $record) {
                                $quantityToReceive = $data['receive_full_quantities']
                                    ? $record->quantity
                                    : $record->quantity_received;

                                $record->update([
                                    'quantity_received' => $quantityToReceive,
                                    'condition_notes' => $data['notes'],
                                ]);

                                // Update stock levels at destination
                                $record->inventoryItem->adjustStock(
                                    $stockTransfer->to_facility_id,
                                    $quantityToReceive,
                                    "Bulk transfer receipt - {$stockTransfer->transfer_number}"
                                );
                            }

                            Notification::make()
                                ->title('Items received successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No items in transfer')
            ->emptyStateDescription('Add items to this transfer.')
            ->emptyStateIcon('heroicon-o-cube');
    }
}
