<?php

namespace App\Filament\Resources\TrainingResource\RelationManagers;

use App\Models\TrainingSession;
use App\Models\Module;
use App\Models\Methodology;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Training Sessions';
    protected static ?string $icon = 'heroicon-o-calendar-days';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Details')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Session Title')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Introduction to Clinical Guidelines'),

                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'planned' => 'Planned',
                                    'ongoing' => 'Ongoing',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->default('planned')
                                ->required(),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('module_id')
                                ->label('Module')
                                ->options(function () {
                                    $training = $this->getOwnerRecord();
                                    if (!$training->modules()->exists()) {
                                        return Module::pluck('name', 'id');
                                    }
                                    return $training->modules()->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('methodology_id')
                                ->label('Teaching Methodology')
                                ->relationship('methodology', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description'),
                                ]),

                            Forms\Components\Select::make('facilitator_id')
                                ->label('Facilitator')
                                ->options(function () {
                                    return User::whereHas('roles', function($q) {
                                        $q->whereIn('name', ['Trainer', 'Mentor', 'Senior Staff']);
                                    })->get()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->required()
                                ->default(fn () => $this->getOwnerRecord()->mentor_id),
                        ]),
                    ]),

                Forms\Components\Section::make('Schedule & Location')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('session_date')
                                ->label('Session Date')
                                ->required()
                                ->default(fn () => $this->getOwnerRecord()->start_date)
                                ->after($this->getOwnerRecord()->start_date)
                                ->before($this->getOwnerRecord()->end_date),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('Start Time')
                                ->required()
                                ->default('09:00'),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('End Time')
                                ->required()
                                ->default('17:00')
                                ->after('start_time'),
                        ]),

                        Forms\Components\TextInput::make('location')
                            ->label('Session Location')
                            ->placeholder('Conference room, lab, field site, etc.')
                            ->default(fn () => $this->getOwnerRecord()->location),

                        Forms\Components\TextInput::make('attendance_count')
                            ->label('Expected Attendance')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn () => $this->getOwnerRecord()->participants()->count()),
                    ]),

                Forms\Components\Section::make('Content & Materials')
                    ->schema([
                        Forms\Components\Repeater::make('materials_used')
                            ->label('Materials & Equipment Used')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('material')
                                        ->label('Material/Equipment')
                                        ->required()
                                        ->placeholder('e.g., Projector, Handouts, Medical Equipment'),

                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->default(1),

                                    Forms\Components\Select::make('condition')
                                        ->label('Condition')
                                        ->options([
                                            'excellent' => 'Excellent',
                                            'good' => 'Good',
                                            'fair' => 'Fair',
                                            'poor' => 'Poor',
                                            'unavailable' => 'Unavailable',
                                        ])
                                        ->default('good'),
                                ]),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add Material')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('session_notes')
                            ->label('Session Notes')
                            ->rows(4)
                            ->placeholder('Key topics covered, participant engagement, challenges, etc.')
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Session Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (TrainingSession $record): string =>
                        $record->module?->name ?? 'No module assigned'
                    ),

                Tables\Columns\TextColumn::make('session_date')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->badge()
                    ->color(fn (TrainingSession $record): string => match(true) {
                        $record->session_date->isFuture() => 'warning',
                        $record->session_date->isToday() => 'success',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('time_range')
                    ->label('Time')
                    ->getStateUsing(fn (TrainingSession $record): string =>
                        $record->start_time?->format('H:i') . ' - ' . $record->end_time?->format('H:i')
                    )
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('facilitator.full_name')
                    ->label('Facilitator')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn (TrainingSession $record): string =>
                        $record->facilitator?->full_name ?? 'No facilitator assigned'
                    ),

                Tables\Columns\TextColumn::make('methodology.name')
                    ->label('Methodology')
                    ->badge()
                    ->color('primary')
                    ->limit(15),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'ongoing' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('duration_hours')
                    ->label('Duration')
                    ->getStateUsing(fn (TrainingSession $record): string =>
                        $record->duration_hours . 'h'
                    )
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('attendance_count')
                    ->label('Attendance')
                    ->badge()
                    ->color('info')
                    ->suffix(' participants'),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->limit(20)
                    ->tooltip(fn (TrainingSession $record): string => $record->location ?? 'No location specified')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('session_date')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'planned' => 'Planned',
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('module_id')
                    ->relationship('module', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('methodology_id')
                    ->relationship('methodology', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('today')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDate('session_date', today())
                    )
                    ->label('Today\'s Sessions'),

                Tables\Filters\Filter::make('this_week')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereBetween('session_date', [
                            now()->startOfWeek(),
                            now()->endOfWeek()
                        ])
                    )
                    ->label('This Week'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Session')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert time strings to datetime objects if needed
                        if (isset($data['start_time']) && is_string($data['start_time'])) {
                            $data['start_time'] = $data['session_date'] . ' ' . $data['start_time'];
                        }
                        if (isset($data['end_time']) && is_string($data['end_time'])) {
                            $data['end_time'] = $data['session_date'] . ' ' . $data['end_time'];
                        }

                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Session Added')
                            ->body('Training session has been scheduled successfully.')
                    ),

                Tables\Actions\Action::make('bulk_create')
                    ->label('Bulk Create Sessions')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('modules')
                            ->label('Modules')
                            ->options(function () {
                                $training = $this->getOwnerRecord();
                                return $training->modules()->pluck('name', 'id');
                            })
                            ->multiple()
                            ->required()
                            ->helperText('Select modules to create sessions for'),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->default(fn () => $this->getOwnerRecord()->start_date),

                        Forms\Components\Select::make('session_pattern')
                            ->label('Session Pattern')
                            ->options([
                                'daily' => 'Daily',
                                'weekdays' => 'Weekdays Only',
                                'weekly' => 'Weekly',
                            ])
                            ->default('daily')
                            ->required(),

                        Forms\Components\TimePicker::make('default_start_time')
                            ->label('Default Start Time')
                            ->default('09:00')
                            ->required(),

                        Forms\Components\TimePicker::make('default_end_time')
                            ->label('Default End Time')
                            ->default('17:00')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $training = $this->getOwnerRecord();
                        $modules = Module::whereIn('id', $data['modules'])->get();
                        $currentDate = \Carbon\Carbon::parse($data['start_date']);
                        $endDate = $training->end_date;

                        $sessionsCreated = 0;

                        foreach ($modules as $index => $module) {
                            if ($currentDate > $endDate) break;

                            TrainingSession::create([
                                'training_id' => $training->id,
                                'module_id' => $module->id,
                                'title' => $module->name . ' - Session',
                                'session_date' => $currentDate->toDateString(),
                                'start_time' => $currentDate->copy()->setTimeFromTimeString($data['default_start_time']),
                                'end_time' => $currentDate->copy()->setTimeFromTimeString($data['default_end_time']),
                                'facilitator_id' => $training->mentor_id,
                                'location' => $training->location,
                                'status' => 'planned',
                            ]);

                            $sessionsCreated++;

                            // Advance date based on pattern
                            switch ($data['session_pattern']) {
                                case 'daily':
                                    $currentDate->addDay();
                                    break;
                                case 'weekdays':
                                    $currentDate->addWeekday();
                                    break;
                                case 'weekly':
                                    $currentDate->addWeek();
                                    break;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Sessions Created')
                            ->body("Created {$sessionsCreated} training sessions successfully.")
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TrainingSession $record) => $record->status !== 'completed')
                    ->form([
                        Forms\Components\TextInput::make('actual_attendance')
                            ->label('Actual Attendance')
                            ->numeric()
                            ->required(),

                        Forms\Components\Textarea::make('completion_notes')
                            ->label('Session Summary')
                            ->rows(3)
                            ->placeholder('Key outcomes, participant feedback, next steps...')
                            ->required(),
                    ])
                    ->action(function (TrainingSession $record, array $data) {
                        $record->update([
                            'status' => 'completed',
                            'attendance_count' => $data['actual_attendance'],
                            'session_notes' => ($record->session_notes ?? '') . "\n\nCompletion Summary: " . $data['completion_notes'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Session Completed')
                            ->body('Session has been marked as completed.')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (TrainingSession $record) {
                        $newSession = $record->replicate();
                        $newSession->title = $record->title . ' (Copy)';
                        $newSession->status = 'planned';
                        $newSession->session_date = $record->session_date->addDay();
                        $newSession->save();

                        Notification::make()
                            ->success()
                            ->title('Session Duplicated')
                            ->body('A copy of the session has been created.')
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('default_attendance')
                                ->label('Default Attendance Count')
                                ->numeric()
                                ->helperText('Will be applied to sessions without attendance data'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updated = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'completed') {
                                    $record->update([
                                        'status' => 'completed',
                                        'attendance_count' => $record->attendance_count ?? $data['default_attendance'] ?? 0,
                                    ]);
                                    $updated++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Sessions Updated')
                                ->body("{$updated} sessions marked as completed.")
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reschedule')
                        ->label('Bulk Reschedule')
                        ->icon('heroicon-o-calendar')
                        ->color('warning')
                        ->form([
                            Forms\Components\DatePicker::make('new_start_date')
                                ->label('New Start Date')
                                ->required(),

                            Forms\Components\Select::make('shift_pattern')
                                ->label('Shift Pattern')
                                ->options([
                                    'consecutive' => 'Consecutive Days',
                                    'weekdays' => 'Weekdays Only',
                                    'keep_intervals' => 'Keep Original Intervals',
                                ])
                                ->default('consecutive')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $newDate = \Carbon\Carbon::parse($data['new_start_date']);
                            $sortedRecords = $records->sortBy('session_date');

                            foreach ($sortedRecords as $index => $record) {
                                $record->update(['session_date' => $newDate->copy()]);

                                // Advance date based on pattern
                                switch ($data['shift_pattern']) {
                                    case 'consecutive':
                                        $newDate->addDay();
                                        break;
                                    case 'weekdays':
                                        $newDate->addWeekday();
                                        break;
                                    case 'keep_intervals':
                                        if ($index === 0) break;
                                        $previousRecord = $sortedRecords->values()[$index - 1];
                                        $originalInterval = $record->session_date->diffInDays($previousRecord->session_date);
                                        $newDate->addDays($originalInterval);
                                        break;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Sessions Rescheduled')
                                ->body(count($records) . ' sessions have been rescheduled.')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No sessions scheduled')
            ->emptyStateDescription('Create training sessions to organize the content delivery.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->poll('60s');
    }
}
