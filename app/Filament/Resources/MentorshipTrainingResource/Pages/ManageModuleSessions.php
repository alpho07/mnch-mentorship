<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;

class ManageModuleSessions extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-sessions';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load('programModule');
    }

    public function getTitle(): string {
        return "Sessions - {$this->module->programModule->name}";
    }

    public function getSubheading(): ?string {
        return "{$this->class->name} • {$this->module->sessions()->count()} sessions";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('manage_mentees')
                    ->label('Manage Mentees')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->url(fn() => MentorshipTrainingResource::getUrl('module-mentees', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                                'module' => $this->module->id,
                            ])),
                    Actions\Action::make('view_summary')
                    ->label('View Summary')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('info')
                    ->visible(fn() => $this->module->sessions()->count() > 0)
                    ->url(fn() => MentorshipTrainingResource::getUrl('module-summary', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                                'module' => $this->module->id,
                            ])),
                    Actions\Action::make('add_sessions_from_templates')
                    ->label('Add from Templates')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Section::make('Select Sessions to Add')
                        ->description('Choose which sessions from the module template you want to add to this class')
                        ->schema([
                            Forms\Components\CheckboxList::make('session_ids')
                            ->label('Available Sessions')
                            ->options(function () {
                                // Get template sessions for this module's program module
                                $moduleSessions = \App\Models\ModuleSession::where('program_module_id', $this->module->program_module_id)
                                        ->active()
                                        ->ordered()
                                        ->with(['methodology', 'materials'])
                                        ->get();

                                // Get already added session IDs
                                $addedSessionIds = $this->module->sessions()
                                        ->whereNotNull('module_session_id')
                                        ->pluck('module_session_id')
                                        ->toArray();

                                return $moduleSessions->mapWithKeys(function ($session) use ($addedSessionIds) {
                                            $label = $session->name . ' (' . $session->duration . ')';

                                            if (in_array($session->id, $addedSessionIds)) {
                                                $label .= ' - Already Added ✓';
                                            }

                                            if ($session->methodology) {
                                                $label .= ' | ' . $session->methodology->name;
                                            }

                                            if ($session->materials_count > 0) {
                                                $label .= ' | ' . $session->materials_count . ' materials';
                                            }

                                            return [$session->id => $label];
                                        });
                            })
                            ->descriptions(function () {
                                $moduleSessions = \App\Models\ModuleSession::where('program_module_id', $this->module->program_module_id)
                                        ->active()
                                        ->ordered()
                                        ->get();

                                return $moduleSessions->mapWithKeys(function ($session) {
                                            return [$session->id => $session->description ?? ''];
                                        });
                            })
                            ->columns(1)
                            ->required()
                            ->helperText('Select one or more sessions to add to this class'),
                        ]),
                        Forms\Components\Section::make('Schedule Settings')
                        ->description('These will be applied to all selected sessions (you can edit individual sessions later)')
                        ->schema([
                            Forms\Components\Select::make('facilitator_id')
                            ->label('Default Facilitator')
                            ->options(function () {
                                $facilitators = collect([$this->training->mentor]);

                                if (method_exists($this->training, 'coMentors')) {
                                    $coMentors = $this->training->coMentors()
                                            ->where('status', 'accepted')
                                            ->with('user')
                                            ->get()
                                            ->pluck('user');

                                    $facilitators = $facilitators->concat($coMentors);
                                }

                                return $facilitators->filter()->mapWithKeys(fn($user) => [
                                            $user->id => $user->full_name,
                                ]);
                            })
                            ->default(auth()->id())
                            ->required(),
                            Forms\Components\TextInput::make('location')
                            ->label('Default Location')
                            ->placeholder('e.g., Training Room 1')
                            ->helperText('Will be applied to all sessions'),
                        ]),
                    ])
                    ->action(fn(array $data) => $this->addSessionsFromTemplates($data)),
                    Actions\Action::make('create_custom_session')
                    ->label('Create Custom')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('title')
                        ->label('Session Title')
                        ->required()
                        ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('scheduled_date')
                            ->label('Date')
                            ->required()
                            ->native(false),
                            Forms\Components\TimePicker::make('scheduled_time')
                            ->label('Time')
                            ->required()
                            ->seconds(false),
                            Forms\Components\TextInput::make('duration_minutes')
                            ->label('Duration')
                            ->numeric()
                            ->required()
                            ->default(120)
                            ->suffix('minutes'),
                        ]),
                        Forms\Components\TextInput::make('location')
                        ->label('Location')
                        ->maxLength(255),
                        Forms\Components\Select::make('facilitator_id')
                        ->label('Facilitator')
                        ->options(function () {
                            $facilitators = collect([$this->training->mentor]);

                            if (method_exists($this->training, 'coMentors')) {
                                $coMentors = $this->training->coMentors()
                                        ->where('status', 'accepted')
                                        ->with('user')
                                        ->get()
                                        ->pluck('user');

                                $facilitators = $facilitators->concat($coMentors);
                            }

                            return $facilitators->filter()->mapWithKeys(fn($user) => [
                                        $user->id => $user->full_name,
                            ]);
                        })
                        ->default(auth()->id())
                        ->required(),
                    ])
                    ->action(fn(array $data) => $this->createCustomSession($data)),
                    Actions\ActionGroup::make([
                        Actions\Action::make('start_module')
                        ->label('Start Module')
                        ->icon('heroicon-o-play')
                        ->color('primary')
                        ->visible(fn() => $this->module->status === 'not_started')
                        ->requiresConfirmation()
                        ->modalHeading('Start Module')
                        ->modalDescription('Mark this module as in progress?')
                        ->action(function () {
                            $this->module->start();
                            Notification::make()
                                    ->success()
                                    ->title('Module Started')
                                    ->send();
                        }),
                        Actions\Action::make('complete_module')
                        ->label('Complete Module')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn() => $this->module->status === 'in_progress')
                        ->requiresConfirmation()
                        ->modalHeading('Complete Module')
                        ->modalDescription('Mark this module as completed? All sessions should be done.')
                        ->action(function () {
                            $this->module->complete();
                            Notification::make()
                                    ->success()
                                    ->title('Module Completed')
                                    ->send();
                        }),
                        Actions\Action::make('back')
                        ->label('Back to Modules')
                        ->icon('heroicon-o-arrow-left')
                        ->url(function () {
                            return MentorshipTrainingResource::getUrl('classes', ['record' => $this->training]);
                        }),
                    ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassSession::query()
                                ->where('class_module_id', $this->module->id)
                                ->with(['facilitator'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('session_number')
                            ->label('#')
                            ->badge()
                            ->color('primary')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('title')
                            ->label('Session Title')
                            ->searchable()
                            ->weight('bold')
                            ->description(fn(ClassSession $record): string =>
                                    $record->description ?? ''
                            ),
                            Tables\Columns\IconColumn::make('is_from_template')
                            ->label('Template')
                            ->getStateUsing(fn(ClassSession $record) => $record->module_session_id !== null)
                            ->boolean()
                            ->trueIcon('heroicon-o-document-duplicate')
                            ->falseIcon('heroicon-o-pencil-square')
                            ->trueColor('info')
                            ->falseColor('warning')
                            ->tooltip(fn(ClassSession $record) =>
                                    $record->module_session_id ? 'From template' : 'Custom session'
                            ),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'scheduled',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'danger' => 'cancelled',
                            ]),
                            Tables\Columns\TextColumn::make('scheduled_date')
                            ->label('Scheduled')
                            ->date('M j, Y')
                            ->sortable()
                            ->description(fn(ClassSession $record): string =>
                                    $record->scheduled_time ? 'at ' . $record->scheduled_time : 'Time not set'
                            ),
                            Tables\Columns\TextColumn::make('duration_minutes')
                            ->label('Duration')
                            ->suffix(' min')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('facilitator.full_name')
                            ->label('Facilitator')
                            ->searchable(['first_name', 'last_name'])
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('location')
                            ->label('Location')
                            ->searchable()
                            ->toggleable()
                            ->limit(30),
                            Tables\Columns\IconColumn::make('attendance_taken')
                            ->label('Attendance')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'scheduled' => 'Scheduled',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ]),
                            Tables\Filters\SelectFilter::make('facilitator')
                            ->relationship('facilitator', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('start_session')
                                ->label('Start Session')
                                ->icon('heroicon-o-play')
                                ->color('primary')
                                ->visible(fn(ClassSession $record) => $record->status === 'scheduled')
                                ->action(function (ClassSession $record) {
                                    $record->start();
                                    Notification::make()
                                            ->success()
                                            ->title('Session Started')
                                            ->send();
                                }),
                                Tables\Actions\Action::make('take_attendance')
                                ->label('Take Attendance')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->color('warning')
                                ->visible(fn(ClassSession $record) =>
                                        $record->status === 'in_progress' || $record->status === 'completed'
                                )
                                ->url(fn(ClassSession $record): string =>
                                        route('filament.admin.resources.mentorships.session-attendance', [
                                            'training' => $this->training->id,
                                            'class' => $this->class->id,
                                            'session' => $record->id,
                                        ])
                                ),
                                Tables\Actions\Action::make('complete_session')
                                ->label('Complete Session')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->visible(fn(ClassSession $record) => $record->status === 'in_progress')
                                ->requiresConfirmation()
                                ->action(function (ClassSession $record) {
                                    $record->complete();
                                    Notification::make()
                                            ->success()
                                            ->title('Session Completed')
                                            ->send();
                                }),
                                Tables\Actions\EditAction::make()
                                ->form([
                                    Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                    Forms\Components\Textarea::make('description')
                                    ->rows(3),
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\DatePicker::make('scheduled_date')
                                        ->native(false)
                                        ->required(),
                                        Forms\Components\TimePicker::make('scheduled_time')
                                        ->required()
                                        ->seconds(false),
                                        Forms\Components\TextInput::make('duration_minutes')
                                        ->numeric()
                                        ->required()
                                        ->suffix('minutes'),
                                    ]),
                                    Forms\Components\TextInput::make('location')
                                    ->maxLength(255),
                                    Forms\Components\Select::make('facilitator_id')
                                    ->label('Facilitator')
                                    ->options(function () {
                                        $facilitators = collect([$this->training->mentor]);
                                        $coMentors = $this->training->coMentors()
                                                ->where('status', 'accepted')
                                                ->with('user')
                                                ->get()
                                                ->pluck('user');

                                        return $facilitators->concat($coMentors)
                                                        ->mapWithKeys(fn($user) => [
                                                            $user->id => $user->full_name,
                                        ]);
                                    })
                                    ->required(),
                                    Forms\Components\Select::make('status')
                                    ->options([
                                        'scheduled' => 'Scheduled',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ]),
                                    Forms\Components\Textarea::make('notes')
                                    ->rows(2),
                                ]),
                                Tables\Actions\DeleteAction::make()
                                ->requiresConfirmation()
                                ->modalDescription('Delete this session? This cannot be undone.'),
                            ]),
                        ])
                        ->defaultSort('session_number', 'asc')
                        ->reorderable('session_number')
                        ->emptyStateHeading('No Sessions Yet')
                        ->emptyStateDescription('Add sessions to this module to start training.')
                        ->emptyStateIcon('heroicon-o-clock')
                        ->emptyStateActions([
                            Tables\Actions\Action::make('add_first_session')
                            ->label('Add First Session')
                            ->icon('heroicon-o-plus')
                            ->button()
                            ->action(function () {
                                $this->mountAction('add_session');
                            }),
        ]);
    }

    private function addSessionsFromTemplates(array $data): void {
        $sessionIds = $data['session_ids'];
        $facilitatorId = $data['facilitator_id'];
        $location = $data['location'] ?? null;

        // Get template sessions
        $templateSessions = \App\Models\ModuleSession::whereIn('id', $sessionIds)
                ->ordered()
                ->get();

        // Get next session number
        $lastSession = ClassSession::where('class_module_id', $this->module->id)
                ->max('session_number');
        $nextNumber = ($lastSession ?? 0) + 1;

        $addedCount = 0;
        foreach ($templateSessions as $template) {
            // Skip if already added
            $exists = ClassSession::where('class_module_id', $this->module->id)
                    ->where('module_session_id', $template->id)
                    ->exists();

            if ($exists) {
                continue;
            }

            ClassSession::create([
                'class_module_id' => $this->module->id,
                'module_session_id' => $template->id,
                'session_number' => $nextNumber++,
                'title' => $template->name,
                'description' => $template->description,
                'duration_minutes' => $template->time_minutes,
                'facilitator_id' => $facilitatorId,
                'location' => $location,
                'status' => 'scheduled',
            ]);

            $addedCount++;
        }

        // If this is the first session and module is not started, start it
        if ($lastSession === null && $this->module->status === 'not_started') {
            $this->module->start();
        }

        Notification::make()
                ->success()
                ->title('Sessions Added')
                ->body("{$addedCount} session(s) added from templates")
                ->send();
    }

    private function createCustomSession(array $data): void {
        // Get next session number
        $lastSession = ClassSession::where('class_module_id', $this->module->id)
                ->max('session_number');

        $sessionNumber = $lastSession ? $lastSession + 1 : 1;

        ClassSession::create([
            'class_module_id' => $this->module->id,
            'module_session_id' => null, // Custom session
            'session_number' => $sessionNumber,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'],
            'duration_minutes' => $data['duration_minutes'],
            'facilitator_id' => $data['facilitator_id'],
            'location' => $data['location'] ?? null,
            'status' => 'scheduled',
        ]);

        // If this is the first session and module is not started, start it
        if ($sessionNumber === 1 && $this->module->status === 'not_started') {
            $this->module->start();
        }

        Notification::make()
                ->success()
                ->title('Custom Session Created')
                ->body("Session #{$sessionNumber} added to {$this->module->programModule->name}")
                ->send();
    }
}
