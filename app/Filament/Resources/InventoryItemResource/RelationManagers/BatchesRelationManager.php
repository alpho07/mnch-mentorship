<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Models\ItemBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';
    protected static ?string $title = 'Batches/Lots';
    protected static ?string $modelLabel = 'Batch';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Batch Information')
                    ->schema([
                        Forms\Components\TextInput::make('batch_no')
                            ->label('Batch/Lot Number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Enter batch or lot number'),

                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->required(fn (): bool =>
                                $this->getOwnerRecord()->shelf_life_days > 0)
                            ->minDate(today())
                            ->helperText('When does this batch expire?'),

                        Forms\Components\TextInput::make('initial_quantity')
                            ->label('Initial Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Original quantity in this batch'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Batch Notes')
                            ->rows(3)
                            ->placeholder('Any additional information about this batch...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batch_no')
            ->columns([
                Tables\Columns\TextColumn::make('batch_no')
                    ->label('Batch/Lot Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->sortable()
                    ->color(function (?string $state) {
                        if (!$state) return 'gray';
                        $expiryDate = \Carbon\Carbon::parse($state);
                        $daysToExpiry = now()->diffInDays($expiryDate, false);

                        return match(true) {
                            $daysToExpiry < 0 => 'danger',        // Expired
                            $daysToExpiry <= 30 => 'warning',     // Expiring soon
                            $daysToExpiry <= 90 => 'info',        // Expiring in 3 months
                            default => 'success'                   // Good
                        };
                    })
                    ->icon(function (?string $state): ?string  {
                        if (!$state) return null;
                        $expiryDate = \Carbon\Carbon::parse($state);
                        $daysToExpiry = now()->diffInDays($expiryDate, false);

                        return $daysToExpiry < 0 ? 'heroicon-o-exclamation-triangle' : null;
                    }),

                Tables\Columns\TextColumn::make('days_to_expiry')
                    ->label('Days to Expiry')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => match(true) {
                        $state < 0 => 'danger',
                        $state <= 30 => 'warning',
                        $state <= 90 => 'info',
                        default => 'success'
                    })
                    ->getStateUsing(function (ItemBatch $record): int {
                        if (!$record->expiry_date) return 999;
                        return now()->diffInDays($record->expiry_date, false);
                    })
                    ->formatStateUsing(function (int $state): string {
                        if ($state === 999) return 'No expiry';
                        if ($state < 0) return 'Expired ' . abs($state) . ' days ago';
                        return $state . ' days';
                    }),

                Tables\Columns\TextColumn::make('initial_quantity')
                    ->label('Initial Qty')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Current Qty')
                    ->alignCenter()
                    ->badge()
                    ->color(function (ItemBatch $record): ?string  {
                        $current = $record->stockBalances()->sum('quantity');
                        $initial = $record->initial_quantity;

                        return match(true) {
                            $current <= 0 => 'danger',
                            $current <= ($initial * 0.2) => 'warning',
                            default => 'success'
                        };
                    })
                    ->getStateUsing(fn (ItemBatch $record): int =>
                        $record->stockBalances()->sum('quantity')),

                Tables\Columns\TextColumn::make('usage_percentage')
                    ->label('Used %')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (float $state): string => match(true) {
                        $state >= 100 => 'danger',
                        $state >= 80 => 'warning',
                        $state >= 50 => 'info',
                        default => 'success'
                    })
                    ->getStateUsing(function (ItemBatch $record): float {
                        $current = $record->stockBalances()->sum('quantity');
                        $initial = $record->initial_quantity;

                        if ($initial <= 0) return 0;
                        return round((($initial - $current) / $initial) * 100, 1);
                    })
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1) . '%'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (ItemBatch $record): string {
                        if (!$record->expiry_date) return 'No expiry';

                        $daysToExpiry = now()->diffInDays($record->expiry_date, false);
                        $current = $record->stockBalances()->sum('quantity');

                        if ($current <= 0) return 'Depleted';
                        if ($daysToExpiry < 0) return 'Expired';
                        if ($daysToExpiry <= 30) return 'Expiring Soon';

                        return 'Active';
                    })
                    ->color(fn (string $state): string => match($state) {
                        'Active' => 'success',
                        'Expiring Soon' => 'warning',
                        'Expired' => 'danger',
                        'Depleted' => 'gray',
                        'No expiry' => 'info',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('locations_count')
                    ->label('Locations')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (ItemBatch $record): int =>
                        $record->stockBalances()->distinct('location_id')->count()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNotNull('expiry_date')
                              ->where('expiry_date', '<=', now()->addDays(30))
                              ->where('expiry_date', '>=', now()))
                    ->toggle(),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNotNull('expiry_date')
                              ->where('expiry_date', '<', now()))
                    ->toggle(),

                Tables\Filters\Filter::make('active')
                    ->label('Active Batches')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereHas('stockBalances', fn ($q) =>
                            $q->where('quantity', '>', 0)))
                    ->toggle(),

                Tables\Filters\Filter::make('depleted')
                    ->label('Depleted')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDoesntHave('stockBalances', fn ($q) =>
                            $q->where('quantity', '>', 0)))
                    ->toggle(),

                Tables\Filters\Filter::make('no_expiry')
                    ->label('No Expiry Date')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNull('expiry_date'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Create New Batch')
                    ->successNotificationTitle('Batch created successfully'),

                Tables\Actions\Action::make('bulk_create')
                    ->label('Bulk Create Batches')
                    ->icon('heroicon-o-plus-circle')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('prefix')
                            ->label('Batch Number Prefix')
                            ->placeholder('e.g., BATCH-2024-'),

                        Forms\Components\TextInput::make('start_number')
                            ->label('Starting Number')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Number of Batches')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(50),

                        Forms\Components\TextInput::make('padding')
                            ->label('Number Padding')
                            ->numeric()
                            ->default(3)
                            ->helperText('Number of digits (e.g., 3 = 001, 002, etc.)'),

                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->minDate(today()),

                        Forms\Components\TextInput::make('initial_quantity')
                            ->label('Initial Quantity per Batch')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ])
                    ->action(function (array $data): void {
                        $created = 0;
                        $inventoryItem = $this->getOwnerRecord();

                        for ($i = 0; $i < $data['quantity']; $i++) {
                            $number = $data['start_number'] + $i;
                            $paddedNumber = str_pad($number, $data['padding'], '0', STR_PAD_LEFT);
                            $batchNo = ($data['prefix'] ?? '') . $paddedNumber;

                            try {
                                ItemBatch::create([
                                    'inventory_item_id' => $inventoryItem->id,
                                    'batch_no' => $batchNo,
                                    'expiry_date' => $data['expiry_date'] ?? null,
                                    'initial_quantity' => $data['initial_quantity'],
                                ]);
                                $created++;
                            } catch (\Exception $e) {
                                // Skip duplicates
                                continue;
                            }
                        }

                        Notification::make()
                            ->title("Created {$created} batches")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('view_stock_balances')
                    ->label('View Stock')
                    ->icon('heroicon-o-cube')
                    ->color('info')
                    ->modalHeading(fn (ItemBatch $record): string =>
                        "Stock Balances - Batch {$record->batch_no}")
                    ->modalContent(function (ItemBatch $record): string {
                        $balances = $record->stockBalances()->with('location')->get();

                        if ($balances->isEmpty()) {
                            return '<p class="text-gray-500">No stock balances found for this batch.</p>';
                        }

                        $html = '<div class="space-y-2">';
                        foreach ($balances as $balance) {
                            $locationName = $balance->location?->name ?? 'Main Store';
                            $html .= "<div class='flex justify-between p-2 bg-gray-50 rounded'>";
                            $html .= "<span class='font-medium'>{$locationName}</span>";
                            $html .= "<span class='text-blue-600'>{$balance->quantity} units</span>";
                            $html .= "</div>";
                        }
                        $html .= '</div>';

                        return $html;
                    }),

                Tables\Actions\Action::make('extend_expiry')
                    ->label('Extend Expiry')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (ItemBatch $record): bool =>
                        $record->expiry_date && $record->expiry_date->isFuture())
                    ->form([
                        Forms\Components\DatePicker::make('new_expiry_date')
                            ->label('New Expiry Date')
                            ->required()
                            ->minDate(fn (ItemBatch $record): string =>
                                $record->expiry_date->format('Y-m-d')),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Extension')
                            ->required()
                            ->placeholder('Explain why the expiry date is being extended...'),
                    ])
                    ->action(function (ItemBatch $record, array $data): void {
                        $oldExpiry = $record->expiry_date->format('Y-m-d');

                        $record->update([
                            'expiry_date' => $data['new_expiry_date'],
                            'notes' => ($record->notes ? $record->notes . "\n\n" : '') .
                                      "Expiry extended from {$oldExpiry} to {$data['new_expiry_date']}. " .
                                      "Reason: {$data['reason']}",
                        ]);

                        Notification::make()
                            ->title('Expiry date extended successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('This will delete the batch and all associated stock balances.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('bulk_extend_expiry')
                        ->label('Extend Expiry Dates')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->form([
                            Forms\Components\DatePicker::make('new_expiry_date')
                                ->label('New Expiry Date')
                                ->required()
                                ->minDate(today()),

                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for Extension')
                                ->required()
                                ->placeholder('Explain why expiry dates are being extended...'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                if ($record->expiry_date) {
                                    $oldExpiry = $record->expiry_date->format('Y-m-d');

                                    $record->update([
                                        'expiry_date' => $data['new_expiry_date'],
                                        'notes' => ($record->notes ? $record->notes . "\n\n" : '') .
                                                  "Expiry extended from {$oldExpiry} to {$data['new_expiry_date']}. " .
                                                  "Reason: {$data['reason']}",
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title('Expiry dates extended successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No batches')
            ->emptyStateDescription('This item does not have any batches/lots tracked yet.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }
}
