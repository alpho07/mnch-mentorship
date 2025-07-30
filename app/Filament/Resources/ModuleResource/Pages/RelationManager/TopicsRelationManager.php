<?php

namespace App\Filament\Resources\ModuleResource\RelationManagers;

use App\Models\Topic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class TopicsRelationManager extends RelationManager
{
    protected static string $relationship = 'topics';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Topic Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Topic Name')
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
                    ->label('Topic Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('training_links_count')
                    ->label('Training Links')
                    ->counts('trainingLinks')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('has_training_links')
                    ->label('Has Training Links')
                    ->query(fn ($query) => $query->has('trainingLinks')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['module_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalContent(fn (Topic $record): View => view(
                        'filament.modals.topic-details',
                        ['topic' => $record]
                    ))
                    ->modalHeading(fn (Topic $record): string => "Topic: {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                
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
                        $data['module_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->emptyStateHeading('No topics yet')
            ->emptyStateDescription('Start by creating your first topic for this module.')
            ->emptyStateIcon('heroicon-o-list-bullet');
    }
}