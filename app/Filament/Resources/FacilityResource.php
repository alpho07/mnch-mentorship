<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityResource\Pages;
use App\Filament\Resources\FacilityResource\RelationManagers\UsersRelationManager;
use App\Models\County;
use App\Models\Facility;
use App\Models\Subcounty;
use App\Models\FacilityType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Geographic Structure';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Facility Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Facility Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('subcounty_id')
                            ->label('Subcounty')
                            ->relationship('subcounty', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $set('hub_id', null);
                            }),

                        Forms\Components\Select::make('facility_type_id')
                            ->label('Facility Type')
                            ->relationship('facilityType', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('mfl_code')
                            ->label('MFL Code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('uid')
                            ->label('Unique Identifier')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Facility Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('is_hub')
                            ->label('Is Hub Facility?')
                            ->default(false)
                            ->live()
                            ->helperText('Hub facilities coordinate with spoke facilities'),

                        Forms\Components\Toggle::make('is_central_store')
                            ->label('Is Central Store/Warehouse?')
                            ->default(false)
                            ->live()
                            ->helperText('Central stores receive supplies and distribute to facilities'),

                        Forms\Components\Select::make('hub_id')
                            ->label('Parent Hub (if this is a spoke)')
                            ->options(function (Forms\Get $get) {
                                $subcountyId = $get('subcounty_id');
                                if (!$subcountyId) {
                                    return [];
                                }

                                return Facility::where('is_hub', true)
                                    ->where('subcounty_id', $subcountyId)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => !$get('is_hub'))
                            ->helperText('Select the hub facility this spoke facility reports to'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Central Store Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('storage_capacity')
                            ->label('Storage Capacity')
                            ->placeholder('e.g., 1000 sqm, 500 pallets')
                            ->maxLength(255)
                            ->helperText('Describe the storage capacity of this central store'),

                        Forms\Components\KeyValue::make('operating_hours')
                            ->label('Operating Hours')
                            ->keyLabel('Day')
                            ->valueLabel('Hours')
                            ->default([
                                'Monday' => '8:00 AM - 5:00 PM',
                                'Tuesday' => '8:00 AM - 5:00 PM',
                                'Wednesday' => '8:00 AM - 5:00 PM',
                                'Thursday' => '8:00 AM - 5:00 PM',
                                'Friday' => '8:00 AM - 5:00 PM',
                                'Saturday' => 'Closed',
                                'Sunday' => 'Closed',
                            ])
                            ->helperText('Set operating hours for this central store'),

                        Forms\Components\Textarea::make('storage_conditions')
                            ->label('Storage Conditions')
                            ->rows(3)
                            ->placeholder('e.g., Temperature controlled, Humidity controlled, Refrigerated sections available')
                            ->helperText('Describe special storage conditions available'),

                        Forms\Components\Textarea::make('distribution_notes')
                            ->label('Distribution Notes')
                            ->rows(3)
                            ->placeholder('e.g., Covers 5 counties, Main distribution hub for Region X')
                            ->helperText('Notes about distribution coverage and capabilities'),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('is_central_store'))
                    ->collapsible(),

                Forms\Components\Section::make('Location Coordinates')
                    ->schema([
                        Forms\Components\TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('e.g., -1.286389')
                            ->helperText('GPS latitude coordinate'),

                        Forms\Components\TextInput::make('long')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('e.g., 36.817223')
                            ->helperText('GPS longitude coordinate'),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('get_location')
                                ->label('Get Current Location')
                                ->icon('heroicon-o-map-pin')
                                ->color('info')
                                ->action(function (Forms\Set $set) {
                                    // JavaScript to get current location
                                })
                                ->extraAttributes(['onclick' => 'getCurrentLocation()']),
                                //->columnSpanFull(),
                        ]),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Facility Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('mfl_code')
                    ->label('MFL Code')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('subcounty.name')
                    ->label('Subcounty')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('subcounty.county.name')
                    ->label('County')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('facilityType.name')
                    ->label('Type')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_hub')
                    ->boolean()
                    ->label('Hub?')
                    ->sortable()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\IconColumn::make('is_central_store')
                    ->boolean()
                    ->label('Central Store?')
                    ->sortable()
                    ->trueIcon('heroicon-o-building-storefront')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('hub.name')
                    ->label('Parent Hub')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->placeholder('Standalone'),

                Tables\Columns\TextColumn::make('spoke_count')
                    ->label('Spokes')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (?Facility $record): int => $record?->spokes()->count() ?? 0)
                    ->visible(fn (?Facility $record): bool => $record?->is_hub ?? false),

                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Stock Value')
                    ->money('KES')
                    ->getStateUsing(fn (?Facility $record): float => $record?->total_stock_value ?? 0)
                    ->visible(fn (?Facility $record): bool => $record?->is_central_store ?? false)
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('distribution_facilities_count')
                    ->label('Serves Facilities')
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(fn (?Facility $record): int =>
                        $record?->is_central_store ? $record->getDistributionFacilities()->count() : 0
                    )
                    ->visible(fn (?Facility $record): bool => $record?->is_central_store ?? false),

                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->label('Created')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('subcounty_id')
                    ->label('Subcounty')
                    ->relationship('subcounty', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('county')
                    ->label('County')
                    ->options(County::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $query->whereHas('subcounty.county', function ($q) use ($data) {
                                $q->where('id', $data['value']);
                            });
                        }
                    }),

                Tables\Filters\SelectFilter::make('facility_type_id')
                    ->label('Type')
                    ->relationship('facilityType', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_hub')
                    ->label('Hub Status')
                    ->trueLabel('Hub Facilities')
                    ->falseLabel('Non-Hub Facilities')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_central_store')
                    ->label('Central Store Status')
                    ->trueLabel('Central Stores')
                    ->falseLabel('Regular Facilities')
                    ->native(false),

                Tables\Filters\Filter::make('has_coordinates')
                    ->label('Has GPS Coordinates')
                    ->query(fn ($query) => $query->whereNotNull('lat')->whereNotNull('long')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_stock')
                    ->label('View Stock')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->visible(fn (?Facility $record): bool => $record?->is_central_store ?? false)
                    ->url(fn (?Facility $record): string =>
                        $record
                            ? route('filament.admin.resources.stock-levels.index', [
                                'tableFilters[facility][value]' => $record->id
                            ])
                            : '#'
                    ),
                Tables\Actions\Action::make('create_distribution')
                    ->label('Create Distribution')
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->visible(fn (?Facility $record): bool => $record?->is_central_store ?? false)
                    ->url(fn (?Facility $record): string =>
                        $record
                            ? route('filament.admin.resources.stock-requests.create', [
                                'central_store_id' => $record->id
                            ])
                            : '#'
                    ),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('make_central_store')
                        ->label('Mark as Central Store')
                        ->icon('heroicon-o-building-storefront')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['is_central_store' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('remove_central_store')
                        ->label('Remove Central Store Status')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_central_store' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Facility Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('subcounty.county.name')
                            ->label('County')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('subcounty.name')
                            ->label('Subcounty')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Facility Name')
                            ->size('xl')
                            ->weight('bold')
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('mfl_code')
                            ->label('MFL Code')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('facilityType.name')
                            ->label('Facility Type')
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Facility Configuration')
                    ->schema([
                        Infolists\Components\TextEntry::make('is_hub')
                            ->label('Hub Status')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Hub Facility' : 'Non-Hub Facility')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                        Infolists\Components\TextEntry::make('is_central_store')
                            ->label('Central Store Status')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Central Store/Warehouse' : 'Regular Facility')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                        Infolists\Components\TextEntry::make('hub.name')
                            ->label('Parent Hub')
                            ->badge()
                            ->color('info')
                            ->placeholder('Standalone facility')
                            ->visible(fn (?Facility $record): bool => !$record?->is_hub),

                        Infolists\Components\TextEntry::make('spoke_count')
                            ->label('Spoke Facilities')
                            ->badge()
                            ->color('primary')
                            ->visible(fn (?Facility $record): bool => $record?->is_hub ?? false),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Central Store Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('storage_capacity')
                            ->label('Storage Capacity')
                            ->placeholder('Not specified'),

                        Infolists\Components\KeyValueEntry::make('operating_hours')
                            ->label('Operating Hours'),

                        Infolists\Components\TextEntry::make('storage_conditions')
                            ->label('Storage Conditions')
                            ->placeholder('Standard conditions')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('distribution_notes')
                            ->label('Distribution Coverage')
                            ->placeholder('Not specified')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?Facility $record): bool => $record?->is_central_store ?? false),

                Infolists\Components\Section::make('Inventory Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_stock_value')
                            ->label('Total Stock Value')
                            ->money('KES')
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('low_stock_items_count')
                            ->label('Low Stock Items')
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('out_of_stock_items_count')
                            ->label('Out of Stock Items')
                            ->badge()
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('distribution_facilities_count')
                            ->label('Facilities Served')
                            ->badge()
                            ->color('primary')
                            ->getStateUsing(fn (?Facility $record): int =>
                                $record?->is_central_store ? $record->getDistributionFacilities()->count() : 0
                            ),
                    ])
                    ->columns(4)
                    ->visible(fn (?Facility $record): bool => $record?->is_central_store ?? false),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_count')
                            ->label('Staff Members')
                            ->badge()
                            ->color('success')
                            ->getStateUsing(fn (?Facility $record): int => $record?->users()->count() ?? 0),

                        Infolists\Components\TextEntry::make('coordinates')
                            ->label('GPS Coordinates')
                            ->formatStateUsing(fn (?Facility $record): string =>
                                $record && $record->coordinates
                                    ? "Lat: {$record->lat}, Long: {$record->long}"
                                    : 'Not available'
                            )
                            ->badge()
                            ->color(fn (?Facility $record): string => $record && $record->coordinates ? 'info' : 'gray'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Shared\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'view' => Pages\ViewFacility::route('/{record}'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'mfl_code', 'uid', 'subcounty.name', 'subcounty.county.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [
            'Subcounty' => $record->subcounty?->name,
            'County' => $record->subcounty?->county?->name,
            'MFL Code' => $record->mfl_code,
        ];

        if ($record->is_central_store) {
            $details['Type'] = 'Central Store';
        } elseif ($record->is_hub) {
            $details['Type'] = 'Hub Facility';
        }

        return $details;
    }
}
