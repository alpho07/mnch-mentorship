<?php
namespace App\Filament\Resources\SubcountyResource\RelationManagers;

use App\Models\Facility;
use App\Models\FacilityType;
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
                        
                        Forms\Components\TextInput::make('uid')
                            ->label('Unique Identifier')
                            ->maxLength(50)
                            ->unique(Facility::class, 'uid', ignoreRecord: true),
                        
                        Forms\Components\TextInput::make('mfl_code')
                            ->label('MFL Code')
                            ->maxLength(50),
                        
                        Forms\Components\Select::make('facility_type_id')
                            ->label('Facility Type')
                            ->relationship('facilityType', 'name')
                            ->searchable()
                            ->preload(),
                        
                        Forms\Components\Toggle::make('is_hub')
                            ->label('Is Hub Facility')
                            ->default(false),
                        
                        Forms\Components\Select::make('hub_id')
                            ->label('Hub Facility')
                            ->relationship('hub', 'name')
                            ->searchable()
                            ->preload()
                            ->hidden(fn (Forms\Get $get) => $get('is_hub')),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Location')
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
                
                Tables\Columns\TextColumn::make('uid')
                    ->label('UID')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('mfl_code')
                    ->label('MFL Code')
                    ->searchable()
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('facilityType.name')
                    ->label('Type')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\IconColumn::make('is_hub')
                    ->label('Hub')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),
                
                Tables\Columns\TextColumn::make('hub.name')
                    ->label('Hub Facility')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Standalone'),
                
                Tables\Columns\TextColumn::make('training_count')
                    ->label('Trainings')
                    ->badge()
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('facility_type_id')
                    ->label('Facility Type')
                    ->relationship('facilityType', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\TernaryFilter::make('is_hub')
                    ->label('Hub Status')
                    ->trueLabel('Hub Facilities')
                    ->falseLabel('Non-Hub Facilities')
                    ->native(false),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['subcounty_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Facility $record): string => 
                        \App\Filament\Resources\FacilityResource::getUrl('view', ['record' => $record])
                    ),
                
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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['subcounty_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ]);
    }
}