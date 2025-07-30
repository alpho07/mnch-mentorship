<?php

namespace App\Filament\Resources\StockRequestResource\RelationManagers;

use App\Models\StockRequestItem;
use App\Models\InventoryItem;
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
    protected static ?string $title = 'Requested Items';
    protected static ?string $modelLabel = 'Request Item';

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

                                        // Get available stock at supplying facility
                                        $stockRequest = $this->getOwnerRecord();
                                        $availableStock = $item->getStockAtLocation(
                                            $stockRequest->supplying_facility_id ?: 1,
                                            $stockRequest->supplying_facility_id ? 'facility' : 'main_store'
                                        );

                                        $set('available_stock_info', "Available: {$availableStock} {$item->unit_of_measure}");
                                    }
                                }
                            }),

                        Forms\Components\Placeholder::make('available_stock_info')
                            ->label('Stock Information')
                            ->visible(fn (Forms\Get $get): bool => !empty($get('available_stock_info'))),

                        Forms\Components\TextInput::make('quantity_requested')
                            ->label('Quantity Requested')
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

                        Forms\Components\TextInput::make('quantity_approved')
                            ->label('Quantity Approved')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled(fn (): bool => $this->getOwnerRecord()->status === 'draft'),

                        Forms\Components\TextInput::make('quantity_fulfilled')
                            ->label('Quantity Fulfilled')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost')
                            ->prefix('$')
                            ->numeric()
                            ->step(0.01)
                            ->disabled(),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Cost')
                            ->prefix('$')
                            ->numeric()
                            ->step(0.01)
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Select::make('urgency_level')
                            ->label('Urgency Level')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                            ])
                            ->default('medium')
                            ->required(),

                        Forms\Components\Textarea::make('justification')
                            ->label('Justification')
                            ->rows(3)
                            ->placeholder('Why is this item needed?')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(2)
                            ->placeholder('Any additional information...')
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

                Tables\Columns\TextColumn::make('quantity_requested')
                    ->label('Requested')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('quantity_approved')
                    ->label('Approved')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('quantity_fulfilled')
                    ->label('Fulfilled')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn (StockRequestItem $record): string => match(true) {
                        $record->is_fully_fulfilled => 'success',
                        $record->is_partially_fulfilled => 'warning',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('quantity_pending')
                    ->label('Pending')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn (StockRequestItem $record): int => $record->quantity_pending),

                Tables\Columns\TextColumn::make('fulfillment_percentage')
                    ->label('Progress')
                    ->alignCenter()
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1) . '%')
                    ->color(fn (float $state): string => match(true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        $state > 0 => 'info',
                        default => 'gray'
                    })
                    ->getStateUsing(fn (StockRequestItem $record): float => $record->fulfillment_percentage),

                Tables\Columns\TextColumn::make('urgency_level')
                    ->label('Urgency')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray'
                    }),

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
                    ->color(fn (StockRequestItem $record): string => $record->status_color)
                    ->getStateUsing(fn (StockRequestItem $record): string => match($record->status) {
                        'pending_approval' => 'Pending Approval',
                        'approved' => 'Approved',
                        'partially_fulfilled' => 'Partially Fulfilled',
                        'fulfilled' => 'Fulfilled',
                        default => 'Unknown'
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('urgency_level')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ]),

                Tables\Filters\SelectFilter::make('inventory_item')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('pending_approval')
                    ->label('Pending Approval')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('quantity_approved', 0))
                    ->toggle(),

                Tables\Filters\Filter::make('partially_fulfilled')
                    ->label('Partially Fulfilled')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('quantity_fulfilled', '>', 0)
                              ->whereColumn('quantity_fulfilled', '<', 'quantity_approved'))
                    ->toggle(),

                Tables\Filters\Filter::make('fully_fulfilled')
                    ->label('Fully Fulfilled')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereColumn('quantity_fulfilled', '>=', 'quantity_approved'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Add Item to Request')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['unit_cost']) && isset($data['quantity_requested'])) {
                            $data['total_cost'] = $data['unit_cost'] * $data['quantity_requested'];
                        }
                        return $data;
                    })
                    ->after(function (): void {
                        // Recalculate request total
                        $stockRequest = $this->getOwnerRecord();
                        $stockRequest->update([
                            'total_estimated_cost' => $stockRequest->calculateEstimatedCost(),
                        ]);
                    }),

                Tables\Actions\Action::make('bulk_add_items')
                    ->label('Bulk Add Items')
                    ->icon('heroicon-o-plus-circle')
                    ->color('info')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->form([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->relationship('inventoryItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity_requested')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),

                                Forms\Components\Select::make('urgency_level')
                                    ->label('Urgency')
                                    ->options([
                                        'low' => 'Low',
                                        'medium' => 'Medium',
                                        'high' => 'High',
                                    ])
                                    ->default('medium'),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->maxItems(20)
                            ->addActionLabel('Add Another Item'),
                    ])
                    ->action(function (array $data): void {
                        $stockRequest = $this->getOwnerRecord();

                        foreach ($data['items'] as $itemData) {
                            $inventoryItem = InventoryItem::find($itemData['inventory_item_id']);

                            $stockRequest->items()->create([
                                'inventory_item_id' => $itemData['inventory_item_id'],
                                'quantity_requested' => $itemData['quantity_requested'],
                                'urgency_level' => $itemData['urgency_level'],
                                'unit_cost' => $inventoryItem->cost_price,
                                'total_cost' => $inventoryItem->cost_price * $itemData['quantity_requested'],
                            ]);
                        }

                        // Recalculate request total
                        $stockRequest->update([
                            'total_estimated_cost' => $stockRequest->calculateEstimatedCost(),
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
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['unit_cost']) && isset($data['quantity_requested'])) {
                            $data['total_cost'] = $data['unit_cost'] * $data['quantity_requested'];
                        }
                        return $data;
                    })
                    ->after(function (): void {
                        // Recalculate request total
                        $stockRequest = $this->getOwnerRecord();
                        $stockRequest->update([
                            'total_estimated_cost' => $stockRequest->calculateEstimatedCost(),
                        ]);
                    }),

                Tables\Actions\Action::make('check_availability')
                    ->label('Check Stock')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->action(function (StockRequestItem $record): void {
                        $stockRequest = $this->getOwnerRecord();
                        $availableStock = $record->inventoryItem->getStockAtLocation(
                            $stockRequest->supplying_facility_id ?: 1,
                            $stockRequest->supplying_facility_id ? 'facility' : 'main_store'
                        );

                        $totalStock = $record->inventoryItem->total_stock;

                        $message = "Available at source: {$availableStock} {$record->inventoryItem->unit_of_measure}\n";
                        $message .= "Total system stock: {$totalStock} {$record->inventoryItem->unit_of_measure}";

                        Notification::make()
                            ->title('Stock Availability')
                            ->body($message)
                            ->info()
                            ->duration(10000)
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->after(function (): void {
                        // Recalculate request total
                        $stockRequest = $this->getOwnerRecord();
                        $stockRequest->update([
                            'total_estimated_cost' => $stockRequest->calculateEstimatedCost(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                        ->after(function (): void {
                            // Recalculate request total
                            $stockRequest = $this->getOwnerRecord();
                            $stockRequest->update([
                                'total_estimated_cost' => $stockRequest->calculateEstimatedCost(),
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('update_urgency')
                        ->label('Update Urgency')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                        ->form([
                            Forms\Components\Select::make('urgency_level')
                                ->label('New Urgency Level')
                                ->options([
                                    'low' => 'Low',
                                    'medium' => 'Medium',
                                    'high' => 'High',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->update(['urgency_level' => $data['urgency_level']]);
                            }

                            Notification::make()
                                ->title('Urgency levels updated')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No items requested')
            ->emptyStateDescription('Add items to this stock request.')
            ->emptyStateIcon('heroicon-o-cube');
    }
}
