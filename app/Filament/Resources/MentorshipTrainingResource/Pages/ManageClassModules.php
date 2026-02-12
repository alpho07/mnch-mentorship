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

class ManageClassModules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-class-modules';
    protected static bool $shouldRegisterNavigation = false;
    
    public Training $training;
    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void
    {
        $this->training = $training;
        $this->class = $class->load('training');
    }

    public function getTitle(): string
    {
        return "Manage Modules - {$this->class->name}";
    }

    public function getSubheading(): ?string
    {
        $moduleCount = $this->class->classModules()->count();
        return "{$this->training->name} • {$moduleCount} modules selected";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_modules')
                ->label('Add Modules')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->slideOver()
                ->modalWidth('3xl')
                ->form([
                    Forms\Components\Section::make('Select Modules to Add')
                        ->description('Choose modules from the training program that you want to include in this class.')
                        ->schema([
                            Forms\Components\Placeholder::make('info')
                                ->content(function () {
                                    $total = ProgramModule::whereHas('trainings', function ($query) {
                                        $query->where('trainings.id', $this->training->id);
                                    })->count();
                                    
                                    $added = $this->class->classModules()->count();
                                    $available = $total - $added;
                                    
                                    return "Total modules in training: {$total} | Already added: {$added} | Available: {$available}";
                                }),
                            
                            Forms\Components\CheckboxList::make('selected_modules')
                                ->label('Available Modules')
                                ->options(function () {
                                    $existingModuleIds = $this->class->classModules()
                                        ->pluck('program_module_id')
                                        ->toArray();
                                    
                                    return ProgramModule::whereHas('trainings', function ($query) {
                                        $query->where('trainings.id', $this->training->id);
                                    })
                                    ->whereNotIn('id', $existingModuleIds)
                                    ->orderBy('order_sequence')
                                    ->pluck('name', 'id')
                                    ->toArray();
                                })
                                ->descriptions(function () {
                                    $existingModuleIds = $this->class->classModules()
                                        ->pluck('program_module_id')
                                        ->toArray();
                                    
                                    return ProgramModule::whereHas('trainings', function ($query) {
                                        $query->where('trainings.id', $this->training->id);
                                    })
                                    ->whereNotIn('id', $existingModuleIds)
                                    ->orderBy('order_sequence')
                                    ->get()
                                    ->mapWithKeys(fn($module) => [
                                        $module->id => ($module->description ?? '') . 
                                            ($module->duration_hours ? ' • ' . $module->duration_hours . ' hours' : '')
                                    ])
                                    ->toArray();
                                })
                                ->columns(1)
                                ->gridDirection('row')
                                ->bulkToggleable()
                                ->required()
                                ->helperText('Select one or more modules to add to this class')
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data) {
                    if (empty($data['selected_modules'])) {
                        Notification::make()
                            ->warning()
                            ->title('No Modules Selected')
                            ->body('Please select at least one module to add.')
                            ->send();
                        return;
                    }
                    
                    DB::transaction(function () use ($data) {
                        $maxSequence = $this->class->classModules()->max('order_sequence') ?? 0;
                        
                        foreach ($data['selected_modules'] as $programModuleId) {
                            ClassModule::create([
                                'mentorship_class_id' => $this->class->id,
                                'program_module_id' => $programModuleId,
                                'status' => 'not_started',
                                'order_sequence' => ++$maxSequence,
                            ]);
                        }
                    });
                    
                    Notification::make()
                        ->success()
                        ->title('Modules Added')
                        ->body(count($data['selected_modules']) . ' module(s) added to class successfully')
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

    public function table(Table $table): Table
    {
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
                
                Tables\Columns\TextColumn::make('programModule.duration_hours')
                    ->label('Duration')
                    ->suffix(' hrs')
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
                Tables\Actions\Action::make('manage_mentees')
                    ->label('Mentees')
                    ->icon('heroicon-o-users')
                    ->url(fn (ClassModule $record) => MentorshipTrainingResource::getUrl('module-mentees', [
                        'record' => $this->training->id,
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
                    ->modalDescription('Are you sure you want to remove this module? This will also delete all related progress records.')
                    ->successNotificationTitle('Module removed from class'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Remove Selected')
                        ->modalHeading('Remove Modules from Class')
                        ->modalDescription('Are you sure? This will delete all related progress records.')
                        ->successNotificationTitle('Modules removed from class'),
                ]),
            ])
            ->emptyStateHeading('No Modules Added Yet')
            ->emptyStateDescription('Click "Add Modules" to select modules from the training program')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->emptyStateActions([
                Tables\Actions\Action::make('add_first_module')
                    ->label('Add Modules')
                    ->icon('heroicon-o-plus-circle')
                    ->action(fn() => $this->mountAction('add_modules')),
            ]);
    }
}