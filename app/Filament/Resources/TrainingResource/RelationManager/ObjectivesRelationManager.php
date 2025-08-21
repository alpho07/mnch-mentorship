<?php

namespace App\Filament\Resources\TrainingResource\RelationManagers;

use App\Models\TrainingObjective;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ObjectivesRelationManager extends RelationManager
{
    protected static string $relationship = 'objectives';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Learning Objectives';
    protected static ?string $icon = 'heroicon-o-academic-cap';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Objective Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Objective Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('What will participants be able to do?')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Detailed Description')
                            ->rows(3)
                            ->placeholder('Describe the learning objective in detail...')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('type')
                                ->label('Objective Type')
                                ->options([
                                    'knowledge' => 'Knowledge (Cognitive)',
                                    'skill' => 'Skill (Psychomotor)',
                                    'attitude' => 'Attitude (Affective)',
                                ])
                                ->required()
                                ->default('knowledge')
                                ->helperText('Type of learning outcome'),

                            Forms\Components\TextInput::make('weight')
                                ->label('Weight (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(function () {
                                    // Auto-calculate equal weight
                                    $existingCount = $this->getOwnerRecord()->objectives()->count();
                                    return $existingCount > 0 ? round(100 / ($existingCount + 1), 2) : 100;
                                })
                                ->helperText('Percentage weight in final score')
                                ->suffix('%'),

                            Forms\Components\TextInput::make('pass_criteria')
                                ->label('Pass Criteria (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(70)
                                ->helperText('Minimum score to pass this objective')
                                ->suffix('%'),
                        ]),

                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->minValue(1)
                            ->default(function () {
                                return $this->getOwnerRecord()->objectives()->max('order') + 1;
                            })
                            ->helperText('Order in which this objective appears'),
                    ]),

                Forms\Components\Section::make('Assessment Criteria')
                    ->schema([
                        Forms\Components\Repeater::make('assessment_criteria')
                            ->label('Assessment Criteria')
                            ->schema([
                                Forms\Components\TextInput::make('criterion')
                                    ->label('Criterion')
                                    ->required()
                                    ->placeholder('How will this be assessed?'),

                                Forms\Components\Select::make('method')
                                    ->label('Assessment Method')
                                    ->options([
                                        'observation' => 'Direct Observation',
                                        'demonstration' => 'Practical Demonstration',
                                        'verbal' => 'Verbal Assessment',
                                        'written' => 'Written Test',
                                        'portfolio' => 'Portfolio Review',
                                    ])
                                    ->required(),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add Assessment Criterion')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('title')
                    ->label('Learning Objective')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->description(
                        fn(TrainingObjective $record): string =>
                        Str::limit($record->description, 100)
                    ),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'knowledge' => 'info',
                        'skill' => 'success',
                        'attitude' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'knowledge' => 'Knowledge',
                        'skill' => 'Skill',
                        'attitude' => 'Attitude',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pass_criteria')
                    ->label('Pass Score')
                    ->suffix('%')
                    ->alignCenter()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('pass_rate')
                    ->label('Pass Rate')
                    ->getStateUsing(
                        fn(TrainingObjective $record): string =>
                        number_format($record->pass_rate, 1) . '%'
                    )
                    ->badge()
                    ->color(fn(TrainingObjective $record): string => match (true) {
                        $record->pass_rate >= 90 => 'success',
                        $record->pass_rate >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->description(
                        fn(TrainingObjective $record): string =>
                        $record->participantResults()->count() . ' assessments'
                    ),

                Tables\Columns\TextColumn::make('average_score')
                    ->label('Avg Score')
                    ->getStateUsing(
                        fn(TrainingObjective $record): string =>
                        number_format($record->participantResults()->avg('score') ?? 0, 1) . '%'
                    )
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'knowledge' => 'Knowledge',
                        'skill' => 'Skill',
                        'attitude' => 'Attitude',
                    ]),

                Tables\Filters\Filter::make('low_pass_rate')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('participantResults', function (Builder $subQuery) {
                            $subQuery->selectRaw('objective_id,
                                (COUNT(CASE WHEN score >= pass_criteria THEN 1 END) * 100.0 / COUNT(*)) as pass_rate')
                                ->groupBy('objective_id')
                                ->havingRaw('pass_rate < 70');
                        });
                    })
                    ->label('Low Pass Rate (<70%)'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Objective')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-balance weights if this is not the first objective
                        $existingObjectives = $this->getOwnerRecord()->objectives();
                        $totalExistingWeight = $existingObjectives->sum('weight');

                        if ($totalExistingWeight > 0 && $data['weight']) {
                            $newTotalWeight = $totalExistingWeight + $data['weight'];
                            if ($newTotalWeight > 100) {
                                // Rebalance all weights proportionally
                                $factor = 100 / $newTotalWeight;
                                $existingObjectives->each(function ($objective) use ($factor) {
                                    $objective->update(['weight' => round($objective->weight * $factor, 2)]);
                                });
                                $data['weight'] = round($data['weight'] * $factor, 2);
                            }
                        }

                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Objective Added')
                            ->body('Learning objective has been successfully added to the training.')
                    ),

                Tables\Actions\Action::make('balance_weights')
                    ->label('Balance Weights')
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will distribute the weights equally among all objectives.')
                    ->action(function () {
                        $objectives = $this->getOwnerRecord()->objectives;
                        $count = $objectives->count();

                        if ($count > 0) {
                            $equalWeight = round(100 / $count, 2);
                            $objectives->each(function ($objective) use ($equalWeight) {
                                $objective->update(['weight' => $equalWeight]);
                            });

                            Notification::make()
                                ->success()
                                ->title('Weights Balanced')
                                ->body("All {$count} objectives now have equal weight of {$equalWeight}%")
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_assessments')
                    ->label('View Assessments')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(
                        fn(TrainingObjective $record): string =>
                        route('filament.admin.resources.training-objectives.assessments', $record)
                    )
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('secondary')
                    ->action(function (TrainingObjective $record) {
                        $newObjective = $record->replicate();
                        $newObjective->title = $record->title . ' (Copy)';
                        $newObjective->order = $this->getOwnerRecord()->objectives()->max('order') + 1;
                        $newObjective->save();

                        Notification::make()
                            ->success()
                            ->title('Objective Duplicated')
                            ->body('A copy of the objective has been created.')
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('set_type')
                        ->label('Set Type')
                        ->icon('heroicon-o-tag')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('type')
                                ->label('Objective Type')
                                ->options([
                                    'knowledge' => 'Knowledge',
                                    'skill' => 'Skill',
                                    'attitude' => 'Attitude',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['type' => $data['type']]);

                            Notification::make()
                                ->success()
                                ->title('Type Updated')
                                ->body($records->count() . ' objectives updated successfully.')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('set_pass_criteria')
                        ->label('Set Pass Criteria')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('pass_criteria')
                                ->label('Pass Criteria (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required()
                                ->suffix('%'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['pass_criteria' => $data['pass_criteria']]);

                            Notification::make()
                                ->success()
                                ->title('Pass Criteria Updated')
                                ->body($records->count() . ' objectives updated successfully.')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No learning objectives defined')
            ->emptyStateDescription('Add learning objectives that participants need to achieve in this training.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->poll('60s');
    }
}
