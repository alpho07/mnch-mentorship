<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ProgramModule;
use App\Services\ModuleUsageService;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;

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
        return "Manage Modules - {$this->class->name}";
    }

    public function getSubheading(): ?string {
        $moduleCount = $this->class->classModules()->count();
        $usageService = app(ModuleUsageService::class);
        $availableCount = $usageService->getAvailableModules($this->training, $this->class)->count();

        return "{$this->training->name} • {$moduleCount} modules selected • {$availableCount} available";
    }

    /**
     * Get the structured module availability data.
     */
    private function getModuleAvailability(): \Illuminate\Support\Collection {
        $usageService = app(ModuleUsageService::class);

        return $usageService->getModulesWithAvailability($this->training, $this->class);
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('add_modules')
                    ->label('Add Modules')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->visible(function () {
                        try {
                            return $this->getModuleAvailability()->isNotEmpty();
                        } catch (\Throwable $e) {
                            // If query fails, show button anyway so user can see the error
                            return true;
                        }
                    })
                    ->form([
                        Forms\Components\Section::make('Select Modules to Add')
                        ->description('Choose modules for this class. Modules already used in this mentorship are hidden. Modules completed at this facility in other mentorships are shown but disabled.')
                        ->schema([
                            Forms\Components\Placeholder::make('info')
                            ->content(function () {
                                $availability = $this->getModuleAvailability();

                                $programIds = [];
                                if ($this->training->program_id) {
                                    $programIds[] = $this->training->program_id;
                                }
                                $pivotIds = \Illuminate\Support\Facades\DB::table('training_programs')
                                        ->where('training_id', $this->training->id)
                                        ->pluck('program_id')
                                        ->toArray();
                                $programIds = array_unique(array_merge($programIds, $pivotIds));

                                $total = ProgramModule::whereIn('program_id', $programIds)
                                                ->where('is_active', true)->count();

                                $usageService = app(ModuleUsageService::class);
                                $usedInMentorship = $usageService->getUsedModules($this->training)->count();
                                $inThisClass = $this->class->classModules()->count();
                                $selectableCount = $availability->filter(fn($i) => $i->available)->count();
                                $disabledCount = $availability->filter(fn($i) => !$i->available)->count();

                                $text = "Total program modules: {$total} | Used in this mentorship: {$usedInMentorship} | In this class: {$inThisClass} | Selectable: {$selectableCount}";
                                if ($disabledCount > 0) {
                                    $text .= " | Completed at facility: {$disabledCount} (disabled)";
                                }

                                return $text;
                            }),
                            Forms\Components\CheckboxList::make('selected_modules')
                            ->label('Available Modules')
                            ->options(function () {
                                $availability = $this->getModuleAvailability();

                                return $availability->mapWithKeys(function ($item) {
                                            $label = $item->module->name;
                                            if (!$item->available) {
                                                $label .= ' 🔒';
                                            }

                                            return [$item->module->id => $label];
                                        })->toArray();
                            })
                            ->descriptions(function () {
                                $availability = $this->getModuleAvailability();

                                return $availability->mapWithKeys(function ($item) {
                                            if (!$item->available && $item->disabled_reason) {
                                                // Show the reason it's disabled
                                                return [$item->module->id => '⚠️ ' . $item->disabled_reason];
                                            }

                                            // Normal description for available modules
                                            $desc = $item->module->description ?? '';
                                            if ($item->module->duration_weeks) {
                                                $desc .= ($desc ? ' • ' : '') . $item->module->duration_weeks . ' weeks';
                                            }

                                            return [$item->module->id => $desc];
                                        })->toArray();
                            })
                            ->disableOptionWhen(function (string $value) {
                                $availability = $this->getModuleAvailability();
                                $item = $availability->first(fn($i) => $i->module->id == $value);

                                return $item && !$item->available;
                            })
                            ->columns(1)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->helperText('Modules can only be taught once per mentorship. Disabled modules were completed at this facility in another mentorship.')
                            ->columnSpanFull(),
                        ]),
                    ])
                    ->action(function (array $data) {
                        // Filter out any disabled modules that somehow got through
                        $availability = $this->getModuleAvailability();
                        $disabledIds = $availability->filter(fn($i) => !$i->available)
                                ->map(fn($i) => $i->module->id)
                                ->toArray();

                        $selectedModules = array_diff($data['selected_modules'] ?? [], $disabledIds);

                        if (empty($selectedModules)) {
                            Notification::make()
                                    ->warning()
                                    ->title('No Modules Selected')
                                    ->body('Please select at least one available module to add.')
                                    ->send();

                            return;
                        }

                        $usageService = app(ModuleUsageService::class);
                        $assigned = $usageService->assignModulesToClass(
                                $this->training,
                                $this->class,
                                $selectedModules
                        );

                        if ($assigned > 0) {
                            Notification::make()
                                    ->success()
                                    ->title('Modules Added')
                                    ->body("{$assigned} module(s) added to class successfully.")
                                    ->send();
                        } else {
                            Notification::make()
                                    ->warning()
                                    ->title('No Modules Added')
                                    ->body('Selected modules may have already been used.')
                                    ->send();
                        }
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
                                ClassModule::query()
                                ->where('mentorship_class_id', $this->class->id)
                                ->with(['programModule'])
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
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('programModule.description')
                            ->label('Description')
                            ->limit(50)
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('programModule.duration_weeks')
                            ->label('Duration')
                            ->suffix(' weeks')
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'not_started',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                            ]),
                            Tables\Columns\TextColumn::make('started_at')
                            ->label('Started')
                            ->dateTime()
                            ->sortable()
                            ->toggleable()
                            ->toggledHiddenByDefault(),
                            Tables\Columns\TextColumn::make('completed_at')
                            ->label('Completed')
                            ->dateTime()
                            ->sortable()
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
                            ->color('primary')
                            ->url(fn(ClassModule $classModule) => MentorshipTrainingResource::getUrl('module-sessions', [
                                        'training' => $this->training->id,
                                        'class' => $this->class->id,
                                        'module' => $classModule->id,
                                    ])),
                            Tables\Actions\Action::make('manage_mentees')
                            ->label('Mentees')
                            ->icon('heroicon-o-users')
                            ->url(fn(ClassModule $classModule) => MentorshipTrainingResource::getUrl('module-mentees', [
                                        'training' => $this->training->id,
                                        'class' => $this->class->id,
                                        'module' => $classModule->id,
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
                            Tables\Actions\Action::make('remove')
                            ->label('Remove')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Remove Module from Class')
                            ->modalDescription('This will remove the module from this class and delete related progress records. The module slot remains consumed for this mentorship.')
                            ->action(function (ClassModule $record) {
                                $usageService = app(ModuleUsageService::class);
                                $removed = $usageService->removeModuleFromClass(
                                        $this->training,
                                        $this->class,
                                        $record
                                );

                                if ($removed) {
                                    Notification::make()
                                            ->success()
                                            ->title('Module Removed')
                                            ->body('Module removed from this class. The module slot remains consumed for this mentorship.')
                                            ->send();
                                } else {
                                    Notification::make()
                                            ->danger()
                                            ->title('Cannot Remove Module')
                                            ->body('This module has completed sessions and cannot be removed.')
                                            ->send();
                                }
                            }),
                        ])
                        ->bulkActions([])
                        ->emptyStateHeading('No Modules Added Yet')
                        ->emptyStateDescription('Click "Add Modules" to select modules for this class. Modules already taught in this mentorship or completed at this facility are restricted.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\Action::make('add_first_module')
                            ->label('Add Modules')
                            ->icon('heroicon-o-plus-circle')
                            ->action(fn() => $this->mountAction('add_modules')),
        ]);
    }
}
