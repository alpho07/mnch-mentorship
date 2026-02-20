<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ProgramModule;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ManageClassModules extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-modules';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;

    public function mount(int|string $record, MentorshipClass $class): void {
        $this->training = Training::findOrFail($record);
        $this->class = $class->load('training');
    }

    public function getTitle(): string {
        return "Manage Modules — {$this->class->name}";
    }

    public function getSubheading(): ?string {
        $moduleCount = $this->class->classModules()->count();
        $sessionCount = $this->class->session_count;
        $menteeCount = $this->class->participants()->count();
        return "{$this->training->title} • {$moduleCount} modules • {$sessionCount} sessions • {$menteeCount} mentees";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('add_modules')
                    ->label('Add Modules')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Section::make('Select Modules to Add')
                        ->description('Choose modules for this class. Modules assigned to completed classes in this mentorship are excluded.')
                        ->schema([
                            Forms\Components\CheckboxList::make('selected_modules')
                            ->label('Available Modules')
                            ->options(function () {
                                return $this->getAvailableModuleOptions();
                            })
                            ->descriptions(function () {
                                return $this->getAvailableModuleDescriptions();
                            })
                            ->columns(1)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->helperText('Modules already assigned to completed classes in this mentorship are not shown.')
                            ->columnSpanFull(),
                        ]),
                    ])
                    ->action(function (array $data) {
                        $this->addModulesToClass($data['selected_modules'] ?? []);
                    }),
                    Actions\Action::make('manage_mentees')
                    ->label('Manage Mentees')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->url(fn() => MentorshipTrainingResource::getUrl('class-mentees', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                            ])),
                    Actions\Action::make('back')
                    ->label('Back to Classes')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('classes', [
                                'record' => $this->training->id,
                            ])),
        ];
    }

    // ==========================================
    // MODULE AVAILABILITY (WITHIN MENTORSHIP)
    // ==========================================

    /**
     * Get module options for the checkbox list.
     *
     * Rule: Only exclude modules that are assigned to COMPLETED classes
     * within THIS mentorship. Draft/active classes don't lock modules.
     * NO cross-mentorship filtering.
     */
    private function getAvailableModuleOptions(): array {
        $excludeIds = $this->getExcludedModuleIds();
        $programIds = $this->getTrainingProgramIds();

        if (empty($programIds)) {
            return [];
        }

        return ProgramModule::whereIn('program_id', $programIds)
                        ->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds))
                        ->where('is_active', true)
                        ->orderBy('order_sequence')
                        ->pluck('name', 'id')
                        ->toArray();
    }

    private function getAvailableModuleDescriptions(): array {
        $excludeIds = $this->getExcludedModuleIds();
        $programIds = $this->getTrainingProgramIds();

        if (empty($programIds)) {
            return [];
        }

        return ProgramModule::whereIn('program_id', $programIds)
                        ->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds))
                        ->where('is_active', true)
                        ->orderBy('order_sequence')
                        ->get()
                        ->mapWithKeys(fn($module) => [
                            $module->id => ($module->description ?? '') .
                            ($module->duration_weeks ? ' • ' . $module->duration_weeks . ' weeks' : ''),
                                ])
                        ->toArray();
    }

    /**
     * Module IDs to exclude from the "Add Modules" list.
     *
     * 1. Modules already in THIS class
     * 2. Modules assigned to COMPLETED classes in this mentorship
     *
     * Modules in draft/active classes are NOT excluded — only completed locks them.
     */
    private function getExcludedModuleIds(): array {
        // 1. Already in this class
        $inThisClass = $this->class->classModules()
                ->pluck('program_module_id')
                ->toArray();

        // 2. In COMPLETED classes of this mentorship (not this class)
        $inCompletedClasses = DB::table('class_modules')
                ->join('mentorship_classes', 'class_modules.mentorship_class_id', '=', 'mentorship_classes.id')
                ->where('mentorship_classes.training_id', $this->training->id)
                ->where('mentorship_classes.status', 'completed')
                ->where('mentorship_classes.id', '!=', $this->class->id)
                ->pluck('class_modules.program_module_id')
                ->toArray();

        return array_unique(array_merge($inThisClass, $inCompletedClasses));
    }

    private function getTrainingProgramIds(): array {
        $ids = [];

        if ($this->training->program_id) {
            $ids[] = $this->training->program_id;
        }

        $pivotIds = DB::table('training_programs')
                ->where('training_id', $this->training->id)
                ->pluck('program_id')
                ->toArray();

        return array_unique(array_merge($ids, $pivotIds));
    }

    // ==========================================
    // ADD MODULES ACTION
    // ==========================================

    private function addModulesToClass(array $selectedModuleIds): void {
        if (empty($selectedModuleIds)) {
            Notification::make()
                    ->warning()
                    ->title('No Modules Selected')
                    ->body('Please select at least one module to add.')
                    ->send();
            return;
        }

        $excludeIds = $this->getExcludedModuleIds();
        $validModuleIds = array_diff($selectedModuleIds, $excludeIds);

        if (empty($validModuleIds)) {
            Notification::make()
                    ->warning()
                    ->title('No Modules Added')
                    ->body('All selected modules are already in use.')
                    ->send();
            return;
        }

        DB::transaction(function () use ($validModuleIds) {
            $maxSequence = $this->class->classModules()->max('order_sequence') ?? 0;

            foreach ($validModuleIds as $programModuleId) {
                ClassModule::create([
                    'mentorship_class_id' => $this->class->id,
                    'program_module_id' => $programModuleId,
                    'status' => 'not_started',
                    'order_sequence' => ++$maxSequence,
                ]);
            }
        });

        $count = count($validModuleIds);

        // Step 1: Success notification
        Notification::make()
                ->success()
                ->title("{$count} Module(s) Added")
                ->body("Next step: Add sessions to each module, then add mentees to this class.")
                ->persistent()
                ->send();

        // Step 2: Reminder notification about sessions
        Notification::make()
                ->info()
                ->icon('heroicon-o-calendar')
                ->title('Reminder: Add Sessions')
                ->body('Click on each module below and add sessions before enrolling mentees.')
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('go_to_mentees')
                    ->label('Skip to Manage Mentees')
                    ->url(MentorshipTrainingResource::getUrl('class-mentees', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                            ]))
                    ->color('primary'),
                ])
                ->send();
    }

    // ==========================================
    // TABLE
    // ==========================================

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassModule::query()
                                ->where('mentorship_class_id', $this->class->id)
                                ->with(['programModule', 'sessions'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('order_sequence')
                            ->label('#')
                            ->sortable()
                            ->width(50),
                            Tables\Columns\TextColumn::make('programModule.name')
                            ->label('Module Name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->description(fn(ClassModule $record): string => $record->programModule?->description ?? ''),
                            Tables\Columns\TextColumn::make('programModule.duration_weeks')
                            ->label('Duration')
                            ->suffix(' weeks')
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('session_count')
                            ->label('Sessions')
                            ->badge()
                            ->color(fn(ClassModule $record) => $record->sessions->count() > 0 ? 'primary' : 'danger')
                            ->formatStateUsing(function (ClassModule $record) {
                                $total = $record->sessions->count();
                                $completed = $record->sessions->where('status', 'completed')->count();
                                return $total > 0 ? "{$completed}/{$total}" : '0 ⚠️';
                            }),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'not_started',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                            ])
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        default => ucfirst($state),
                                    }),
                            Tables\Columns\TextColumn::make('started_at')
                            ->label('Started')
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder('—')
                            ->toggleable()
                            ->toggledHiddenByDefault(),
                            Tables\Columns\TextColumn::make('completed_at')
                            ->label('Completed')
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder('—')
                            ->toggleable()
                            ->toggledHiddenByDefault(),
                        ])
                        ->defaultSort('order_sequence')
                        ->reorderable('order_sequence')
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'not_started' => 'Not Started',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                            ]),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('manage_sessions')
                            ->label('Sessions')
                            ->icon('heroicon-o-calendar')
                            ->color(fn(ClassModule $record) => $record->sessions()->count() > 0 ? 'primary' : 'warning')
                            ->badge(fn(ClassModule $record) => $record->sessions()->count() ?: null)
                            ->url(fn(ClassModule $record) => MentorshipTrainingResource::getUrl('module-sessions', [
                                        'training' => $this->training->id,
                                        'class' => $this->class->id,
                                        'module' => $record->id,
                                    ])),
                            Tables\Actions\EditAction::make()
                            ->form([
                                Forms\Components\Select::make('status')
                                ->options([
                                    'not_started' => 'Not Started',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                ])
                                ->required(),
                                Forms\Components\DateTimePicker::make('started_at')
                                ->label('Started At'),
                                Forms\Components\DateTimePicker::make('completed_at')
                                ->label('Completed At'),
                                Forms\Components\Textarea::make('notes')
                                ->rows(3),
                            ]),
                            Tables\Actions\DeleteAction::make()
                            ->label('Remove')
                            ->modalHeading('Remove Module from Class')
                            ->modalDescription('Are you sure? This will delete all related sessions and progress records.')
                            ->successNotificationTitle('Module removed from class'),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->label('Remove Selected'),
                            ]),
                        ])
                        ->emptyStateHeading('No Modules Added Yet')
                        ->emptyStateDescription('Click "Add Modules" to select modules from the training program.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\Action::make('add_first_module')
                            ->label('Add Modules')
                            ->icon('heroicon-o-plus-circle')
                            ->action(fn() => $this->mountAction('add_modules')),
        ]);
    }
}
