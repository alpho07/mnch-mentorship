<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
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

class ManageModuleMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-mentees';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load(['programModule', 'mentorshipClass']);
    }

    public function getTitle(): string {
        return "Mentees - {$this->module->programModule->name}";
    }

    public function getSubheading(): ?string {
        $enrolledCount = $this->module->menteeProgress()->count();
        return "{$this->class->name} • {$enrolledCount} mentees enrolled";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('add_mentees')
                    ->label('Add Mentees to Module')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Section::make('Select Mentees')
                        ->description('Search and select mentees to add to this module')
                        ->schema([
                            Forms\Components\TextInput::make('search')
                            ->label('Search')
                            ->placeholder('Search by name, phone, or email...')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn() => null),
                            Forms\Components\CheckboxList::make('user_ids')
                            ->label('Available Users')
                            ->options(function (Forms\Get $get) {
                                $search = $get('search');

                                $query = User::where('status', 'active')
                                        ->whereNotIn('id', function ($query) {
                                            // Exclude users already in this module
                                            $query->select('cp.user_id')
                                                    ->from('class_participants as cp')
                                                    ->join('mentee_module_progress as mmp', 'cp.id', '=', 'mmp.class_participant_id')
                                                    ->where('mmp.class_module_id', $this->module->id);
                                        });

                                if ($search) {
                                    $query->where(function ($q) use ($search) {
                                        $q->where('first_name', 'like', "%{$search}%")
                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                ->orWhere('phone', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%");
                                    });
                                }

                                return $query->limit(100)
                                                ->get()
                                                ->mapWithKeys(fn($user) => [
                                                    $user->id => $user->full_name .
                                                    ' - ' . $user->phone .
                                                    ($user->facility ? ' (' . $user->facility->name . ')' : ''),
                                ]);
                            })
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(1)
                            ->gridDirection('row')
                            ->helperText('Select one or more mentees. Use search above to filter.')
                            ->required()
                            ->columnSpanFull(),
                        ]),
                        Forms\Components\Section::make('Or Add New Mentee')
                        ->description('Create a new user and add them to this module')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('new_first_name')
                                ->label('First Name')
                                ->maxLength(255),
                                Forms\Components\TextInput::make('new_last_name')
                                ->label('Last Name')
                                ->maxLength(255),
                                Forms\Components\TextInput::make('new_phone')
                                ->label('Phone Number')
                                ->tel()
                                ->required(fn(Forms\Get $get) =>
                                        !empty($get('new_first_name')) || !empty($get('new_last_name'))
                                )
                                ->helperText('Required if adding new mentee'),
                                Forms\Components\TextInput::make('new_email')
                                ->label('Email')
                                ->email(),
                                Forms\Components\Select::make('new_facility_id')
                                ->label('Facility')
                                ->options(\App\Models\Facility::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(fn(Forms\Get $get) =>
                                        !empty($get('new_first_name')) || !empty($get('new_last_name'))
                                )
                                ->columnSpanFull(),
                            ]),
                        ]),
                        Forms\Components\Section::make('Previous Completion Check')
                        ->description('Automatically check if mentees completed this module before')
                        ->schema([
                            Forms\Components\Placeholder::make('check_info')
                            ->content(new \Illuminate\Support\HtmlString('
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <p class="mb-2">✓ System will check if selected mentees completed this module in previous classes</p>
                                        <p>✓ If already completed, they will be marked as exempted</p>
                                    </div>
                                '))
                            ->columnSpanFull(),
                        ]),
                    ])
                    ->action(fn(array $data) => $this->addMentees($data)),
                    Actions\Action::make('generate_attendance_link')
                    ->label('Generate Attendance Link')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn() => $this->module->menteeProgress()->count() > 0)
                    ->action(fn() => $this->generateAttendanceLink()),
                    Actions\Action::make('back')
                    ->label('Back to Sessions')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('module-sessions', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                                'module' => $this->module->id,
                            ])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                MenteeModuleProgress::query()
                                ->where('class_module_id', $this->module->id)
                                ->with(['classParticipant.user.facility'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('classParticipant.user.full_name')
                            ->label('Mentee')
                            ->searchable(['first_name', 'last_name'])
                            ->weight('bold')
                            ->description(fn($record) =>
                                    $record->classParticipant->user->phone
                            ),
                            Tables\Columns\TextColumn::make('classParticipant.user.facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'not_started',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'info' => 'exempted',
                            ])
                            ->formatStateUsing(fn(string $state): string =>
                                    match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'exempted' => 'Exempted',
                                        default => ucfirst($state),
                                    }
                            ),
                            Tables\Columns\IconColumn::make('completed_in_previous_class')
                            ->label('Previous')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('info')
                            ->falseColor('gray')
                            ->tooltip(fn($record) =>
                                    $record->completed_in_previous_class ? 'Completed in previous class' : 'First time taking this module'
                            ),
                            Tables\Columns\TextColumn::make('attendance_percentage')
                            ->label('Attendance')
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => match (true) {
                                        $state >= 80 => 'success',
                                        $state >= 60 => 'warning',
                                        $state === null => 'gray',
                                        default => 'danger',
                                    })
                            ->default('N/A'),
                            Tables\Columns\TextColumn::make('assessment_score')
                            ->label('Assessment')
                            ->formatStateUsing(fn($state) => $state ? number_format($state, 1) . '%' : 'N/A')
                            ->badge()
                            ->color(fn($state) => $state >= 70 ? 'success' : ($state ? 'danger' : 'gray')),
                            Tables\Columns\TextColumn::make('completed_at')
                            ->label('Completed')
                            ->dateTime('M j, Y')
                            ->placeholder('Not completed'),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('mark_completed')
                                ->label('Mark Completed')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->visible(fn($record) => $record->status !== 'completed' && $record->status !== 'exempted')
                                ->requiresConfirmation()
                                ->action(function ($record) {
                                    $record->markCompleted(100, null, 'passed');
                                    Notification::make()
                                            ->success()
                                            ->title('Module Completed')
                                            ->send();
                                }),
                                Tables\Actions\Action::make('remove')
                                ->label('Remove from Module')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->action(function ($record) {
                                    $participantName = $record->classParticipant->user->full_name;
                                    $record->delete();

                                    Notification::make()
                                            ->success()
                                            ->title('Mentee Removed')
                                            ->body("{$participantName} removed from this module")
                                            ->send();
                                }),
                            ]),
                        ])
                        ->emptyStateHeading('No Mentees Enrolled')
                        ->emptyStateDescription('Add mentees to this module to start training.')
                        ->emptyStateIcon('heroicon-o-users');
    }

    private function addMentees(array $data): void {
        $userIds = $data['user_ids'] ?? [];
        $added = 0;
        $exempted = 0;

        // Create new user if provided
        if (!empty($data['new_first_name']) && !empty($data['new_phone'])) {
            $existingUser = User::where('phone', $data['new_phone'])->first();

            if ($existingUser) {
                $userIds[] = $existingUser->id;
            } else {
                $newUser = User::create([
                    'first_name' => $data['new_first_name'],
                    'last_name' => $data['new_last_name'],
                    'phone' => $data['new_phone'],
                    'email' => $data['new_email'] ?? null,
                    'facility_id' => $data['new_facility_id'],
                    'password' => bcrypt('temporary123'),
                    'status' => 'active',
                ]);

                $userIds[] = $newUser->id;

                Notification::make()
                        ->success()
                        ->title('New User Created')
                        ->body("User {$newUser->full_name} created")
                        ->send();
            }
        }

        // Add each user to this specific module
        foreach ($userIds as $userId) {
            // Get or create class participant
            $participant = ClassParticipant::firstOrCreate(
                    [
                        'mentorship_class_id' => $this->class->id,
                        'user_id' => $userId,
                    ],
                    [
                        'status' => 'enrolled',
                        'enrolled_at' => now(),
                    ]
            );

            // Check if already in this module
            $exists = MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->where('class_module_id', $this->module->id)
                    ->exists();

            if ($exists) {
                continue;
            }

            // Check if user completed this module before
            $hasCompletedBefore = $this->hasUserCompletedModule($userId, $this->module->program_module_id);

            // Create module progress record
            MenteeModuleProgress::create([
                'class_participant_id' => $participant->id,
                'class_module_id' => $this->module->id,
                'status' => $hasCompletedBefore ? 'exempted' : 'not_started',
                'completed_in_previous_class' => $hasCompletedBefore,
                'exempted_at' => $hasCompletedBefore ? now() : null,
            ]);

            if ($hasCompletedBefore) {
                $exempted++;
            }

            $added++;
        }

        $message = "{$added} mentee(s) added to this module";
        if ($exempted > 0) {
            $message .= " ({$exempted} auto-exempted - completed before)";
        }

        Notification::make()
                ->success()
                ->title('Mentees Added')
                ->body($message)
                ->send();
    }

    private function hasUserCompletedModule(int $userId, int $programModuleId): bool {
        return \Illuminate\Support\Facades\DB::table('class_participants')
                        ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                        ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                        ->where('class_participants.user_id', $userId)
                        ->where('class_modules.program_module_id', $programModuleId)
                        ->where('mentee_module_progress.status', 'completed')
                        ->exists();
    }

    private function generateAttendanceLink(): void {
        // Generate unique token for this module
        if (!$this->module->attendance_token) {
            $this->module->update([
                'attendance_token' => Str::random(32),
                'attendance_link_active' => true,
            ]);
        }

        $link = route('module.attend', ['token' => $this->module->attendance_token]);

        Notification::make()
                ->success()
                ->title('Attendance Link Generated')
                ->body('Share this link with mentees to mark attendance')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('copy')
                    ->button()
                    ->label('Copy Link')
                    ->extraAttributes([
                        'x-on:click' => "
                            navigator.clipboard.writeText('{$link}');
                            \$tooltip('Copied!', { timeout: 2000 });
                        ",
                    ]),
                ])
                ->persistent()
                ->send();
    }
}
