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
                                // Clear hub selection when subcounty changes
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

                Forms\Components\Section::make('Hub Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('is_hub')
                            ->label('Is Hub Facility?')
                            ->default(false)
                            ->live()
                            ->helperText('Hub facilities coordinate with spoke facilities'),

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
                            ->required(fn (Forms\Get $get) => !$get('is_hub'))
                            ->helperText('Select the hub facility this spoke facility reports to'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Location Coordinates')
                    ->schema([
                        Forms\Components\TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('e.g., -1.286389'),

                        Forms\Components\TextInput::make('long')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('e.g., 36.817223'),
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
                    ->getStateUsing(fn (Facility $record): int => $record->spokes()->count())
                    ->visible(fn (Facility $record): bool => $record->is_hub),
                
                Tables\Columns\TextColumn::make('training_count')
                    ->label('Trainings')
                    ->badge()
                    ->color('primary'),
                
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
                
                Tables\Filters\Filter::make('has_coordinates')
                    ->label('Has GPS Coordinates')
                    ->query(fn ($query) => $query->whereNotNull('lat')->whereNotNull('long')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
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
                        Infolists\Components\TextEntry::make('subcounty.county.division.name')
                            ->label('Division')
                            ->badge()
                            ->color('primary'),
                        
                        Infolists\Components\TextEntry::make('subcounty.county.name')
                            ->label('County')
                            ->badge()
                            ->color('info'),
                        
                        Infolists\Components\TextEntry::make('subcounty.name')
                            ->label('Subcounty')
                            ->badge()
                            ->color('success'),
                        
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
                
                Infolists\Components\Section::make('Hub Configuration')
                    ->schema([
                        Infolists\Components\TextEntry::make('is_hub')
                            ->label('Hub Status')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Hub Facility' : 'Non-Hub Facility')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                        
                        Infolists\Components\TextEntry::make('hub.name')
                            ->label('Parent Hub')
                            ->badge()
                            ->color('info')
                            ->placeholder('Standalone facility')
                            ->visible(fn (Facility $record): bool => !$record->is_hub),
                        
                        Infolists\Components\TextEntry::make('spoke_count')
                            ->label('Spoke Facilities')
                            ->badge()
                            ->color('primary')
                            ->visible(fn (Facility $record): bool => $record->is_hub),
                    ])
                    ->columns(3),
                
                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('training_count')
                            ->label('Total Trainings')
                            ->badge()
                            ->color('primary'),
                        
                        Infolists\Components\TextEntry::make('user_count')
                            ->label('Staff Members')
                            ->badge()
                            ->color('success')
                            ->getStateUsing(fn (Facility $record): int => $record->users()->count()),
                        
                        Infolists\Components\TextEntry::make('coordinates')
                            ->label('GPS Coordinates')
                            ->formatStateUsing(fn (Facility $record): string => 
                                $record->coordinates 
                                    ? "Lat: {$record->lat}, Long: {$record->long}"
                                    : 'Not available'
                            )
                            ->badge()
                            ->color(fn (Facility $record): string => $record->coordinates ? 'info' : 'gray'),
                    ])
                    ->columns(3),
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
        return [
            'Subcounty' => $record->subcounty?->name,
            'County' => $record->subcounty?->county?->name,
            'MFL Code' => $record->mfl_code,
        ];
    }
}