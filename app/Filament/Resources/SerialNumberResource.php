<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerialNumberResource\Pages;
use App\Filament\Resources\SerialNumberResource\RelationManagers;
use App\Models\SerialNumber;
use App\Models\InventoryItem;
use App\Models\Facility;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Collection;

class SerialNumberResource extends Resource
{
    protected static ?string $model = SerialNumber::class;
    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'serial_number';

       public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Serial Number Details')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->schema([
                                Section::make('Item & Serial Details')
                                    ->schema([
                                        Forms\Components\Select::make('inventory_item_id')
                                            ->label('Inventory Item')
                                            ->relationship('inventoryItem', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Forms\Set $set) {
                                                $itemId = $get('inventory_item_id');
                                                if ($itemId) {
                                                    $item = InventoryItem::find($itemId);
                                                    if ($item && !$item->is_serialized) {
                                                        Notification::make()
                                                            ->warning()
                                                            ->title('Warning')
                                                            ->body('This item is not configured for serialization.')
                                                            ->send();
                                                    }
                                                }
                                            }),

                                        Forms\Components\TextInput::make('serial_number')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('Enter serial number'),

                                        Forms\Components\TextInput::make('tag_number')
                                            ->label('Asset Tag Number')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('Optional asset tag'),

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
                            ]),

                        Tabs\Tab::make('Location & Assignment')
                            ->schema([
                                Section::make('Current Location')
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
                                            ->relationship('facility', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => $get('current_location_type') === 'facility')
                                            ->required(fn (Get $get): bool => $get('current_location_type') === 'facility'),

                                        Forms\Components\Select::make('assigned_to_user_id')
                                            ->label('Assigned To')
                                            ->relationship('assignedToUser', 'full_name')
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => $get('status') === 'assigned')
                                            ->required(fn (Get $get): bool => $get('status') === 'assigned'),
                                    ])
                                    ->columns(2),

                                Section::make('GPS Coordinates')
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->numeric()
                                            ->step(0.00000001)
                                            ->placeholder('Enter latitude'),

                                        Forms\Components\TextInput::make('longitude')
                                            ->numeric()
                                            ->step(0.00000001)
                                            ->placeholder('Enter longitude'),

                                        Forms\Components\DateTimePicker::make('last_tracked_at')
                                            ->label('Last Tracked')
                                            ->disabled()
                                            ->visibleOn('edit'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Acquisition & Warranty')
                            ->schema([
                                Section::make('Purchase Information')
                                    ->schema([
                                        Forms\Components\DatePicker::make('acquisition_date')
                                            ->label('Acquisition Date')
                                            ->placeholder('When was this item acquired?'),

                                        Forms\Components\DatePicker::make('warranty_expiry_date')
                                            ->label('Warranty Expiry')
                                            ->placeholder('When does warranty expire?')
                                            ->afterOrEqual('acquisition_date'),
                                    ])
                                    ->columns(2),

                                Section::make('Additional Information')
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->rows(4)
                                            ->placeholder('Any additional notes about this item...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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

                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (SerialNumber $record): string => $record->status_badge_color)
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'available' => 'Available',
                        'assigned' => 'Assigned',
                        'in_transit' => 'In Transit',
                        'damaged' => 'Damaged',
                        'lost' => 'Lost',
                        'retired' => 'Retired',
                        default => 'Unknown'
                    }),

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
                    })
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'active' => 'Active',
                        'expiring_soon' => 'Expiring Soon',
                        'expired' => 'Expired',
                        'no_warranty' => 'No Warranty',
                        default => 'Unknown'
                    }),

                Tables\Columns\TextColumn::make('warranty_expiry_date')
                    ->label('Warranty Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (SerialNumber $record): string =>
                        $record->warranty_status === 'expired' ? 'danger' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('coordinates')
                    ->label('GPS')
                    ->boolean()
                    ->getStateUsing(fn (SerialNumber $record): bool => !empty($record->coordinates))
                    ->trueIcon('heroicon-o-map-pin')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('last_tracked_at')
                    ->label('Last Tracked')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never tracked')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('acquisition_date')
                    ->label('Acquired')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'assigned' => 'Assigned',
                        'in_transit' => 'In Transit',
                        'damaged' => 'Damaged',
                        'lost' => 'Lost',
                        'retired' => 'Retired',
                    ]),

                SelectFilter::make('condition')
                    ->options([
                        'new' => 'New',
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                        'damaged' => 'Damaged',
                    ]),

                SelectFilter::make('inventory_item')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('facility')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('assigned_user')
                    ->relationship('assignedToUser', 'full_name')
                    ->searchable()
                    ->preload()
                    ->label('Assigned To'),

                Filter::make('warranty_expiring')
                    ->label('Warranty Expiring Soon')
                    ->query(fn (Builder $query): Builder => $query->warrantyExpiring())
                    ->toggle(),

                Filter::make('has_gps')
                    ->label('Has GPS Coordinates')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNotNull('latitude')->whereNotNull('longitude'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('assign')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->visible(fn (SerialNumber $record): bool =>
                        $record->status === 'available')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Assign To User')
                            ->relationship('assignedToUser', 'full_name')
                            ->searchable()
                            ->preload()
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
                    ->visible(fn (SerialNumber $record): bool =>
                        $record->status === 'assigned')
                    ->action(function (SerialNumber $record): void {
                        $record->unassign();

                        Notification::make()
                            ->title('Item unassigned successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('update_location')
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
                            ->relationship('facility', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('location_type') === 'facility')
                            ->required(fn (Get $get): bool => $get('location_type') === 'facility'),

                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001),
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

                Tables\Actions\Action::make('change_status')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'assigned' => 'Assigned',
                                'in_transit' => 'In Transit',
                                'damaged' => 'Damaged',
                                'lost' => 'Lost',
                                'retired' => 'Retired',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Reason for status change')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (SerialNumber $record, array $data): void {
                        $record->update(['status' => $data['status']]);

                        // Create tracking record
                        $record->trackingHistory()->create([
                            'action' => 'status_changed',
                            'notes' => $data['notes'],
                            'metadata' => [
                                'old_status' => $record->getOriginal('status'),
                                'new_status' => $data['status'],
                            ],
                        ]);

                        Notification::make()
                            ->title('Status updated successfully')
                            ->success()
                            ->send();
                    }),
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
                                ->relationship('facility', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('location_type') === 'facility')
                                ->required(fn (Get $get): bool => $get('location_type') === 'facility'),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Serial Number Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('serial_number')
                                            ->label('Serial Number')
                                            ->copyable()
                                            ->size('lg')
                                            ->weight('bold'),

                                        Infolists\Components\TextEntry::make('tag_number')
                                            ->label('Asset Tag')
                                            ->copyable()
                                            ->placeholder('No tag assigned'),

                                        Infolists\Components\TextEntry::make('inventoryItem.name')
                                            ->label('Item'),

                                        Infolists\Components\TextEntry::make('inventoryItem.sku')
                                            ->label('SKU')
                                            ->copyable(),

                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (SerialNumber $record): string => $record->status_badge_color),

                                        Infolists\Components\TextEntry::make('condition')
                                            ->badge(),
                                    ]),

                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('current_location_name')
                                            ->label('Current Location'),

                                        Infolists\Components\TextEntry::make('assignedToUser.full_name')
                                            ->label('Assigned To')
                                            ->placeholder('Not assigned'),

                                        Infolists\Components\TextEntry::make('last_tracked_at')
                                            ->label('Last Tracked')
                                            ->dateTime()
                                            ->placeholder('Never tracked'),

                                        Infolists\Components\TextEntry::make('coordinates')
                                            ->label('GPS Coordinates')
                                            ->formatStateUsing(fn (?array $state): string =>
                                                $state ? "{$state['latitude']}, {$state['longitude']}" : 'No coordinates')
                                            ->copyable(),
                                    ]),
                                ]),
                        ]),
                    ]),

                Infolists\Components\Section::make('Acquisition & Warranty')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('acquisition_date')
                                    ->date()
                                    ->placeholder('Not recorded'),

                                Infolists\Components\TextEntry::make('warranty_expiry_date')
                                    ->date()
                                    ->placeholder('No warranty'),

                                Infolists\Components\TextEntry::make('warranty_status')
                                    ->badge()
                                    ->color(fn (string $state): string => match($state) {
                                        'active' => 'success',
                                        'expiring_soon' => 'warning',
                                        'expired' => 'danger',
                                        'no_warranty' => 'gray',
                                        default => 'gray'
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])
                    ->visible(fn (SerialNumber $record): bool => !empty($record->notes)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
           // RelationManagers\TrackingHistoryRelationManager::class,
           // RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSerialNumbers::route('/'),
            'create' => Pages\CreateSerialNumber::route('/create'),
            'view' => Pages\ViewSerialNumber::route('/{record}'),
            'edit' => Pages\EditSerialNumber::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
