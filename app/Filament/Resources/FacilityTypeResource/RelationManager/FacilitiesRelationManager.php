<?php

namespace App\Filament\Resources\FacilityTypeResource\RelationManagers;

use App\Models\Facility;
use App\Models\Subcounty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FacilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'facilities';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Facility Details')
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
                            ->required(),

                        Forms\Components\TextInput::make('mfl_code')
                            ->label('MFL Code')
                            ->maxLength(50)
                            ->unique(Facility::class, 'mfl_code', ignoreRecord: true),

                        Forms\Components\TextInput::make('uid')
                            ->label('Unique Identifier')
                            ->maxLength(50)
                            ->unique(Facility::class, 'uid', ignoreRecord: true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Hub Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('is_hub')
                            ->label('Is Hub Facility?')
                            ->default(false)
                            ->live(),

                        Forms\Components\Select::make('hub_id')
                            ->label('Parent Hub')
                            ->options(function (Forms\Get $get) {
                                $subcountyId = $get('subcounty_id');
                                if (!$subcountyId) {
                                    return [];
                                }

                                return Facility::where('is_hub', true)
                                    ->where('subcounty_id', $subcountyId)
                                    ->where('facility_type_id', $this->ownerRecord->id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->visible(fn(Forms\Get $get) => !$get('is_hub'))
                            ->helperText('Select hub facility of the same type in the same subcounty'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Location Coordinates')
                    ->schema([
                        Forms\Components\TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.0000001),

                        Forms\Components\TextInput::make('long')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.0000001),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Facility Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('subcounty.county.name')
                    ->label('County')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('subcounty.name')
                    ->label('Subcounty')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('mfl_code')
                    ->label('MFL Code')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_hub')
                    ->boolean()
                    ->label('Hub')
                    ->sortable()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\TextColumn::make('hub.name')
                    ->label('Parent Hub')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Standalone'),

                Tables\Columns\TextColumn::make('spoke_count')
                    ->label('Spokes')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn(Facility $record): int => $record->spokes()->count())
                    ->visible(function (?Facility $record): bool {
                        return $record ? $record->is_hub : false;
                    }),

                Tables\Columns\TextColumn::make('training_count')
                    ->label('Trainings')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
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
                    ->options(\App\Models\County::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $query->whereHas('subcounty.county', function ($q) use ($data) {
                                $q->where('id', $data['value']);
                            });
                        }
                    }),

                Tables\Filters\TernaryFilter::make('is_hub')
                    ->label('Hub Status')
                    ->trueLabel('Hub Facilities')
                    ->falseLabel('Non-Hub Facilities')
                    ->native(false),

                Tables\Filters\Filter::make('has_coordinates')
                    ->label('Has GPS Coordinates')
                    ->query(fn($query) => $query->whereNotNull('lat')->whereNotNull('long')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['facility_type_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(
                        fn(Facility $record): string =>
                        \App\Filament\Resources\FacilityResource::getUrl('view', ['record' => $record])
                    ),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('manage_staff')
                    ->label('Staff')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(
                        fn(Facility $record): string =>
                        \App\Filament\Resources\FacilityResource::getUrl('view', ['record' => $record]) . '#users'
                    ),

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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['facility_type_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->emptyStateHeading('No facilities of this type yet')
            ->emptyStateDescription('Create facilities of this type to start organizing your health infrastructure.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
