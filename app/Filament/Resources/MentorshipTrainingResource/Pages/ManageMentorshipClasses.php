<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;

class ManageMentorshipClasses extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-mentorship-classes';
    public Training $record;
    public ?MentorshipClass $selectedClass = null;
    public bool $viewingModules = false;

    public function mount(): void {
        // $this->record is automatically bound by Filament from the route
        // Check if we have a 'class' query parameter for viewing modules
        $classId = request()->query('class');

        if ($classId) {
            $this->selectedClass = MentorshipClass::findOrFail($classId);
            $this->viewingModules = true;
        }
    }

    public function getTitle(): string {
        if ($this->viewingModules && $this->selectedClass) {
            return "Modules - {$this->selectedClass->name}";
        }
        return "Classes";
    }

    public function getSubheading(): ?string {
        if ($this->viewingModules && $this->selectedClass) {
            return "{$this->selectedClass->module_count} modules • {$this->selectedClass->session_count} sessions";
        }
        return "Manage mentorship cohorts and modules";
    }

    protected function getHeaderActions(): array {
        if ($this->viewingModules) {
            return $this->getModuleHeaderActions();
        }

        return $this->getClassHeaderActions();
    }

    private function getClassHeaderActions(): array {
        return [
                    Actions\Action::make('create_class')
                    ->label('Create New Class')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('name')
                        ->label('Class Name')
                        ->required()
                        ->placeholder('e.g., January 2025 Cohort')
                        ->maxLength(255),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false),
                            Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->after('start_date'),
                        ]),
                        Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('Details about this class cohort'),
                    ])
                    ->action(fn(array $data) => $this->createClass($data)),
                    Actions\Action::make('back_to_training')
                    ->label('Back to Mentorships')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    private function getModuleHeaderActions(): array {
        return [
                    Actions\Action::make('invite_mentees')
                    ->label('Manage/Invite Mentees')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->url(fn() => MentorshipTrainingResource::getUrl('class-mentees', [
                                'training' => $this->record->id,
                                'class' => $this->selectedClass->id,
                            ]))
                    ->badge(fn() => $this->selectedClass->participants()->count()),
                    Actions\Action::make('back_to_classes')
                    ->label('Back to Classes')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('classes', [
                                'record' => $this->record
                            ])),
        ];
    }

    public function table(Table $table): Table {
        if ($this->viewingModules) {
            return $this->getModulesTable($table);
        }

        return $this->getClassesTable($table);
    }

    private function getClassesTable(Table $table): Table {
        return $table
                        ->query(
                                MentorshipClass::query()
                                ->where('training_id', $this->record->id)
                                ->with(['creator'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Class Name')
                            ->searchable()
                            ->weight('bold')
                            ->description(fn(MentorshipClass $record): string =>
                                    $record->description ?? ''
                            ),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'draft',
                                'warning' => 'active',
                                'success' => 'completed',
                                'danger' => 'cancelled',
                            ]),
                            Tables\Columns\TextColumn::make('start_date')
                            ->date('M j, Y')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('end_date')
                            ->date('M j, Y')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('module_count')
                            ->label('Modules')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('session_count')
                            ->label('Sessions')
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('participants_count')
                            ->label('Mentees')
                            ->counts('participants')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('progress_percentage')
                            ->label('Progress')
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 50 ? 'warning' : 'danger')),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('view_modules')
                                ->label('View Modules')
                                ->icon('heroicon-o-book-open')
                                ->color('primary')
                                ->url(fn(MentorshipClass $record): string =>
                                        MentorshipTrainingResource::getUrl('classes', [
                                            'record' => $this->record,
                                        ]) . '?class=' . $record->id
                                ),
                                Tables\Actions\Action::make('invite_mentees')
                                ->label('Manage/Invite Mentees')
                                ->icon('heroicon-o-user-plus')
                                ->color('success')
                                ->url(fn(MentorshipClass $record): string =>
                                        MentorshipTrainingResource::getUrl('class-mentees', [
                                            'training' => $this->record->id,
                                            'class' => $record->id,
                                        ])
                                ),
