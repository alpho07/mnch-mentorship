<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Cadre;
use App\Models\ClassParticipant;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\MenteeModuleProgress;
use App\Models\Training;
use App\Models\User;
use App\Services\EnrollmentService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManageClassMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-mentees';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void {
        $this->training = $training;
        $this->class = $class->load(['training']);
    }

    public function getTitle(): string {
        return "Mentees — {$this->class->name}";
    }

    public function getSubheading(): ?string {
        $enrolled = ClassParticipant::where('mentorship_class_id', $this->class->id)
                ->whereIn('status', ['enrolled', 'active'])
                ->count();
        return "{$enrolled} enrolled · {$this->training->title}";
    }

    // Pass variables the blade view needs
    public function getViewData(): array {
        $token = $this->class->enrollment_token;
        $enrollmentLink = $token ? route('mentee.enroll', ['token' => $token]) : null;

        return [
            'enrollmentLink' => $enrollmentLink,
            'enrollmentLinkActive' => (bool) $token,
            'training' => $this->training,
            'class' => $this->class,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table (enrolled mentees roster)
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassParticipant::query()
                                ->where('mentorship_class_id', $this->class->id)
                                ->with(['user.cadre', 'user.facility', 'user.department'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('user.name')
                            ->label('Name')
                            ->searchable(['users.first_name', 'users.last_name', 'users.name'])
                            ->sortable()
                            ->description(fn($record) => $record->user?->email ?? '—'),
                            Tables\Columns\TextColumn::make('user.phone')
                            ->label('Phone')
                            ->searchable()
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('user.cadre.name')
                            ->label('Cadre')
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('user.department.name')
                            ->label('Department')
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('user.facility.name')
                            ->label('Facility')
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'warning' => 'enrolled',
                                'success' => 'active',
                                'gray' => 'completed',
                                'danger' => 'withdrawn',
                            ]),
                            Tables\Columns\TextColumn::make('enrolled_at')
                            ->label('Enrolled')
                            ->date('d M Y')
                            ->sortable()
                            ->toggleable(),
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
                            Tables\Actions\Action::make('remove')
                            ->label('Remove')
                            ->icon('heroicon-o-x-mark')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Remove Mentee')
                            ->modalDescription('This will remove the mentee and all their module progress for this class.')
                            ->action(function ($record) {
                                app(EnrollmentService::class)->removeFromClass($record);
                                Notification::make()->success()->title('Mentee Removed')->send();
                            }),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkAction::make('bulk_remove')
                            ->label('Remove Selected')
                            ->icon('heroicon-o-x-mark')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(function ($records) {
                                $service = app(EnrollmentService::class);
                                foreach ($records as $record) {
                                    $service->removeFromClass($record);
                                }
                                Notification::make()->success()->title('Mentees Removed')->send();
                            }),
                        ])
                        ->emptyStateHeading('No Mentees Enrolled')
                        ->emptyStateDescription('Use "Add from List" to enroll existing users, or "Add Mentee" to create and enroll a new one.')
                        ->emptyStateIcon('heroicon-o-users');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('back')
                    ->label('Back to Classes')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('classes', ['record' => $this->training->id])),
            // ─── ACTION A: Checkbox slide-over (UNCHANGED) ────────────────────
            Actions\Action::make('manage_from_list')
                    ->label('Add from List')
                    ->icon('heroicon-o-list-bullet')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Manage Class Mentees')
                    ->modalDescription('Select mentees to enroll. Already-enrolled mentees are pre-checked. Uncheck to remove.')
                    ->form([
                        Forms\Components\TextInput::make('search')
                        ->label('Search')
                        ->placeholder('Search by name, phone, or email...')
                        ->live(debounce: 300)
                        ->prefixIcon('heroicon-o-magnifying-glass'),
                        Forms\Components\CheckboxList::make('selected_users')
                        ->label('Available Users')
                        ->options(function (Forms\Get $get) {
                            $search = $get('search');
                            $query = User::where('status', 'active')
                                    ->with(['cadre', 'facility'])
                                    ->orderBy('first_name');

                            if ($search) {
                                $query->where(function ($q) use ($search) {
                                    $q->where('first_name', 'like', "%{$search}%")
                                            ->orWhere('last_name', 'like', "%{$search}%")
                                            ->orWhere('name', 'like', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                });
                            }

                            $results = $query->get();

                            if ($results->isEmpty()) {
                                // When no results, we return empty — the hint is shown via helperText
                                return [];
                            }

                            return $results->mapWithKeys(fn($u) => [
                                        $u->id => implode(' · ', array_filter([
                                            $u->name ?? trim("{$u->first_name} {$u->last_name}"),
                                            $u->phone,
                                            $u->cadre?->name,
                                            $u->facility?->name,
                                        ])),
                            ]);
                        })
                        ->default(fn() => ClassParticipant::where('mentorship_class_id', $this->class->id)
                                ->pluck('user_id')
                                ->toArray()
                        )
                        ->bulkToggleable()
                        ->columns(1)
                        ->gridDirection('row')
                        ->helperText(function (Forms\Get $get) {
                            $search = $get('search');
                            if (!$search) {
                                return 'Checked users are already enrolled. Uncheck to remove.';
                            }
                            // Check if query would be empty
                            $exists = User::where('status', 'active')
                                            ->where(function ($q) use ($search) {
                                                $q->where('first_name', 'like', "%{$search}%")
                                                        ->orWhere('last_name', 'like', "%{$search}%")
                                                        ->orWhere('name', 'like', "%{$search}%")
                                                        ->orWhere('phone', 'like', "%{$search}%")
                                                        ->orWhere('email', 'like', "%{$search}%");
                                            })->exists();

                            if (!$exists) {
                                return '⚠️ No users found for "' . $search . '". Close this panel and use the "Add Mentee" button to create and enroll a new user.';
                            }
                            return 'Checked users are already enrolled. Uncheck to remove.';
                        })
                        ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        $selected = $data['selected_users'] ?? [];
                        $currentIds = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                        ->pluck('user_id')->toArray();

                        $toAdd = array_diff($selected, $currentIds);
                        $toRemove = array_diff($currentIds, $selected);
                        $service = app(EnrollmentService::class);

                        DB::transaction(function () use ($toAdd, $toRemove, $service) {
                            foreach ($toRemove as $userId) {
                                $p = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                                ->where('user_id', $userId)->first();
                                if ($p)
                                    $service->removeFromClass($p);
                            }
                            foreach ($toAdd as $userId) {
                                $u = User::find($userId);
                                if ($u)
                                    $service->enrollInClass($u, $this->class, 'manual');
                            }
                        });

                        $parts = [];
                        if (count($toAdd))
                            $parts[] = count($toAdd) . ' enrolled';
                        if (count($toRemove))
                            $parts[] = count($toRemove) . ' removed';

                        Notification::make()
                                ->success()
                                ->title('Mentees Updated')
                                ->body(implode(' · ', $parts) ?: 'No changes made.')
                                ->send();
                    }),
            // ─── ACTION B: Add Mentee popout (NEW) ───────────────────────────
            // Email lookup → load existing details OR create new user + enroll
            Actions\Action::make('add_mentee')
                    ->label('Add Mentee')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->modalWidth('xl')
                    ->modalHeading('Add Mentee')
                    ->modalDescription('Enter the email address to look up an existing user, or fill in all fields to create a new mentee account.')
                    ->form([
                        // ── Email field (triggers live lookup) ────────────────
                        Forms\Components\TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->live(debounce: 600)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if (!$state || !filter_var($state, FILTER_VALIDATE_EMAIL)) {
                                $set('_found_user_id', null);
                                $set('_found_label', null);
                                $set('_already_enrolled', false);
                                return;
                            }

                            $user = User::where('email', $state)
                                    ->with(['cadre', 'facility', 'department'])
                                    ->first();

                            if ($user) {
                                $set('_found_user_id', $user->id);
                                $set('_already_enrolled', ClassParticipant::where('mentorship_class_id', $this->class->id)->where('user_id', $user->id)->exists());

                                // Pre-fill form with existing data (read-only in found state)
                                $set('first_name', $user->first_name);
                                $set('middle_name', $user->middle_name);
                                $set('last_name', $user->last_name);
                                $set('phone', $user->phone);
                                $set('cadre_id', $user->cadre_id);
                                $set('department_id', $user->department_id);
                                $set('facility_id', $user->facility_id);
                            } else {
                                $set('_found_user_id', null);
                                $set('_already_enrolled', false);
                                $set('_found_label', null);
                                // Clear prefills so form is editable
                                $set('first_name', null);
                                $set('middle_name', null);
                                $set('last_name', null);
                                $set('phone', null);
                                $set('cadre_id', null);
                                $set('department_id', null);
                                $set('facility_id', null);
                            }
                        })
                        ->placeholder('e.g. jane.wanjiku@moh.go.ke'),
                        // Hidden tracking fields
                        Forms\Components\Hidden::make('_found_user_id'),
                        Forms\Components\Hidden::make('_already_enrolled')->default(false),
                        // ── "User Found" status banner ────────────────────────
                        Forms\Components\Placeholder::make('_status_banner')
                        ->label('')
                        ->content(function (Forms\Get $get) {
                            $email = $get('email');
                            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                return '';

                            if ($get('_found_user_id')) {
                                if ($get('_already_enrolled')) {
                                    return '⚠️  This person is already enrolled in this class.';
                                }
                                return '✅  User found! Fields are pre-filled from their profile. Click "Add Mentee" to enroll them.';
                            }

                            return '🆕  No account found for this email. Fill in the details below — a new user will be created with password 123456.';
                        })
                        ->visible(fn(Forms\Get $get) => !empty($get('email')) && filter_var($get('email'), FILTER_VALIDATE_EMAIL))
                        ->extraAttributes(fn(Forms\Get $get) => [
                            'class' => $get('_found_user_id') ? ($get('_already_enrolled') ? 'text-sm font-medium text-amber-700 dark:text-amber-400' : 'text-sm font-medium text-emerald-700 dark:text-emerald-400') : 'text-sm font-medium text-blue-700 dark:text-blue-400',
                                ]),
                        Forms\Components\Fieldset::make('Personal Details')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('first_name')
                                ->label('First Name')
                                ->required(fn(Forms\Get $get) => !$get('_found_user_id'))
                                ->readOnly(fn(Forms\Get $get) => (bool) $get('_found_user_id'))
                                ->maxLength(100),
                                Forms\Components\TextInput::make('middle_name')
                                ->label('Middle Name')
                                ->readOnly(fn(Forms\Get $get) => (bool) $get('_found_user_id'))
                                ->maxLength(100),
                                Forms\Components\TextInput::make('last_name')
                                ->label('Last Name')
                                ->required(fn(Forms\Get $get) => !$get('_found_user_id'))
                                ->readOnly(fn(Forms\Get $get) => (bool) $get('_found_user_id'))
                                ->maxLength(100),
                            ]),
                            Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->readOnly(fn(Forms\Get $get) => (bool) $get('_found_user_id'))
                            ->placeholder('+254 7XX XXX XXX')
                            ->maxLength(20),
                        ]),
                        Forms\Components\Fieldset::make('Professional Details')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('cadre_id')
                                ->label('Cadre')
                                ->options(Cadre::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->disabled(fn(Forms\Get $get) => (bool) $get('_found_user_id')),
                                Forms\Components\Select::make('department_id')
                                ->label('Department')
                                ->options(Department::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->disabled(fn(Forms\Get $get) => (bool) $get('_found_user_id')),
                            ]),
                            Forms\Components\Select::make('facility_id')
                            ->label('Facility')
                            ->options(fn() => Facility::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn($f) => [$f->id => "{$f->mfl_code} - {$f->name}"]))
                            ->searchable()
                            ->disabled(fn(Forms\Get $get) => (bool) $get('_found_user_id')),
                        ]),
                        // Password notice — only shown for new users
                        Forms\Components\Placeholder::make('_password_notice')
                        ->label('Password')
                        ->content('Default password 123456 will be set. The mentee should change it on first login.')
                        ->visible(fn(Forms\Get $get) =>
                                !$get('_found_user_id') &&
                                !empty($get('email')) &&
                                filter_var($get('email'), FILTER_VALIDATE_EMAIL)
                        ),
                    ])
                    ->modalSubmitActionLabel('Add Mentee')
                    ->action(function (array $data) {
                        $service = app(EnrollmentService::class);

                        // ── Existing user ─────────────────────────────────────
                        if (!empty($data['_found_user_id'])) {
                            if ($data['_already_enrolled']) {
                                Notification::make()
                                        ->warning()
                                        ->title('Already Enrolled')
                                        ->body('This mentee is already enrolled in this class.')
                                        ->send();
                                return;
                            }

                            $user = User::findOrFail((int) $data['_found_user_id']);
                            $service->enrollInClass($user, $this->class, 'manual');

                            Notification::make()
                                    ->success()
                                    ->title('Mentee Enrolled')
                                    ->body("{$user->name} has been enrolled in {$this->class->name}.")
                                    ->send();
                            return;
                        }

                        // ── New user: validate and create ─────────────────────
                        $email = $data['email'];

                        if (User::where('email', $email)->exists()) {
                            Notification::make()
                                    ->danger()
                                    ->title('Email Already Taken')
                                    ->body('A user with this email already exists. Please reload and search again.')
                                    ->send();
                            return;
                        }

                        if (empty(trim($data['first_name'] ?? '')) || empty(trim($data['last_name'] ?? ''))) {
                            Notification::make()
                                    ->danger()
                                    ->title('Name Required')
                                    ->body('First and last name are required to create a new account.')
                                    ->send();
                            return;
                        }

                        $displayName = trim(implode(' ', array_filter([
                            $data['first_name'],
                            $data['middle_name'] ?? null,
                            $data['last_name'],
                        ])));

                        DB::transaction(function () use ($data, $email, $displayName, $service) {
                            $user = User::create([
                                'first_name' => $data['first_name'],
                                'middle_name' => $data['middle_name'] ?? null,
                                'last_name' => $data['last_name'],
                                'name' => $displayName,
                                'email' => $email,
                                'phone' => $data['phone'] ?? null,
                                'cadre_id' => $data['cadre_id'] ?? null,
                                'department_id' => $data['department_id'] ?? null,
                                'facility_id' => $data['facility_id'] ?? null,
                                'password' => Hash::make('123456'),
                                'status' => 'active',
                                'role' => 'mentee',
                            ]);

                            if (method_exists($user, 'assignRole')) {
                                try {
                                    $user->assignRole('mentee');
                                } catch (\Exception) {
                                    
                                }
                            }

                            $service->enrollInClass($user, $this->class, 'manual');
                        });

                        Notification::make()
                                ->success()
                                ->title('Mentee Created & Enrolled')
                                ->body(
                                        "{$displayName} has been registered (email: {$email}) and enrolled in {$this->class->name}. " .
                                        "Default password: 123456"
                                )
                                ->persistent()
                                ->send();
                    }),
            // ─── Enrollment link ──────────────────────────────────────────────
            Actions\Action::make('enrollment_link')
                    ->label('Enrollment Link')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn() => ClassParticipant::where('mentorship_class_id', $this->class->id)->exists())
                    ->action(function () {
                        $missing = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                ->whereHas('user', fn($q) => $q->whereNull('email')->orWhere('email', ''))
                                ->with('user')
                                ->get();

                        if ($missing->isNotEmpty()) {
                            $names = $missing->map(fn($p) => $p->user?->name ?? 'Unknown')->implode(', ');
                            Notification::make()
                                    ->danger()
                                    ->title('Missing Emails')
                                    ->body("These mentees have no email: {$names}. Update emails first.")
                                    ->persistent()
                                    ->send();
                            return;
                        }

                        // Generate token if missing, and always activate the link
                        if (!$this->class->enrollment_token) {
                            $this->class->update([
                                'enrollment_token' => \Illuminate\Support\Str::random(32),
                                'enrollment_link_active' => true,
                            ]);
                        } else {
                            // Token exists but flag may still be 0 — ensure it's active
                            $this->class->update(['enrollment_link_active' => true]);
                        }

                        $this->class->refresh();

                        $link = route('mentee.enroll', ['token' => $this->class->enrollment_token]);

                        Notification::make()
                                ->success()
                                ->title('Enrollment Link Active')
                                ->body("Share this link with mentees: {$link}")
                                ->persistent()
                                ->send();
                    }),
                    Actions\Action::make('start_class')
                    ->label('Start Class')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn() => $this->class->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Start This Class?')
                    ->modalDescription(
                            'This will activate the class and start all modules. ' .
                            'Attendance links will open for each module. ' .
                            'The enrollment link stays open so mentees can still join.'
                    )
                    ->modalSubmitActionLabel('Yes, Start Class')
                    ->action(function () {
                        try {
                            $this->class->start();
                            $this->class = $this->class->fresh();

                            Notification::make()
                                    ->success()
                                    ->title('Class Started')
                                    ->body('All modules are now in progress and attendance links are active.')
                                    ->send();
                        } catch (\LogicException $e) {
                            Notification::make()
                                    ->danger()
                                    ->title('Cannot Start Class')
                                    ->body($e->getMessage())
                                    ->send();
                        }
                    }),
                    Actions\Action::make('end_class')
                    ->label('End Class')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn() => $this->class->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('End This Class?')
                    ->modalDescription(
                            'This will permanently close the class. ' .
                            'All modules will be marked complete, attendance links will stop working, ' .
                            'and the enrollment link will be deactivated. ' .
                            'This action cannot be undone.'
                    )
                    ->modalSubmitActionLabel('Yes, End Class')
                    ->action(function () {
                        try {
                            $this->class->complete();
                            $this->class = $this->class->fresh();

                            Notification::make()
                                    ->success()
                                    ->title('Class Ended')
                                    ->body('All modules completed. Enrollment and attendance links are now inactive.')
                                    ->send();
                        } catch (\LogicException $e) {
                            Notification::make()
                                    ->danger()
                                    ->title('Cannot End Class')
                                    ->body($e->getMessage())
                                    ->send();
                        }
                    }),
        ];
    }
}
