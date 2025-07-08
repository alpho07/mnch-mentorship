<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingResource\Pages;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\SessionInventory;
use App\Models\TrainingParticipant;
use App\Models\Facility;
use App\Models\Department;
use App\Models\User;
use App\Models\Program;
use App\Models\InventoryItem;
use App\Models\Methodology;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TrainingResource extends Resource
{
    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationGroup = 'Training Management';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Training Details')
                ->description('Set up the general details of the training event.')
                ->schema([
                    Forms\Components\TextInput::make('title')->required()->maxLength(255),
                    Forms\Components\Select::make('program_id')
                        ->relationship('program', 'name')
                        ->live()
                        ->required(),

                    Forms\Components\Select::make('facility_id')
                        ->label('Facility')
                        ->relationship('facility', 'name')
                        ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} - {$record->mfl_code}")
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('departments')
                        ->label('Departments Involved')
                        ->multiple()
                        ->preload()
                        ->relationship('departments', 'name')
                        ->required(),

                    Forms\Components\Select::make('organizer_id')
                        ->label('Mentor/Organizer')
                        ->relationship('organizer', 'name')
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('location')->label('Location/Room'),

                    Forms\Components\DatePicker::make('start_date')
                        ->minDate(now())
                        ->required(),

                    Forms\Components\DatePicker::make('end_date')
                        ->required()
                        ->minDate(fn(Get $get) => $get('start_date') ?: now())
                        ->afterStateUpdated(function (Set $set, $state, Get $get) {
                            if ($get('start_date') && $state && $state < $get('start_date')) {
                                $set('end_date', null);
                            }
                        }),

                    Forms\Components\Select::make('approach')
                        ->label('Training Approach')
                        ->options([
                            'onsite' => 'Onsite',
                            'virtual' => 'Virtual',
                            'hybrid' => 'Hybrid',
                        ])
                        ->required(),

                    Forms\Components\Textarea::make('notes')->label('General Notes'),
                ])->columns(2),

            Forms\Components\Repeater::make('sessions')
                ->columnSpanFull()
                ->label('Sessions')
                ->relationship()
                ->createItemButtonLabel('Add Session')
                ->schema([
                    Forms\Components\Select::make('module_id')
                        ->label('Module')
                        ->options(function (Get $get) {
                            $programId = $get('../../program_id');
                            if ($programId) {
                                return \App\Models\Module::where('program_id', $programId)->pluck('name', 'id');
                            }
                            return [];
                        })
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('session_time')
                        ->label('Session Duration (Minutes)')
                        ->numeric()
                        ->required(),

                    Forms\Components\TextInput::make('name')
                        ->label('Session Name')
                        ->required(),

                    Forms\Components\Select::make('methodology_id')
                        ->label('Methodology')
                        ->relationship('methodology', 'name')
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('training_session_materials')
                        ->label('Materials')
                        ->multiple()
                        ->relationship('training_session_materials', 'name')
                        ->preload()
                        ->searchable(),

                    Forms\Components\Repeater::make('objectives')
                        ->label('Objectives')
                        ->relationship()
                        ->orderColumn('objective_order')
                        ->schema([
                            Forms\Components\TextInput::make('objective_text')
                                ->label('Objective')
                                ->required(),
                            Forms\Components\Select::make('type')
                                ->options(['skill' => 'Skill', 'non-skill' => 'Non-Skill'])
                                ->required(),
                            Forms\Components\Hidden::make('objective_order'),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->maxItems(10),
                ])
                ->orderColumn('id')
                ->defaultItems(1),

            Forms\Components\Section::make('Participants')
                ->description('Add or select participants. Each participant can have a cadre and department.')
                ->schema([
                    Forms\Components\Repeater::make('participants')
                        ->relationship()
                        ->createItemButtonLabel('Add Participant')
                        ->schema([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\Select::make('cadre_id')
                                ->label('Cadre')
                                ->relationship('cadre', 'name')
                                ->searchable()
                                ->required(),
                            Forms\Components\Select::make('department_id')
                                ->label('Department')
                                ->relationship('department', 'name')
                                ->searchable(),
                            Forms\Components\TextInput::make('mobile')
                                ->label('Mobile')
                                ->tel()
                                ->required(),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->nullable(),
                            Forms\Components\Toggle::make('is_tot')
                                ->label('Is TOT')
                                ->default(false),
                        ])
                        ->columns(3)
                        ->maxItems(30),
                ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->sortable(),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('approach')
                    ->badge()
                    ->colors([
                        'primary' => 'onsite',
                        'success' => 'virtual',
                        'warning' => 'hybrid',
                    ]),

                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Sessions')
                    ->counts('sessions'),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program')
                    ->relationship('program', 'name'),

                Tables\Filters\SelectFilter::make('facility')
                    ->relationship('facility', 'name'),

                Tables\Filters\SelectFilter::make('approach')
                    ->options([
                        'onsite' => 'Onsite',
                        'virtual' => 'Virtual',
                        'hybrid' => 'Hybrid',
                    ]),

                Tables\Filters\Filter::make('start_date')
                    ->form([
                        Forms\Components\DatePicker::make('start_from'),
                        Forms\Components\DatePicker::make('start_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['start_from'], fn(Builder $q, $date): Builder => $q->whereDate('start_date', '>=', $date))
                            ->when($data['start_until'], fn(Builder $q, $date): Builder => $q->whereDate('start_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('assess')
                    ->label('Assess')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (Training $record): string => static::getUrl('assess', ['record' => $record]))
                    ->visible(fn (Training $record): bool => $record->participants()->exists() && $record->sessions()->whereHas('objectives')->exists()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Add custom relations tabs here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTrainings::route('/'),
            'create' => Pages\CreateTraining::route('/create'),
            'edit'   => Pages\EditTraining::route('/{record}/edit'),
            'view'   => Pages\ViewTraining::route('/{record}'),
            'assess' => Pages\AssessParticipants::route('/{record}/assess'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['program', 'facility', 'organizer', 'departments', 'sessions.objectives', 'sessions.training_session_materials', 'participants']);
    }
}