//                                Tables\Actions\Action::make('activate')
//                                ->label('Activate')
//                                ->icon('heroicon-o-check')
//                                ->color('warning')
//                                ->visible(fn(MentorshipClass $record) => $record->status === 'draft')
//                                ->requiresConfirmation()
//                                ->action(function (MentorshipClass $record) {
//                                    $record->activate();
//                                    Notification::make()
//                                            ->success()
//                                            ->title('Class Activated')
//                                            ->send();
//                                }),
                                Tables\Actions\EditAction::make()
                                ->form([
                                    Forms\Components\TextInput::make('name')
                                    ->required(),
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\DatePicker::make('start_date')
                                        ->required()
                                        ->native(false),
                                        Forms\Components\DatePicker::make('end_date')
                                        ->required()
                                        ->native(false),
                                    ]),
                                    Forms\Components\Textarea::make('description')
                                    ->rows(3),
                                ]),
                                Tables\Actions\DeleteAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateHeading('No Classes Yet')
                        ->emptyStateDescription('Create your first class cohort to start the mentorship program.')
                        ->emptyStateIcon('heroicon-o-user-group')
                        ->emptyStateActions([
                            Tables\Actions\Action::make('create_first_class')
                            ->label('Create First Class')
                            ->icon('heroicon-o-plus')
                            ->button()
                            ->action(function () {
                                $this->mountAction('create_class');
                            }),
        ]);
    }

    private function getModulesTable(Table $table): Table {
        return $table
                        ->query(
                                ClassModule::query()
                                ->where('mentorship_class_id', $this->selectedClass->id)
                                ->with(['programModule'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('order_sequence')
                            ->label('#')
                            ->badge()
                            ->color('gray')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('programModule.name')
                            ->label('Module Name')
                            ->searchable()
                            ->weight('bold')
                            ->description(fn(ClassModule $record): string =>
                                    $record->programModule->description ?? ''
                            ),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'not_started',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                            ])
                            ->formatStateUsing(fn(string $state): string =>
                                    match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        default => ucfirst($state),
                                    }
                            ),
                            Tables\Columns\TextColumn::make('session_count')
                            ->label('Sessions')
                            ->badge()
                            ->color('primary')
                            ->description(function (ClassModule $record) {
                                $completed = $record->sessions()->where('status', 'completed')->count();
                                return $completed > 0 ? "{$completed} completed" : 'None completed';
                            }),
                            Tables\Columns\TextColumn::make('progress_percentage')
                            ->label('Progress')
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => $state >= 100 ? 'success' : ($state >= 50 ? 'warning' : 'danger')),
                            Tables\Columns\TextColumn::make('programModule.duration_weeks')
                            ->label('Duration')
                            ->suffix(' weeks')
                            ->toggleable(),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('add_sessions')
                                ->label('Add Sessions')
                                ->icon('heroicon-o-plus-circle')
                                ->color('success')
                                ->url(fn(ClassModule $record): string =>
                                        MentorshipTrainingResource::getUrl('module-sessions', [
                                            'training' => $this->record->id,
                                            'class' => $this->selectedClass->id,
                                            'module' => $record->id,
                                        ])
                                ),
                                Tables\Actions\Action::make('start_module')
                                ->label('Start Module')
                                ->icon('heroicon-o-play')
                                ->color('primary')
                                ->visible(fn(ClassModule $record) => $record->status === 'not_started')
                                ->requiresConfirmation()
                                ->action(function (ClassModule $record) {
                                    $record->start();
                                    Notification::make()
                                            ->success()
                                            ->title('Module Started')
                                            ->send();
                                }),
                                Tables\Actions\Action::make('complete_module')
                                ->label('Complete Module')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->visible(fn(ClassModule $record) => $record->status === 'in_progress')
                                ->requiresConfirmation()
                                ->action(function (ClassModule $record) {
                                    $record->complete();
                                    Notification::make()
                                            ->success()
                                            ->title('Module Completed')
                                            ->send();
                                }),
                            ]),
                        ])
                        ->reorderable('order_sequence')
                        ->emptyStateHeading('No Modules Configured')
                        ->emptyStateDescription('Modules should be auto-populated from the mentorship program.');
    }

    private function createClass(array $data): void {
        $class = MentorshipClass::create([
            'training_id' => $this->record->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        // Auto-populate modules from training's program
        if ($this->record->program_id) {
            $programModules = \App\Models\ProgramModule::where('program_id', $this->record->program_id)
                    ->where('is_active', true)
                    ->orderBy('order_sequence')
                    ->get();

            foreach ($programModules as $index => $programModule) {
                ClassModule::create([
                    'mentorship_class_id' => $class->id,
                    'program_module_id' => $programModule->id,
                    'order_sequence' => $index + 1,
                    'status' => 'not_started',
                ]);
            }
        }

        Notification::make()
                ->success()
                ->title('Class Created')
                ->body("Class '{$data['name']}' created with modules. Now add sessions to each module.")
                ->send();

        // Navigate to the new class's modules using query parameter
        redirect(MentorshipTrainingResource::getUrl('classes', [
                    'record' => $this->record,
                ]) . '?class=' . $class->id);
    }
}