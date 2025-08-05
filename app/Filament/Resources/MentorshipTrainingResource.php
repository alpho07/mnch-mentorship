<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MentorshipTrainingResource\Pages;
use App\Models\Training;
use App\Models\Facility;
use App\Models\FacilityAssessment;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\AssessmentCategory;
use App\Models\Module;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class MentorshipTrainingResource extends Resource
{
    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Mentorship Training';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'mentorship-trainings';
    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'facility_mentorship')
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('type')->default('facility_mentorship'),

            Section::make('Basic Information')
                ->description('Start by selecting the facility and defining your mentorship program')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('facility_id')
                            ->label('Training Facility')
                            ->relationship('facility', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $assessment = FacilityAssessment::where('facility_id', $state)
                                        ->where('status', 'approved')
                                        ->where('next_assessment_due', '>', now())
                                        ->latest()
                                        ->first();
                                    
                                    $set('facility_assessment_status', $assessment ? 'valid' : 'required');
                                }
                            })
                            ->helperText(fn (Get $get) => 
                                $get('facility_assessment_status') === 'required' 
                                    ? '⚠️ Facility assessment required'
                                    : ($get('facility_assessment_status') === 'valid' 
                                        ? '✅ Facility assessment valid' 
                                        : 'Select facility to check status')
                            ),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Newborn Care Mentorship Program')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state && !$get('identifier')) {
                                    $set('identifier', 'MT-' . strtoupper(Str::random(6)));
                                }
                            }),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('identifier')
                            ->label('Mentorship ID')
                            ->required()
                            ->unique(Training::class, ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('MT-ABC123')
                            ->disabled(fn($record) => filled($record?->identifier))
                            ->dehydrated(true),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'ongoing' => 'Ongoing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('draft'),
                    ]),

                    Textarea::make('description')
                        ->rows(3)
                        ->placeholder('Describe the mentorship objectives and approach')
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('facility_assessment_status'),
                ]),

            Section::make('Training Content')
                ->description('Select programs, modules, and training methodologies')
                ->collapsible()
                ->schema([
                    Select::make('programs')
                        ->label('Programs')
                        ->multiple()
                        ->relationship('programs', 'name')
                        ->preload()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('modules', []);
                        })
                        ->helperText('Select relevant training programs'),

                    Select::make('modules')
                        ->label('Modules')
                        ->multiple()
                        ->options(function (Get $get) {
                            $programIds = $get('programs');
                            if (!$programIds) {
                                return [];
                            }

                            return Module::whereIn('program_id', $programIds)
                                ->with('program')
                                ->get()
                                ->mapWithKeys(function ($module) {
                                    return [
                                        $module->id => "{$module->program->name} - {$module->name}"
                                    ];
                                })
                                ->toArray();
                        })
                        ->searchable()
                        ->helperText('Modules are grouped by their parent program'),

                    Select::make('methodologies')
                        ->label('Training Methodologies')
                        ->multiple()
                        ->relationship('methodologies', 'name')
                        ->preload()
                        ->searchable()
                        ->helperText('Select the training methods that will be used'),

                    Textarea::make('learning_outcomes')
                        ->label('Expected Learning Outcomes')
                        ->rows(3)
                        ->placeholder('What should mentees achieve?'),

                    Textarea::make('prerequisites')
                        ->rows(2)
                        ->placeholder('Required knowledge or experience'),

                    Forms\Components\TagsInput::make('training_approaches')
                        ->suggestions([
                            'One-on-One Mentoring',
                            'Clinical Supervision',
                            'Practical Demonstrations',
                            'Case Discussions',
                            'Peer Learning',
                            'Skills Practice',
                            'Bedside Teaching',
                        ])
                        ->placeholder('Add training approaches...'),
                ]),

            Section::make('Schedule & Team')->schema([
                Grid::make(3)->schema([
                    DatePicker::make('start_date')
                        ->required()
                        ->native(false)
                        ->minDate(now())
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if ($state) {
                                $set('end_date', now()->parse($state)->addWeek()->format('Y-m-d'));
                            }
                        }),

                    DatePicker::make('end_date')
                        ->required()
                        ->native(false)
                        ->minDate(fn (Get $get) => $get('start_date') ?: now()),

                    TextInput::make('max_participants')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(15)
                        ->helperText('Recommended: 10-20 participants'),
                ]),

                Grid::make(2)->schema([
                    Select::make('mentor_id')
                        ->label('Lead Mentor')
                        ->relationship('mentor', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn(User $record): string => 
                            "{$record->first_name} {$record->last_name} - {$record->cadre?->name}"
                        )
                        ->searchable(['first_name', 'last_name'])
                        ->preload(),

                    Select::make('organizer_id')
                        ->label('Training Coordinator')
                        ->relationship('organizer', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn(User $record): string => 
                            "{$record->first_name} {$record->last_name} - {$record->department?->name}"
                        )
                        ->searchable(['first_name', 'last_name'])
                        ->preload(),
                ]),
            ]),

            Section::make('Assessment Framework')
                ->description('Configure how mentees will be evaluated')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    
                    CheckboxList::make('selected_assessment_categories')
                        ->label('Select Assessment Categories')
                        ->options(function () {
                            return AssessmentCategory::active()
                                ->ordered()
                                ->get()
                                ->mapWithKeys(fn($category) => [
                                    $category->id => $category->name . " (Default: {$category->default_weight_percentage}%)"
                                ]);
                        })
                        ->descriptions(function () {
                            return AssessmentCategory::active()
                                ->ordered()
                                ->get()
                                ->mapWithKeys(fn($category) => [
                                    $category->id => "{$category->description} • Method: {$category->assessment_method} • " .
                                        ($category->is_required ? 'Required' : 'Optional')
                                ]);
                        })
                        ->columns(1)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                            if (is_array($state)) {
                                $currentSettings = $get('assessment_category_settings') ?: [];
                                $newSettings = [];
                                
                                foreach ($state as $categoryId) {
                                    $category = AssessmentCategory::find($categoryId);
                                    if ($category) {
                                        $existingSetting = collect($currentSettings)
                                            ->firstWhere('assessment_category_id', $categoryId);
                                        
                                        if ($existingSetting) {
                                            $newSettings[] = $existingSetting;
                                        } else {
                                            $newSettings[] = [
                                                'assessment_category_id' => $categoryId,
                                                'weight_percentage' => $category->default_weight_percentage,
                                                'pass_threshold' => 70.00,
                                                'is_required' => $category->is_required,
                                                'is_active' => true,
                                                'order_sequence' => $category->order_sequence,
                                            ];
                                        }
                                    }
                                }
                                
                                $set('assessment_category_settings', $newSettings);
                                $totalWeight = collect($newSettings)->sum('weight_percentage');
                                $set('total_weight_check', $totalWeight);
                            }
                        }),

                    Placeholder::make('weight_validation')
                        ->content(function (Get $get): HtmlString {
                            $settings = $get('assessment_category_settings') ?: [];
                            $totalWeight = collect($settings)->sum('weight_percentage');
                            
                            if (empty($settings)) {
                                return new HtmlString('<div class="text-gray-500">Select categories above to configure weights</div>');
                            }
                            
                            $isValid = abs($totalWeight - 100) < 0.1;
                            $color = $isValid ? 'green' : 'red';
                            $icon = $isValid ? '✅' : '⚠️';
                            $status = $isValid ? 'Valid' : 'Invalid';
                            
                            return new HtmlString("
                                <div class='bg-{$color}-50 border border-{$color}-200 rounded-lg p-3'>
                                    <div class='flex items-center justify-between'>
                                        <span class='text-{$color}-800 font-medium'>{$icon} Total Weight: {$totalWeight}%</span>
                                        <span class='text-{$color}-600 text-sm'>{$status}</span>
                                    </div>
                                    " . (!$isValid ? "<p class='text-{$color}-700 text-sm mt-1'>Weights must total exactly 100%</p>" : "") . "
                                </div>
                            ");
                        })
                        ->visible(fn (Get $get) => !empty($get('assessment_category_settings')))
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('total_weight_check'),

                    Repeater::make('assessment_category_settings')
                        ->label('Assessment Category Configuration')
                        ->schema([
                            Grid::make(5)->schema([
                                Select::make('assessment_category_id')
                                    ->label('Category')
                                    ->options(AssessmentCategory::active()->pluck('name', 'id'))
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('weight_percentage')
                                    ->label('Weight (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.1)
                                    ->suffix('%')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $settings = $get('../../assessment_category_settings') ?: [];
                                        $totalWeight = collect($settings)->sum('weight_percentage');
                                        $set('../../total_weight_check', $totalWeight);
                                    }),

                                TextInput::make('pass_threshold')
                                    ->label('Pass Score (%)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(70)
                                    ->suffix('%')
                                    ->required(),

                                Toggle::make('is_required')
                                    ->label('Required')
                                    ->default(true)
                                    ->helperText('Must pass to complete'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Use in assessment'),
                            ]),

                            Forms\Components\Hidden::make('order_sequence'),
                        ])
                        ->itemLabel(function (array $state): ?string {
                            if (!isset($state['assessment_category_id'])) return 'New Category Setting';
                            
                            $category = AssessmentCategory::find($state['assessment_category_id']);
                            $weight = $state['weight_percentage'] ?? 0;
                            $required = ($state['is_required'] ?? false) ? 'Required' : 'Optional';
                            
                            return $category ? "{$category->name} ({$weight}% • {$required})" : 'Category Setting';
                        })
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsed()
                        ->columnSpanFull(),

                    Actions::make([
                        Actions\Action::make('load_standard_assessment')
                            ->label('Load Standard Assessment')
                            ->icon('heroicon-o-star')
                            ->color('info')
                            ->action(function (Set $set) {
                                $practicalSkills = AssessmentCategory::active()->where('name', 'Practical Skills')->first();
                                $clinicalKnowledge = AssessmentCategory::active()->where('name', 'Clinical Knowledge')->first();
                                $postTest = AssessmentCategory::active()->where('name', 'Post-Test')->first();

                                $categoryIds = [];
                                $settings = [];
                                
                                if ($practicalSkills) {
                                    $categoryIds[] = $practicalSkills->id;
                                    $settings[] = [
                                        'assessment_category_id' => $practicalSkills->id,
                                        'weight_percentage' => 50.0,
                                        'pass_threshold' => 80.0,
                                        'is_required' => true,
                                        'is_active' => true,
                                        'order_sequence' => 1,
                                    ];
                                }
                                
                                if ($clinicalKnowledge) {
                                    $categoryIds[] = $clinicalKnowledge->id;
                                    $settings[] = [
                                        'assessment_category_id' => $clinicalKnowledge->id,
                                        'weight_percentage' => 30.0,
                                        'pass_threshold' => 75.0,
                                        'is_required' => true,
                                        'is_active' => true,
                                        'order_sequence' => 2,
                                    ];
                                }
                                
                                if ($postTest) {
                                    $categoryIds[] = $postTest->id;
                                    $settings[] = [
                                        'assessment_category_id' => $postTest->id,
                                        'weight_percentage' => 20.0,
                                        'pass_threshold' => 70.0,
                                        'is_required' => true,
                                        'is_active' => true,
                                        'order_sequence' => 3,
                                    ];
                                }

                                if (!empty($categoryIds)) {
                                    $set('selected_assessment_categories', $categoryIds);
                                    $set('assessment_category_settings', $settings);
                                    $set('total_weight_check', 100.0);

                                    Notification::make()
                                        ->title('Standard Assessment Loaded')
                                        ->body('Practical Skills (50%), Clinical Knowledge (30%), Post-Test (20%)')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Categories Not Found')
                                        ->body('Please create the required assessment categories first.')
                                        ->warning()
                                        ->send();
                                }
                            }),
                    ])->fullWidth(),
                ]),

            Section::make('Training Materials')
                ->description('Select inventory items for the mentorship')
                ->collapsible()
                ->schema([
                    Repeater::make('training_materials')
                        ->relationship('trainingMaterials')
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('inventory_item_id')
                                    ->label('Material')
                                    ->relationship('inventoryItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $item = InventoryItem::find($state);
                                            $set('unit_cost', $item?->unit_price ?? 0);
                                        }
                                    })
                                    ->columnSpan(2),

                                TextInput::make('quantity_planned')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $cost = $get('unit_cost') ?? 0;
                                        $set('total_cost', $state * $cost);
                                    }),

                                TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->disabled()
                                    ->dehydrated(true),
                            ]),

                            Grid::make(2)->schema([
                                TextInput::make('total_cost')
                                    ->label('Total Cost')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->disabled()
                                    ->dehydrated(true),

                                Textarea::make('usage_notes')
                                    ->label('Usage Notes')
                                    ->placeholder('How will this be used?')
                                    ->rows(2),
                            ]),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add Material')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => 
                            InventoryItem::find($state['inventory_item_id'])?->name ?? 'New Material'
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Additional Information')
                ->collapsible()
                ->schema([
                    Textarea::make('notes')
                        ->rows(3)
                        ->placeholder('Additional notes or instructions'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('identifier')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn(Training $record): string => 
                        $record->facility?->name ?? ''
                    ),

                TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('programs.name')
                    ->label('Programs')
                    ->badge()
                    ->separator(', ')
                    ->limit(30)
                    ->color('success'),

                TextColumn::make('mentor_name')
                    ->label('Lead Mentor')
                    ->getStateUsing(fn(Training $record): string =>
                        $record->mentor ? "{$record->mentor->first_name} {$record->mentor->last_name}" : 'Not assigned'
                    )
                    ->description(fn(Training $record): string => 
                        $record->mentor?->cadre?->name ?? ''
                    ),

                TextColumn::make('start_date')
                    ->date('M j, Y')
                    ->sortable()
                    ->description(fn(Training $record): string => 
                        $record->end_date ? 'to ' . $record->end_date->format('M j, Y') : ''
                    ),

                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'ongoing',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                    ]),

                TextColumn::make('participants_count')
                    ->label('Mentees')
                    ->counts('participants')
                    ->badge()
                    ->color('success'),

                TextColumn::make('assessment_categories_count')
                    ->label('Categories')
                    ->counts('assessmentCategories')
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('facility')
                    ->relationship('facility', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('mentor')
                    ->relationship('mentor', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn(User $record): string => 
                        "{$record->first_name} {$record->last_name}"
                    )
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info'),
                    Tables\Actions\EditAction::make()
                        ->color('warning'),
                    Action::make('manage_mentees')
                        ->label('Manage Mentees')
                        ->icon('heroicon-o-users')
                        ->color('success')
                        ->url(fn(Training $record): string => 
                            static::getUrl('mentees', ['record' => $record])
                        ),
                    Action::make('assessment_matrix')
                        ->label('Assessment Matrix')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('primary')
                        ->url(fn(Training $record): string => 
                            static::getUrl('assessments', ['record' => $record])
                        ),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->size('sm')
                ->color('gray')
                ->button()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No Mentorship Programs Found')
            ->emptyStateDescription('Create your first facility-based mentorship training program.')
            ->emptyStateIcon('heroicon-o-academic-cap');
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'facility_mentorship';
        
        if (isset($data['facility_id'])) {
            $assessment = FacilityAssessment::where('facility_id', $data['facility_id'])
                ->where('status', 'approved')
                ->where('next_assessment_due', '>', now())
                ->latest()
                ->first();
                
            if (!$assessment) {
                throw new \Exception('Facility assessment must be completed and approved before creating mentorship training.');
            }
        }
        
        if (isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }
        
        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'facility_mentorship';
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
            'view' => Pages\ViewMentorshipTraining::route('/{record}'),
            'edit' => Pages\EditMentorshipTraining::route('/{record}/edit'),
            'mentees' => Pages\ManageMentorshipMentees::route('/{record}/mentees'),
            'assessments' => Pages\ManageMentorshipAssessments::route('/{record}/assessments'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('type', 'facility_mentorship')
            ->where('status', 'ongoing')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Facility' => $record->facility?->name,
            'Status' => ucfirst($record->status),
            'Start Date' => $record->start_date?->format('M j, Y'),
            'Mentees' => $record->participants()->count(),
        ];
    }
}