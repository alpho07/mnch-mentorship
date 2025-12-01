<?php

namespace App\Filament\Resources\AssessmentSectionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Section Questions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Question Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('question_code')
                                            ->label('Question Code')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->placeholder('e.g., Q1.1')
                                            ->helperText('Unique identifier for this question'),

                                        Forms\Components\TextInput::make('order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->required(),

                                        Forms\Components\Textarea::make('question_text')
                                            ->label('Question Text')
                                            ->required()
                                            ->rows(3)
                                            ->columnSpan(2)
                                            ->placeholder('Enter the question text here'),

                                        Forms\Components\Select::make('response_type')
                                            ->label('Response Type')
                                            ->required()
                                            ->options([
                                                'yes_no' => 'Yes/No',
                                                'yes_no_partial' => 'Yes/Partially/No',
                                                'yes_no_na' => 'Yes/No/N/A',
                                                'number' => 'Number',
                                                'text' => 'Short Text',
                                                'textarea' => 'Long Text',
                                                'date' => 'Date',
                                                'matrix' => 'Matrix (Multi-location)',
                                                'checkbox' => 'Checkbox (Multiple Choice)',
                                                'radio' => 'Radio (Single Choice)',
                                                'select' => 'Dropdown Select',
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                // Auto-set scoring for common types
                                                if ($state === 'yes_no') {
                                                    $set('scoring_map', ['Yes' => 1, 'No' => 0]);
                                                } elseif ($state === 'yes_no_partial') {
                                                    $set('scoring_map', ['Yes' => 1, 'Partially' => 0.5, 'No' => 0]);
                                                } elseif ($state === 'yes_no_na') {
                                                    $set('scoring_map', ['Yes' => 1, 'No' => 0, 'N/A' => null]);
                                                }
                                            }),

                                        Forms\Components\Toggle::make('is_required')
                                            ->label('Required Question')
                                            ->default(false),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true),

                                        Forms\Components\Textarea::make('help_text')
                                            ->label('Help Text')
                                            ->rows(2)
                                            ->columnSpan(2)
                                            ->placeholder('Optional help text or instructions'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Options & Matrix')
                            ->schema([
                                Forms\Components\Grid::make(1)
                                    ->schema([
                                        Forms\Components\TagsInput::make('matrix_locations')
                                            ->label('Matrix Locations')
                                            ->placeholder('Add location (press Enter)')
                                            ->helperText('For matrix questions: Skills Lab, NBU, Maternity, Theatre, Paediatric')
                                            ->visible(fn (Forms\Get $get) => $get('response_type') === 'matrix')
                                            ->required(fn (Forms\Get $get) => $get('response_type') === 'matrix'),

                                        Forms\Components\TagsInput::make('options')
                                            ->label('Response Options')
                                            ->placeholder('Add option (press Enter)')
                                            ->helperText('Options for select/radio/checkbox questions')
                                            ->visible(fn (Forms\Get $get) => in_array($get('response_type'), ['checkbox', 'radio', 'select']))
                                            ->required(fn (Forms\Get $get) => in_array($get('response_type'), ['checkbox', 'radio', 'select'])),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Explanation & Scoring')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('requires_explanation')
                                            ->label('Requires Explanation')
                                            ->default(false)
                                            ->reactive()
                                            ->helperText('Show explanation field for this question'),

                                        Forms\Components\TextInput::make('explanation_label')
                                            ->label('Explanation Label')
                                            ->placeholder('Please explain')
                                            ->visible(fn (Forms\Get $get) => $get('requires_explanation')),

                                        Forms\Components\Toggle::make('include_in_scoring')
                                            ->label('Include in Scoring')
                                            ->default(true)
                                            ->reactive()
                                            ->columnSpan(2),

                                        Forms\Components\KeyValue::make('scoring_map')
                                            ->label('Scoring Map')
                                            ->keyLabel('Response Value')
                                            ->valueLabel('Score')
                                            ->addActionLabel('Add Score')
                                            ->helperText('Map response values to scores (e.g., "Yes" = 1, "No" = 0)')
                                            ->visible(fn (Forms\Get $get) => $get('include_in_scoring'))
                                            ->columnSpan(2),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Skip Logic')
                            ->schema([
                                Forms\Components\KeyValue::make('skip_logic')
                                    ->label('Skip Logic Rules')
                                    ->keyLabel('Condition')
                                    ->valueLabel('Action')
                                    ->addActionLabel('Add Rule')
                                    ->helperText('Define conditional logic (advanced)'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('question_code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('question_text')
                    ->label('Question')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 60 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('response_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'yes_no', 'yes_no_partial', 'yes_no_na' => 'success',
                        'matrix' => 'warning',
                        'number', 'text', 'textarea' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),

                Tables\Columns\IconColumn::make('include_in_scoring')
                    ->label('Scored')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('response_type')
                    ->label('Response Type')
                    ->options([
                        'yes_no' => 'Yes/No',
                        'yes_no_partial' => 'Yes/Partially/No',
                        'yes_no_na' => 'Yes/No/N/A',
                        'number' => 'Number',
                        'text' => 'Short Text',
                        'textarea' => 'Long Text',
                        'date' => 'Date',
                        'matrix' => 'Matrix',
                        'checkbox' => 'Checkbox',
                        'radio' => 'Radio',
                        'select' => 'Select',
                    ]),

                Tables\Filters\TernaryFilter::make('is_required')
                    ->label('Required Questions'),

                Tables\Filters\TernaryFilter::make('include_in_scoring')
                    ->label('Scored Questions'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-increment order if not provided
                        if (!isset($data['order']) || $data['order'] === 0) {
                            $maxOrder = $this->getOwnerRecord()->questions()->max('order') ?? 0;
                            $data['order'] = $maxOrder + 10;
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function ($record) {
                        $newQuestion = $record->replicate();
                        $newQuestion->question_code = $record->question_code . '_COPY';
                        $newQuestion->order = $record->order + 1;
                        $newQuestion->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}