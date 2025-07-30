<?php

namespace App\Filament\Resources\TrainingResource\RelationManagers;

use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\Grade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';
    protected static ?string $recordTitleAttribute = 'user.full_name';
    protected static ?string $title = 'Training Participants';
    protected static ?string $icon = 'heroicon-o-users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Participant Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Select Existing User')
                            ->relationship('user', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->full_name . ' (' . $record->phone . ')')
                            ->searchable(['first_name', 'last_name', 'phone', 'email'])
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $user = User::find($state);
                                    if ($user) {
                                        $set('facility_id', $user->facility_id);
                                        $set('department_id', $user->department_id);
                                        $set('cadre_id', $user->cadre_id);
                                    }
                                }
                            })
                            ->helperText('Search by name, phone, or email. Leave empty to create new user.'),

                        Forms\Components\Fieldset::make('Or Create New Participant')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('first_name')
                                        ->label('First Name')
                                        ->required(fn (Forms\Get $get) => !$get('user_id'))
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('user_id')),

                                    Forms\Components\TextInput::make('middle_name')
                                        ->label('Middle Name')
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('user_id')),

                                    Forms\Components\TextInput::make('last_name')
                                        ->label('Last Name')
                                        ->required(fn (Forms\Get $get) => !$get('user_id'))
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('user_id')),
                                ]),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('phone')
                                        ->label('Phone Number')
                                        ->tel()
                                        ->required(fn (Forms\Get $get) => !$get('user_id'))
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('user_id'))
                                        ->unique(User::class, 'phone', ignoreRecord: true)
                                        ->helperText('Used to check for existing users'),

                                    Forms\Components\TextInput::make('email')
                                        ->label('Email Address')
                                        ->email()
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('user_id'))
                                        ->unique(User::class, 'email', ignoreRecord: true),
                                ]),
                            ])
                            ->columns(1),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('facility_id')
                                ->label('Facility')
                                ->relationship('facility', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn () => $this->getOwnerRecord()->isFacilityMentorship())
                                ->default(fn () => $this->getOwnerRecord()->isFacilityMentorship() ?
                                    $this->getOwnerRecord()->facility_id : null),

                            Forms\Components\Select::make('department_id')
                                ->label('Department')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Select::make('cadre_id')
                                ->label('Cadre')
                                ->relationship('cadre', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                        ]),
                    ]),

                Forms\Components\Section::make('Training Details')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('attendance_status')
                                ->label('Attendance Status')
                                ->options([
                                    'registered' => 'Registered',
                                    'confirmed' => 'Confirmed',
                                    'attended' => 'Attended',
                                    'partially_attended' => 'Partially Attended',
                                    'absent' => 'Absent',
                                ])
                                ->default('registered')
                                ->required(),

                            Forms\Components\Select::make('completion_status')
                                ->label('Completion Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'failed' => 'Failed',
                                    'withdrawn' => 'Withdrawn',
                                ])
                                ->default('pending')
                                ->required(),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('final_score')
                                ->label('Final Score')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%'),

                            Forms\Components\Select::make('outcome_id')
                                ->label('Final Grade')
                                ->relationship('outcome', 'name')
                                ->searchable()
                                ->preload(),

                            Forms\Components\Toggle::make('certificate_issued')
                                ->label('Certificate Issued')
                                ->default(false),
                        ]),

                        Forms\Components\DatePicker::make('completion_date')
                            ->label('Completion Date'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.full_name')
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Participant Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(fn (TrainingParticipant $record): string => $record->facility?->name ?? 'N/A'),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('cadre.name')
                    ->label('Cadre')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('attendance_status')
                    ->label('Attendance')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'attended' => 'success',
                        'partially_attended' => 'warning',
                        'absent' => 'danger',
                        'confirmed' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('completion_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'failed' => 'danger',
                        'withdrawn' => 'gray',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('final_score')
                    ->label('Score')
                    ->suffix('%')
                    ->badge()
                    ->color(fn (?float $state): string => match(true) {
                        $state === null => 'gray',
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('outcome.name')
                    ->label('Grade')
                    ->badge()
                    ->color(fn (TrainingParticipant $record): string =>
                        $record->outcome?->is_passing_grade ? 'success' : 'danger'
                    ),

                Tables\Columns\IconColumn::make('certificate_issued')
                    ->label('Certificate')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Registered')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attendance_status')
                    ->options([
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'attended' => 'Attended',
                        'partially_attended' => 'Partially Attended',
                        'absent' => 'Absent',
                    ]),

                Tables\Filters\SelectFilter::make('completion_status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'withdrawn' => 'Withdrawn',
                    ]),

                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('cadre_id')
                    ->relationship('cadre', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_certificate')
                    ->query(fn (Builder $query): Builder => $query->where('certificate_issued', true))
                    ->label('Has Certificate'),

                Tables\Filters\Filter::make('passed')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereHas('outcome', fn (Builder $q) =>
                            $q->whereIn('name', ['Pass', 'Passed', 'Competent'])
                        )
                    )
                    ->label('Passed'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Participant')
                    ->icon('heroicon-o-user-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Create new user if not selected
                        if (!$data['user_id'] && $data['first_name']) {
                            // Check if user exists by phone
                            $existingUser = User::where('phone', $data['phone'])->first();

                            if ($existingUser) {
                                $data['user_id'] = $existingUser->id;
                                Notification::make()
                                    ->title('Existing User Found')
                                    ->body("User with phone {$data['phone']} already exists. Using existing record.")
                                    ->info()
                                    ->send();
                            } else {
                                // Create new user
                                $user = User::create([
                                    'first_name' => $data['first_name'],
                                    'middle_name' => $data['middle_name'] ?? '',
                                    'last_name' => $data['last_name'],
                                    'phone' => $data['phone'],
                                    'email' => $data['email'] ?? $this->generateEmail($data['first_name'], $data['last_name'], $data['phone']),
                                    'facility_id' => $data['facility_id'],
                                    'department_id' => $data['department_id'],
                                    'cadre_id' => $data['cadre_id'],
                                    'status' => 'active',
                                    'password' => bcrypt('default123'),
                                ]);

                                $user->assignRole('Mentee');
                                $data['user_id'] = $user->id;
                            }
                        }

                        $data['registration_date'] = now();
                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Participant Added')
                            ->body('The participant has been successfully registered for this training.')
                    ),

                Tables\Actions\Action::make('bulk_assess')
                    ->label('Bulk Assessment')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('participants')
                            ->label('Select Participants')
                            ->multiple()
                            ->options(function () {
                                return $this->getOwnerRecord()->participants()
                                    ->with('user')
                                    ->get()
                                    ->pluck('user.full_name', 'id');
                            })
                            ->required(),

                        Forms\Components\Select::make('attendance_status')
                            ->label('Attendance Status')
                            ->options([
                                'attended' => 'Attended',
                                'partially_attended' => 'Partially Attended',
                                'absent' => 'Absent',
                            ]),

                        Forms\Components\Select::make('completion_status')
                            ->label('Completion Status')
                            ->options([
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'in_progress' => 'In Progress',
                            ]),

                        Forms\Components\Select::make('outcome_id')
                            ->label('Grade')
                            ->relationship('outcome', 'name', fn (Builder $query) =>
                                $query->orderBy('name')
                            ),
                    ])
                    ->action(function (array $data) {
                        $updateData = array_filter([
                            'attendance_status' => $data['attendance_status'] ?? null,
                            'completion_status' => $data['completion_status'] ?? null,
                            'outcome_id' => $data['outcome_id'] ?? null,
                        ]);

                        if ($data['completion_status'] === 'completed') {
                            $updateData['completion_date'] = now();
                        }

                        TrainingParticipant::whereIn('id', $data['participants'])
                            ->update($updateData);

                        Notification::make()
                            ->success()
                            ->title('Bulk Update Successful')
                            ->body(count($data['participants']) . ' participants updated successfully.')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('assess_objectives')
                    ->label('Assess')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (TrainingParticipant $record) => $this->getOwnerRecord()->objectives()->exists())
                    ->form(function (TrainingParticipant $record) {
                        $objectives = $this->getOwnerRecord()->objectives;
                        $existingResults = $record->objectiveResults()->pluck('score', 'objective_id');

                        return $objectives->map(function ($objective) use ($existingResults) {
                            return Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Placeholder::make("objective_{$objective->id}_title")
                                    ->label('Objective')
                                    ->content($objective->title),

                                Forms\Components\TextInput::make("objective_{$objective->id}_score")
                                    ->label('Score (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default($existingResults[$objective->id] ?? null)
                                    ->required()
                                    ->suffix('%'),
                            ]);
                        })->toArray();
                    })
                    ->action(function (TrainingParticipant $record, array $data) {
                        $objectives = $this->getOwnerRecord()->objectives;
                        $totalScore = 0;
                        $objectiveCount = 0;

                        foreach ($objectives as $objective) {
                            $scoreKey = "objective_{$objective->id}_score";
                            if (isset($data[$scoreKey])) {
                                $record->objectiveResults()->updateOrCreate(
                                    ['objective_id' => $objective->id],
                                    [
                                        'score' => $data[$scoreKey],
                                        'assessed_by' => auth()->id(),
                                        'assessment_date' => now(),
                                    ]
                                );
                                $totalScore += $data[$scoreKey];
                                $objectiveCount++;
                            }
                        }

                        // Calculate final score and update participant
                        if ($objectiveCount > 0) {
                            $finalScore = $totalScore / $objectiveCount;
                            $passed = $finalScore >= 70; // Adjust threshold as needed

                            $record->update([
                                'final_score' => round($finalScore, 2),
                                'completion_status' => $passed ? 'completed' : 'failed',
                                'completion_date' => now(),
                                'outcome_id' => Grade::where('name', $passed ? 'Pass' : 'Fail')->first()?->id,
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Assessment Completed')
                            ->body('Participant objectives have been assessed successfully.')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('issue_certificate')
                    ->label('Issue Certificate')
                    ->icon('heroicon-o-trophy')
                    ->color('success')
                    ->visible(fn (TrainingParticipant $record) =>
                        $record->completion_status === 'completed' && !$record->certificate_issued
                    )
                    ->requiresConfirmation()
                    ->action(function (TrainingParticipant $record) {
                        $record->update(['certificate_issued' => true]);

                        Notification::make()
                            ->success()
                            ->title('Certificate Issued')
                            ->body('Certificate has been issued to ' . $record->user->full_name)
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_attended')
                        ->label('Mark as Attended')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each->update(['attendance_status' => 'attended']);

                            Notification::make()
                                ->success()
                                ->title('Attendance Updated')
                                ->body($records->count() . ' participants marked as attended.')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('issue_certificates')
                        ->label('Issue Certificates')
                        ->icon('heroicon-o-trophy')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $eligible = $records->filter(fn ($record) =>
                                $record->completion_status === 'completed' && !$record->certificate_issued
                            );

                            $eligible->each->update(['certificate_issued' => true]);

                            Notification::make()
                                ->success()
                                ->title('Certificates Issued')
                                ->body($eligible->count() . ' certificates issued successfully.')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No participants registered')
            ->emptyStateDescription('Start by adding participants to this training program.')
            ->emptyStateIcon('heroicon-o-users')
            ->poll('30s');
    }

    private function generateEmail(string $firstName, string $lastName, string $phone): string
    {
        $emailName = Str::slug(Str::lower($firstName . '.' . $lastName)) . '.' . substr($phone, -4);
        return $emailName . '@mentee.system';
    }
}
