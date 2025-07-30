<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Models\StockLevel;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';
    protected static ?string $title = 'Stock Levels';
    protected static ?string $modelLabel = 'Stock Level';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Location Details')
                    ->schema([
                        Forms\Components\Select::make('location_type')
                            ->options([
                                'main_store' => 'Main Store',
                                'facility' => 'Facility',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('location_id', null)),

                        Forms\Components\Select::make('location_id')
                            ->label('Facility')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('location_type') === 'facility')
                            ->required(fn (Forms\Get $get): bool => $get('location_type') === 'facility'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Stock Information')
                    ->schema([
                        Forms\Components\TextInput::make('current_stock')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('reserved_stock')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Stock reserved for pending orders/transfers'),

                        Forms\Components\TextInput::make('projected_stock')
                            ->numeric()
                            ->default(0)
                            ->helperText('Projected stock including pending receipts'),

                        Forms\Components\DateTimePicker::make('last_stock_take_date')
                            ->label('Last Stock Take')
                            ->helperText('When was the last physical count?'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Any notes about this stock location...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('location_name')
            ->columns([
                Tables\Columns\TextColumn::make('location_name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn (StockLevel $record): string => match(true) {
                        $record->current_stock <= 0 => 'danger',
                        $record->current_stock <= $record->inventoryItem->reorder_point => 'warning',
                        default => 'success'
                    }),

                Tables\Columns\TextColumn::make('reserved_stock')
                    ->label('Reserved')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn (StockLevel $record): int => $record->available_stock),

                Tables\Columns\TextColumn::make('projected_stock')
                    ->label('Projected')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Value')
                    ->money()
                    ->sortable()
                    ->getStateUsing(fn (StockLevel $record): float => $record->stock_value),

                Tables\Columns\TextColumn::make('last_stock_take_date')
                    ->label('Last Count')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never counted')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lastUpdatedBy.full_name')
                    ->label('Updated By')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_type')
                    ->options([
                        'main_store' => 'Main Store',
                        'facility' => 'Facility',
                    ]),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereHas('inventoryItem', fn ($q) =>
                            $q->whereRaw('stock_levels.current_stock <= inventory_items.reorder_point')
                        ))
                    ->toggle(),

                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('current_stock', '<=', 0))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Add Stock Location')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['last_updated_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['last_updated_by'] = auth()->id();
                        return $data;
                    }),

                Tables\Actions\Action::make('adjust_stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('adjustment')
                            ->label('Stock Adjustment')
                            ->numeric()
                            ->required()
                            ->helperText('Use negative numbers to decrease stock'),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->placeholder('Explain the reason for this adjustment...'),
                    ])
                    ->action(function (StockLevel $record, array $data): void {
                        $record->adjustStock($data['adjustment'], $data['reason']);

                        // Create transaction record
                        $record->inventoryItem->transactions()->create([
                            'location_id' => $record->location_id,
                            'location_type' => $record->location_type,
                            'type' => 'adjustment',
                            'quantity' => abs($data['adjustment']),
                            'user_id' => auth()->id(),
                            'remarks' => $data['reason'],
                            'transaction_date' => now(),
                        ]);

                        Notification::make()
                            ->title('Stock adjusted successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('stock_take')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('counted_stock')
                            ->label('Counted Stock')
                            ->numeric()
                            ->required()
                            ->default(fn (StockLevel $record): int => $record->current_stock),

                        Forms\Components\Textarea::make('notes')
                            ->label('Stock Take Notes')
                            ->placeholder('Any observations during the count...'),
                    ])
                    ->action(function (StockLevel $record, array $data): void {
                        $difference = $data['counted_stock'] - $record->current_stock;

                        $record->update([
                            'current_stock' => $data['counted_stock'],
                            'last_stock_take_date' => now(),
                            'last_updated_by' => auth()->id(),
                            'notes' => $data['notes'],
                        ]);

                        // Create adjustment transaction if there's a difference
                        if ($difference !== 0) {
                            $record->inventoryItem->transactions()->create([
                                'location_id' => $record->location_id,
                                'location_type' => $record->location_type,
                                'type' => 'adjustment',
                                'quantity' => abs($difference),
                                'user_id' => auth()->id(),
                                'remarks' => "Stock take adjustment: " . ($difference > 0 ? 'Found' : 'Missing') . " {$difference} units",
                                'transaction_date' => now(),
                            ]);
                        }

                        Notification::make()
                            ->title('Stock take completed')
                            ->body($difference !== 0 ? "Adjustment of {$difference} units recorded" : "No discrepancies found")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No stock locations')
            ->emptyStateDescription('Add stock locations to track inventory at different facilities.')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }
}
