<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityResource\Pages;
use App\Models\Facility;
use App\Models\Subcounty;
use App\Models\FacilityType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Organization Units';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Facility Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('subcounty_id')
                    ->label('Subcounty')
                    ->options(Subcounty::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('facility_type_id')
                    ->label('Facility Type')
                    ->options(FacilityType::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('mfl_code')
                    ->label('MFL Code')
                    ->maxLength(50),

                Forms\Components\Toggle::make('is_hub')
                    ->label('Is Hub Facility?')
                     ->default(true)   
                    ->live(), // <--- Needed to reactively update fields

                Forms\Components\Select::make('hub_id')
                    ->label('Parent Hub (if this is a spoke)')
                    ->options(
                        \App\Models\Facility::where('is_hub', true)->pluck('name', 'id')
                    )
                    ->searchable()
                    ->visible(fn($get) => !$get('is_hub'))   // Show only if NOT a hub
                    ->required(fn($get) => !$get('is_hub')), // Required only if NOT a hub
                // ->nullable(), // Not needed, required() handles it

                Forms\Components\TextInput::make('lat')
                    ->label('Latitude')
                    ->numeric()
                    ->nullable(),

                Forms\Components\TextInput::make('long')
                    ->label('Longitude')
                    ->numeric()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Facility Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('mfl_code')->label('MFL Code')->sortable(),
                Tables\Columns\TextColumn::make('subcounty.name')->label('Subcounty')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subcounty.county.name')->label('County')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('facilityType.name')->label('Type')->sortable()->searchable(),
                Tables\Columns\IconColumn::make('is_hub')->boolean()->label('Hub?')->sortable(),
                Tables\Columns\TextColumn::make('hub.name')->label('Parent Hub')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('lat')->label('Lat'),
                Tables\Columns\TextColumn::make('long')->label('Long'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subcounty_id')
                    ->label('Subcounty')
                    ->options(Subcounty::all()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('facility_type_id')
                    ->label('Type')
                    ->options(FacilityType::all()->pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_hub')
                    ->label('Hub?'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }
}
