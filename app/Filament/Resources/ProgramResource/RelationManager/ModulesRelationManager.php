<?php

namespace App\Filament\Resources\ProgramResource\RelationManagers;

use App\Models\Module;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModulesRelationManager extends RelationManager
{
    protected static string $relationship = 'modules';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Module Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Module Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Module Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('session_count')
                    ->label('Sessions')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('training_count')
                    ->label('Trainings')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('has_sessions')
                    ->label('Has Sessions')
                    ->query(fn ($query) => $query->withSessions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['program_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Module $record): string => route('filament.admin.resources.modules.view', $record)),
                
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('manage_topics')
                    ->label('Topics')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->url(fn (Module $record): string => route('filament.admin.resources.modules.view', $record) . '#topics'),
                
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
                        $data['program_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ]);
    }
}