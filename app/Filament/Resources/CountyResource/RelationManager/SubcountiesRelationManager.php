<?php
namespace App\Filament\Resources\CountyResource\RelationManagers;

use App\Models\Subcounty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubcountiesRelationManager extends RelationManager
{
    protected static string $relationship = 'subcounties';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subcounty Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Subcounty Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('uid')
                            ->label('Unique Identifier')
                            ->maxLength(50)
                            ->unique(Subcounty::class, 'uid', ignoreRecord: true),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Subcounty Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('uid')
                    ->label('UID')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('facility_count')
                    ->label('Facilities')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('hub_facilities_count')
                    ->label('Hub Facilities')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('has_facilities')
                    ->label('Has Facilities')
                    ->query(fn ($query) => $query->withFacilities()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['county_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Subcounty $record): string => route('filament.admin.resources.subcounties.view', $record)),
                
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('manage_facilities')
                    ->label('Facilities')
                    ->icon('heroicon-o-building-office-2')
                    ->color('info')
                    ->url(fn (Subcounty $record): string => route('filament.admin.resources.subcounties.view', $record) . '#facilities'),
                
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
}