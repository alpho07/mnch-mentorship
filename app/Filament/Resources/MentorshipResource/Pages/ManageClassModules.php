<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Services\ModuleUsageService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ManageClassModules extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-modules';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void {
        $this->training = $training;
        $this->class = $class->load(['training', 'classModules.programModule']);
    }

    public function getTitle(): string {
        return "Class > Modules — {$this->class->name}";
    }

    public function getSubheading(): ?string {
        $service = app(ModuleUsageService::class);
        $assigned = $this->class->classModules()->count();
        $available = $service->getAvailableModules($this->training, $this->class)->count();
        $total = $assigned + $available;

        return "{$assigned} / {$total} program modules assigned · {$available} still available";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array {
        $service = app(ModuleUsageService::class);
        $availableModules = $this->getModuleOptions($service);

        return [
                    Actions\Action::make('back_to_class')
                    ->label('Back to Classes')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('classes', ['record' => $this->training->id])),
                    Actions\Action::make('add_modules')
                    ->label('Add Modules')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn() => count($availableModules) > 0 && $this->class->status !== 'completed')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Add Modules to Class')
                    ->modalDescription('Each module can only be taught once per mentorship.')
                    ->form([
                        Forms\Components\CheckboxList::make('module_ids')
                        ->label('Available Program Modules')
                        ->options($availableModules)
                        ->searchable()
                        ->bulkToggleable()
                        ->required()
                        ->helperText('Modules already used in other classes are excluded.'),
                        Forms\Components\Toggle::make('auto_create_sessions')
                        ->label('Auto-populate sessions from program template')
                        ->default(true),
                        Forms\Components\Textarea::make('notes')
                        ->label('Notes (optional)')
                        ->rows(2),
                    ])
                    ->action(function (array $data) use ($service) {
                        $created = $service->assignModulesToClass($this->training, $this->class, $data['module_ids']);
                        $createdSessions = 0;

                        if (($data['auto_create_sessions'] ?? true) && $created > 0) {
                            $this->class->load('classModules');
                            foreach ($this->class->classModules as $cm) {
                                if (method_exists($cm, 'autoCreateSessions')) {
                                    $createdSessions += (int) $cm->autoCreateSessions();
                                }
                            }
                        }

                        $sessionText = $createdSessions > 0 ? " with {$createdSessions} sessions auto-created" : '';

                        Notification::make()->success()->title("{$created} module(s) added{$sessionText}")->send();
                    }),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassModule::query()
                                ->with(['programModule', 'sessions', 'attendanceRecords', 'menteeProgress'])
                                ->where('mentorship_class_id', $this->class->id)
                                ->orderBy('order_sequence')
                        )
                        ->reorderable('order_sequence')
                        ->defaultSort('order_sequence')
                        ->columns([
                            Tables\Columns\TextColumn::make('order_sequence')
                            ->label('#')
                            ->width(40)
                            ->sortable(),
                            // ── Status indicator (icon + badge) ───────────────────────
                            Tables\Columns\TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn(string $state) => match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        default => ucfirst($state),
                                    })
                            ->color(fn(string $state) => match ($state) {
                                        'not_started' => 'gray',
                                        'in_progress' => 'warning',
                                        'completed' => 'success',
                                        default => 'gray',
                                    })
                            ->icon(fn(string $state) => match ($state) {
                                        'not_started' => 'heroicon-m-clock',
                                        'in_progress' => 'heroicon-m-play',
                                        'completed' => 'heroicon-m-check-circle',
                                        default => null,
                                    }),
                            Tables\Columns\TextColumn::make('programModule.name')
                            ->label('Module')
                            ->searchable()
                            ->weight('medium')
                            ->description(fn(ClassModule $record) => $record->programModule?->description ? \Illuminate\Support\Str::limit($record->programModule->description, 80) : null),
                            Tables\Columns\TextColumn::make('sessions_count')
                            ->label('Sessions')
                            ->getStateUsing(fn(ClassModule $record) => $record->sessions->count())
                            ->alignCenter()
                            ->color('gray'),
                            Tables\Columns\TextColumn::make('attendance_summary')
                            ->label('Attendance')
                            ->getStateUsing(function (ClassModule $record) {
                                $confirmed = $record->attendanceRecords->count();
                                $total = ClassParticipant::where('mentorship_class_id', $record->mentorship_class_id)
                                        ->whereIn('status', ['enrolled', 'active'])
                                        ->count();

                                if ($total === 0)
                                    return '—';

                                $pct = round(($confirmed / $total) * 100);
                                return "{$confirmed}/{$total} ({$pct}%)";
                            })
                            ->color(fn(ClassModule $record) => match (true) {
                                        $record->status === 'not_started' => 'gray',
                                        $record->attendanceRecords->count() === 0 => 'danger',
                                        default => 'success',
                                    })
                            ->icon(fn(ClassModule $record) => $record->attendanceRecords->count() > 0 ? 'heroicon-m-check-circle' : null),
                            Tables\Columns\TextColumn::make('started_at')
                            ->label('Started')
                            ->date('d M Y')
                            ->placeholder('—'),
                            Tables\Columns\TextColumn::make('completed_at')
                            ->label('Completed')
                            ->date('d M Y')
                            ->placeholder('—'),
                        ])
                        ->actions([
                            // ── Lifecycle ─────────────────────────────────────────────

                            Tables\Actions\Action::make('start_module')
                            ->label('Start')
                            ->icon('heroicon-o-play')
                            ->color('success')
                            ->button()
                            ->visible(fn(ClassModule $record) => $record->status === 'not_started')
                            ->requiresConfirmation()
                            ->modalHeading(fn(ClassModule $record) => 'Start "' . ($record->programModule->name ?? 'Module') . '"?')
                            ->modalDescription('This will open the attendance link for mentees.')
                            ->modalSubmitActionLabel('Yes, Start')
                            ->action(function (ClassModule $record) {
                                try {
                                    // If class is still draft, activate it first then refresh
                                    $freshClass = MentorshipClass::find($this->class->id);
                                    if ($freshClass->status === 'draft') {
                                        $freshClass->update(['status' => 'active']);
                                    }

                                    $record->start();

                                    Notification::make()
                                            ->success()
                                            ->title('Module Started')
                                            ->body('Attendance link is now active for mentees.')
                                            ->send();
                                } catch (\LogicException $e) {
                                    Notification::make()
                                            ->danger()
                                            ->title('Cannot Start Module')
                                            ->body($e->getMessage())
                                            ->send();
                                }
                            }),
                            Tables\Actions\Action::make('complete_module')
                            ->label('Complete')
                            ->icon('heroicon-o-check-badge')
                            ->color('primary')
                            ->button()
                            ->visible(fn(ClassModule $record) => $record->status === 'in_progress')
                            ->requiresConfirmation()
                            ->modalHeading('Complete Module')
                            ->modalDescription(fn(ClassModule $record) => implode("\n", [
                                        "Completing this module will:",
                                        "• Close attendance confirmation for mentees",
                                        "• Calculate final attendance rates",
                                        "• Update all mentee progress records",
                                        "",
                                        "Attendance: {$record->attendanceRate()}% ({$record->confirmedAttendanceCount()} of {$record->enrolledMenteeCount()} confirmed)",
                                    ]))
                            ->action(function (ClassModule $record) {
                                try {
                                    $record->complete();

                                    Notification::make()
                                            ->success()
                                            ->title('Module Completed')
                                            ->body("Final attendance: {$record->attendanceRate()}%")
                                            ->send();
                                } catch (\LogicException $e) {
                                    Notification::make()
                                            ->danger()
                                            ->title('Cannot Complete Module')
                                            ->body($e->getMessage())
                                            ->send();
                                }
                            }),
                            // ── Navigation (inline icon-only links) ───────────────────
                            Tables\Actions\Action::make('manage_sessions')
                            ->label('Sessions')
                            ->icon('heroicon-o-calendar-days')
                            ->color('gray')
                            ->iconButton()
                            ->tooltip('Sessions')
                            ->url(fn(ClassModule $record) => MentorshipTrainingResource::getUrl('module-sessions', [
                                        'training' => $this->training->id,
                                        'class' => $this->class->id,
                                        'module' => $record->id,
                                    ])),
                            Tables\Actions\Action::make('manage_mentees')
                            ->label('Mentees')
                            ->icon('heroicon-o-users')
                            ->color('gray')
                            ->iconButton()
                            ->tooltip('Mentees & Attendance')
                            ->url(fn(ClassModule $record) => MentorshipTrainingResource::getUrl('module-mentees', [
                                        'training' => $this->training->id,
                                        'class' => $this->class->id,
                                        'module' => $record->id,
                                    ])),
                            Tables\Actions\Action::make('module_summary')
                            ->label('Summary')
                            ->icon('heroicon-o-chart-bar')
                            ->color('gray')
                            ->iconButton()
                            ->tooltip('Summary & Analytics')
                            ->url(fn(ClassModule $record) => MentorshipTrainingResource::getUrl('module-summary', [
                                        'training' => $this->training->id,
                                        'class' => $this->class->id,
                                        'module' => $record->id,
                                    ])),
                            Tables\Actions\EditAction::make()
                            ->label('Edit')
                            ->icon('heroicon-o-pencil')
                            ->iconButton()
                            ->tooltip('Edit Module Settings')
                            ->form([
                                Forms\Components\Select::make('min_attendance_percentage')
                                ->label('Minimum Attendance Required (%)')
                                ->options([50 => '50%', 60 => '60%', 75 => '75%', 80 => '80%', 90 => '90%', 100 => '100%'])
                                ->default(75),
                                Forms\Components\Toggle::make('requires_assessment')
                                ->label('Requires Assessment'),
                                Forms\Components\TextInput::make('order_sequence')
                                ->label('Display Order')
                                ->numeric()
                                ->minValue(0),
                                Forms\Components\Textarea::make('notes')
                                ->label('Module Notes')
                                ->rows(3),
                            ])
                            ->visible(fn(ClassModule $record) => $record->status !== 'completed'),
                            Tables\Actions\Action::make('remove_module')
                            ->label('Remove')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->iconButton()
                            ->tooltip('Remove from Class')
                            ->visible(fn(ClassModule $record) => $record->status === 'not_started')
                            ->requiresConfirmation()
                            ->modalHeading('Remove Module from Class')
                            ->modalDescription('This removes the module from this class and makes it available for other classes. Sessions will also be deleted.')
                            ->action(function (ClassModule $record) {
                                $service = app(ModuleUsageService::class);

                                if (!$record->canBeRemoved()) {
                                    Notification::make()
                                            ->danger()
                                            ->title('Cannot Remove Module')
                                            ->body('Module has sessions or progress records.')
                                            ->send();
                                    return;
                                }

                                $service->removeModuleFromClass($this->training, $this->class, $record);

                                Notification::make()->success()->title('Module Removed')->send();
                            }),
                        ])
                        ->emptyStateHeading('No Modules Added Yet')
                        ->emptyStateDescription('Add modules from the program curriculum to get started.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->striped()
                        ->paginated(false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getModuleOptions(ModuleUsageService $service): array {
        return $service->getAvailableModules($this->training, $this->class)
                        ->mapWithKeys(fn($module) => [
                            $module->id => $module->name . ($module->duration_weeks ? " ({$module->duration_weeks}w)" : ''),
                                ])
                        ->toArray();
    }
}
