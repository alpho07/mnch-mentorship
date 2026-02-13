<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipCoMentor;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;

class ManageClassMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-mentees';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;
    public ?string $enrollmentLink = null;

    public function mount(Training $training, MentorshipClass $class): void {
        $this->training = $training;
        $this->class = $class->load('training');

        // Load existing enrollment link if available
        if ($this->class->enrollment_token && $this->class->enrollment_link_active) {
            $this->enrollmentLink = route('mentee.enroll', ['token' => $this->class->enrollment_token]);
        }
    }

    public function getTitle(): string {
        return "Manage Mentees - {$this->class->name}";
    }

    public function getSubheading(): ?string {
        $enrolledCount = $this->class->participants()->count();
        return "{$this->training->facility->name} • {$enrolledCount} mentees enrolled";
    }

    protected function getHeaderActions(): array {
        $hasEnrolledMentees = $this->class->participants()->count() > 0;

        return [
                    Actions\Action::make('add_mentees')
                    ->label('Add Mentees')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Section::make('Select Mentees')
                        ->description('Search and select mentees. Already enrolled mentees are pre-selected.')
                        ->schema([
                            Forms\Components\TextInput::make('search')
                            ->label('Search')
                            ->placeholder('Search by name, phone, or email...')
                            ->live(debounce: 300)
                            ->prefixIcon('heroicon-o-magnifying-glass')
                            ->afterStateUpdated(fn() => null),
                            Forms\Components\CheckboxList::make('selected_users')
                            ->label('Available Users from Facility')
                            ->options(function (Forms\Get $get) {
                                $search = $get('search');
                                $facilityId = $this->training->facility_id;

                                $query = User::where('status', 'active')
                                        ->with(['department', 'cadre']);

                                if ($search) {
                                    $query->where(function ($q) use ($search) {
                                        $q->where('first_name', 'like', "%{$search}%")
                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                ->orWhere('phone', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%");
                                    });
                                }

                                return $query
                                                ->orderBy('first_name')
                                                ->get()
                                                ->mapWithKeys(fn($user) => [
                                                    $user->id => $user->full_name .
                                                    ' - ' . $user->phone .
                                                    ($user->department ? ' (' . $user->department->name . ')' : ''),
                                ]);
                            })
                            ->default(function () {
                                return ClassParticipant::where('mentorship_class_id', $this->class->id)
                                                ->pluck('user_id')
                                                ->toArray();
                            })
                            ->bulkToggleable()
                            ->columns(1)
                            ->gridDirection('row')
                            ->helperText('Already enrolled mentees are checked. Uncheck to remove, check to add new mentees.')
                            ->columnSpanFull(),
                        ]),
                    ])
                    ->action(function (array $data) {
                        $selectedUsers = $data['selected_users'] ?? [];
                        $currentEnrolledIds = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                ->pluck('user_id')
                                ->toArray();

                        $usersToAdd = array_diff($selectedUsers, $currentEnrolledIds);
                        $usersToRemove = array_diff($currentEnrolledIds, $selectedUsers);

                        DB::transaction(function () use ($usersToAdd, $usersToRemove) {
                            if (!empty($usersToRemove)) {
                                ClassParticipant::where('mentorship_class_id', $this->class->id)
                                        ->whereIn('user_id', $usersToRemove)
                                        ->delete();
                            }

                            foreach ($usersToAdd as $userId) {
                                $participant = ClassParticipant::create([
                                    'mentorship_class_id' => $this->class->id,
                                    'user_id' => $userId,
                                    'status' => 'enrolled',
                                    'enrolled_at' => now(),
                                ]);

                                $this->createModuleProgressForParticipant($participant);
                            }
                        });

                        $messages = [];
                        if (count($usersToAdd) > 0)
                            $messages[] = count($usersToAdd) . ' mentee(s) added';
                        if (count($usersToRemove) > 0)
                            $messages[] = count($usersToRemove) . ' mentee(s) removed';

                        Notification::make()
                                ->success()
                                ->title('Mentees Updated')
                                ->body(implode(' • ', $messages))
                                ->send();
                    }),
                    Actions\Action::make('generate_enrollment_link')
                    ->label('Generate Enrollment Link')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible($hasEnrolledMentees && !$this->class->enrollment_link_active)
                    ->action(function () {
                        // Check if all enrolled mentees have email addresses
                        $menteesWithoutEmail = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                ->whereHas('user', function ($query) {
                                    $query->whereNull('email')->orWhere('email', '');
                                })
                                ->with('user')
                                ->get();

                        if ($menteesWithoutEmail->isNotEmpty()) {
                            $names = $menteesWithoutEmail->map(fn($p) => $p->user->full_name)->implode(', ');

                            Notification::make()
                                    ->danger()
                                    ->title('Missing Email Addresses')
                                    ->body("The following mentees do not have email addresses: {$names}. Please update their emails before generating the enrollment link.")
                                    ->persistent()
                                    ->send();

                            return;
                        }

                        $this->generateEnrollmentLink();
                    }),
                    Actions\Action::make('invite_co_mentor')
                    ->label('Invite Co-Mentor')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('user_id')
                        ->label('Select Mentor')
                        ->options(function () {
                            $existingCoMentorIds = $this->training->coMentors()->pluck('user_id')->toArray();
                            $existingCoMentorIds[] = $this->training->mentor_id;

                            return User::whereNotIn('id', $existingCoMentorIds)
                                            ->where('status', 'active')
                                            ->orderBy('first_name')
                                            ->get()
                                            ->mapWithKeys(fn($user) => [
                                                $user->id => $user->full_name .
                                                ' (' . ($user->facility?->name ?? 'No Facility') . ') - ' .
                                                ($user->cadre?->name ?? 'No Cadre'),
                            ]);
                        })
                        ->required()
                        ->searchable()
                        ->helperText('Select a user to invite as co-mentor'),
                        Forms\Components\Textarea::make('invitation_message')
                        ->label('Invitation Message (Optional)')
                        ->rows(3)
                        ->placeholder('Add a personal message to the invitation'),
                    ])
                    ->action(function (array $data) {
                        MentorshipCoMentor::create([
                            'training_id' => $this->training->id,
                            'user_id' => $data['user_id'],
                            'invited_by' => auth()->id(),
                            'status' => 'pending',
                            'invitation_token' => Str::random(32),
                        ]);

                        $user = User::find($data['user_id']);

                        Notification::make()
                                ->success()
                                ->title('Co-Mentor Invited')
                                ->body("{$user->full_name} has been invited as a co-mentor.")
                                ->send();
                    }),
                    Actions\Action::make('back')
                    ->label('Back to Classes')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('classes', [
                                'record' => $this->training->id,
                            ])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassParticipant::query()
                                ->where('mentorship_class_id', $this->class->id)
                                ->with(['user.department', 'user.cadre'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('user.full_name')
                            ->label('Name')
                            ->searchable(['first_name', 'last_name'])
                            ->sortable(),
                            Tables\Columns\TextColumn::make('user.phone')
                            ->label('Phone')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('user.email')
                            ->label('Email')
                            ->searchable()
                            ->toggleable()
                            ->default('—')
                            ->color(fn($record) => empty($record->user->email) ? 'danger' : null)
                            ->formatStateUsing(fn($state, $record) => $record->user->email ?: 'No email'),
                            Tables\Columns\TextColumn::make('user.department.name')
                            ->label('Department')
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'warning' => 'enrolled',
                                'success' => 'active',
                                'success' => 'completed',
                                'danger' => 'dropped',
                            ]),
                            Tables\Columns\TextColumn::make('enrolled_at')
                            ->label('Enrolled')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'enrolled' => 'Enrolled',
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'dropped' => 'Dropped',
                            ]),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('update_email')
                            ->label('Update Email')
                            ->icon('heroicon-o-envelope')
                            ->color('warning')
                            ->visible(fn(ClassParticipant $record) => empty($record->user->email))
                            ->form([
                                Forms\Components\TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->unique('users', 'email'),
                            ])
                            ->action(function (ClassParticipant $record, array $data) {
                                $record->user->update(['email' => $data['email']]);

                                Notification::make()
                                        ->success()
                                        ->title('Email Updated')
                                        ->body("Email for {$record->user->full_name} has been updated.")
                                        ->send();
                            }),
                            Tables\Actions\Action::make('view_progress')
                            ->label('Progress')
                            ->icon('heroicon-o-chart-bar')
                            ->url(fn(ClassParticipant $record) => MentorshipTrainingResource::getUrl('mentee-progress', [
                                        'record' => $this->training->id,
                                        'participant' => $record->id,
                                    ])),
                            Tables\Actions\DeleteAction::make()
                            ->label('Remove'),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->label('Remove Selected'),
                            ]),
        ]);
    }

    private function generateEnrollmentLink(): void {
        if (!$this->class->enrollment_token) {
            $this->class->update([
                'enrollment_token' => Str::random(32),
            ]);
        }

        $this->class->update([
            'enrollment_link_active' => true,
        ]);

        $this->enrollmentLink = route('mentee.enroll', ['token' => $this->class->enrollment_token]);

        Notification::make()
                ->success()
                ->title('Enrollment Link Generated')
                ->body('The enrollment link is now displayed on the page.')
                ->send();
    }

    public function deactivateEnrollmentLink(): void {
        $this->class->update([
            'enrollment_link_active' => false,
        ]);

        $this->enrollmentLink = null;

        Notification::make()
                ->warning()
                ->title('Enrollment Link Deactivated')
                ->body('Mentees can no longer use this link to enroll.')
                ->send();
    }

    private function createModuleProgressForParticipant(ClassParticipant $participant): void {
        $completedModuleIds = $this->getUserCompletedModules($participant->user_id);

        foreach ($this->class->classModules as $classModule) {
            $isExempted = in_array($classModule->program_module_id, $completedModuleIds);

            MenteeModuleProgress::create([
                'class_participant_id' => $participant->id,
                'class_module_id' => $classModule->id,
                'status' => $isExempted ? 'exempted' : 'not_started',
                'completed_in_previous_class' => $isExempted,
                'exempted_at' => $isExempted ? now() : null,
            ]);
        }
    }

    private function getUserCompletedModules(int $userId): array {
        return DB::table('class_participants')
                        ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                        ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                        ->where('class_participants.user_id', $userId)
                        ->where('mentee_module_progress.status', 'completed')
                        ->pluck('class_modules.program_module_id')
                        ->unique()
                        ->toArray();
    }
}
