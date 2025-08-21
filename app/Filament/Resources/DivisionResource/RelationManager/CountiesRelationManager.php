<?php

namespace App\Filament\Resources\DivisionResource\RelationManagers;

use App\Models\County;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CountiesRelationManager extends RelationManager
{
    protected static string $relationship = 'counties';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('County Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('County Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('uid')
                            ->label('Unique Identifier')
                            ->maxLength(50)
                            ->unique(County::class, 'uid', ignoreRecord: true),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('County Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('uid')
                    ->label('UID')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('subcounty_count')
                    ->label('Subcounties')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('facility_count')
                    ->label('Facilities')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('has_subcounties')
                    ->label('Has Subcounties')
                    ->query(fn ($query) => $query->has('subcounties')),
                
                Tables\Filters\Filter::make('has_facilities')
                    ->label('Has Facilities')
                    ->query(fn ($query) => $query->has('facilities')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['division_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (County $record): string => route('filament.admin.resources.counties.view', $record)),
                
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('manage_subcounties')
                    ->label('Subcounties')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(fn (County $record): string => route('filament.admin.resources.counties.view', $record) . '#subcounties'),
                
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
                        $data['division_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ]);
    }
}