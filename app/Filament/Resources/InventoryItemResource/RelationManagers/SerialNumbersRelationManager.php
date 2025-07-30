<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Models\SerialNumber;
use App\Models\Facility;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SerialNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'serialNumbers';
    protected static ?string $title = 'Serial Numbers';
    protected static ?string $modelLabel = 'Serial Number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Serial Details')
                    ->schema([
                        Forms\Components\TextInput::make('serial_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tag_number')
                            ->label('Asset Tag Number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'assigned' => 'Assigned',
                                'in_transit' => 'In Transit',
                                'damaged' => 'Damaged',
                                'lost' => 'Lost',
                                'retired' => 'Retired',
                            ])
                            ->default('available')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('condition')
                            ->options([
                                'new' => 'New',
                                'excellent' => 'Excellent',
                                'good' => 'Good',
                                'fair' => 'Fair',
                                'poor' => 'Poor',
                                'damaged' => 'Damaged',
                            ])
                            ->default('new')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Location & Assignment')
                    ->schema([
                        Forms\Components\Select::make('current_location_type')
                            ->label('Location Type')
                            ->options([
                                'main_store' => 'Main Store',
                                'facility' => 'Facility',
                            ])
                            ->default('main_store')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('current_location_id')
                            ->label('Facility')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('current_location_type') === 'facility')
                            ->required(fn (Forms\Get $get): bool => $get('current_location_type') === 'facility'),

                        Forms\Components\Select::make('assigned_to_user_id')
                            ->label('Assigned To')
                            ->options(fn (): array => User::pluck('full_name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('status') === 'assigned')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'assigned'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('GPS & Tracking')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dates & Warranty')
                    ->schema([
                        Forms\Components\DatePicker::make('acquisition_date')
                            ->label('Acquisition Date'),

                        Forms\Components\DatePicker::make('warranty_expiry_date')
                            ->label('Warranty Expiry')
                            ->afterOrEqual('acquisition_date'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('tag_number')
                    ->label('Tag #')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No tag'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (SerialNumber $record): string => $record->status_badge_color),

                Tables\Columns\TextColumn::make('condition')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'new', 'excellent' => 'success',
                        'good' => 'info',
                        'fair' => 'warning',
                        'poor', 'damaged' => 'danger',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('current_location_name')
                    ->label('Location')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('assignedToUser.full_name')
                    ->label('Assigned To')
                    ->searchable()
                    ->placeholder('Not assigned')
                    ->wrap(),

                Tables\Columns\TextColumn::make('warranty_status')
                    ->label('Warranty')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'active' => 'success',
                        'expiring_soon' => 'warning',
                        'expired' => 'danger',
                        'no_warranty' => 'gray',
                        default => 'gray'
                    }),

                Tables\Columns\IconColumn::make('coordinates')
                    ->label('GPS')
                    ->boolean()
                    ->getStateUsing(fn (SerialNumber $record): bool => !empty($record->coordinates))
                    ->trueIcon('heroicon-o-map-pin')
                    ->falseIcon('heroicon-o-x-mark'),

                Tables\Columns\TextColumn::make('last_tracked_at')
                    ->label('Last Tracked')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'assigned' => 'Assigned',
                        'in_transit' => 'In Transit',
                        'damaged' => 'Damaged',
                        'lost' => 'Lost',
                        'retired' => 'Retired',
                    ]),

                Tables\Filters\SelectFilter::make('condition')
                    ->options([
                        'new' => 'New',
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                        'damaged' => 'Damaged',
                    ]),

                Tables\Filters\Filter::make('assigned')
                    ->label('Assigned Items')
                    ->query(fn (Builder $query): Builder => $query->assigned())
                    ->toggle(),

                Tables\Filters\Filter::make('available')
                    ->label('Available Items')
                    ->query(fn (Builder $query): Builder => $query->available())
                    ->toggle(),

                Tables\Filters\Filter::make('warranty_expiring')
                    ->label('Warranty Expiring')
                    ->query(fn (Builder $query): Builder => $query->warrantyExpiring())
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Add Serial Number')
                    ->successNotificationTitle('Serial number created successfully'),

                Tables\Actions\Action::make('bulk_create')
                    ->label('Bulk Create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('prefix')
                            ->label('Serial Number Prefix')
                            ->placeholder('e.g., DEV-2024-'),

                        Forms\Components\TextInput::make('start_number')
                            ->label('Starting Number')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Create')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100),

                        Forms\Components\TextInput::make('padding')
                            ->label('Number Padding')
                            ->numeric()
                            ->default(4)
                            ->helperText('Number of digits (e.g., 4 = 0001, 0002, etc.)'),
                    ])
                    ->action(function (array $data): void {
                        $created = 0;
                        $inventoryItem = $this->getOwnerRecord();

                        for ($i = 0; $i < $data['quantity']; $i++) {
                            $number = $data['start_number'] + $i;
                            $paddedNumber = str_pad($number, $data['padding'], '0', STR_PAD_LEFT);
                            $serialNumber = ($data['prefix'] ?? '') . $paddedNumber;

                            try {
                                SerialNumber::create([
                                    'inventory_item_id' => $inventoryItem->id,
                                    'serial_number' => $serialNumber,
                                    'status' => 'available',
                                    'condition' => 'new',
                                    'current_location_type' => 'main_store',
                                ]);
                                $created++;
                            } catch (\Exception $e) {
                                // Skip duplicates
                                continue;
                            }
                        }

                        Notification::make()
                            ->title("Created {$created} serial numbers")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('assign')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->visible(fn (SerialNumber $record): bool => $record->status === 'available')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Assign To User')
                            ->options(fn (): array => User::pluck('full_name', 'id')->toArray())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SerialNumber $record, array $data): void {
                        $user = User::find($data['user_id']);
                        $record->assignToUser($user);

                        Notification::make()
                            ->title('Item assigned successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('unassign')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SerialNumber $record): bool => $record->status === 'assigned')
                    ->action(function (SerialNumber $record): void {
                        $record->unassign();

                        Notification::make()
                            ->title('Item unassigned successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('update_location')
                    ->icon('heroicon-o-map-pin')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('location_type')
                            ->options([
                                'main_store' => 'Main Store',
                                'facility' => 'Facility',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('location_id')
                            ->label('Facility')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => $get('location_type') === 'facility')
                            ->required(fn (Forms\Get $get): bool => $get('location_type') === 'facility'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('latitude')
                                    ->numeric()
                                    ->step(0.00000001),

                                Forms\Components\TextInput::make('longitude')
                                    ->numeric()
                                    ->step(0.00000001),
                            ]),
                    ])
                    ->action(function (SerialNumber $record, array $data): void {
                        $locationId = $data['location_type'] === 'facility' ? $data['location_id'] : null;

                        $record->updateLocation(
                            $locationId,
                            $data['location_type'],
                            $data['latitude'] ?? null,
                            $data['longitude'] ?? null
                        );

                        Notification::make()
                            ->title('Location updated successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('bulk_update_location')
                        ->label('Update Location')
                        ->icon('heroicon-o-map-pin')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('location_type')
                                ->options([
                                    'main_store' => 'Main Store',
                                    'facility' => 'Facility',
                                ])
                                ->required()
                                ->live(),

                            Forms\Components\Select::make('location_id')
                                ->label('Facility')
                                ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                                ->searchable()
                                ->visible(fn (Forms\Get $get): bool => $get('location_type') === 'facility')
                                ->required(fn (Forms\Get $get): bool => $get('location_type') === 'facility'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $locationId = $data['location_type'] === 'facility' ? $data['location_id'] : null;

                            foreach ($records as $record) {
                                $record->updateLocation($locationId, $data['location_type']);
                            }

                            Notification::make()
                                ->title('Locations updated successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No serial numbers')
            ->emptyStateDescription('This item does not have any serial numbers tracked yet.')
            ->emptyStateIcon('heroicon-o-hashtag');
    }
}
