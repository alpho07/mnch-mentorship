<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalTrainingResource\Pages;
use App\Models\Training;
use App\Models\Program;
use App\Models\User;
use App\Models\County;
use App\Models\Partner;
use App\Models\AssessmentCategory;
use App\Models\InventoryItem;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class GlobalTrainingResource extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'MOH';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'moh-trainings';
    protected static ?string $recordTitleAttribute = 'title';

    // Filter to only show global trainings
    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->where('type', 'global_training')
                        ->withoutGlobalScopes([
                            SoftDeletingScope::class,
        ]);
    }

    // Ensure proper route model binding for global trainings only
    public static function resolveRecordRouteBinding($value): ?Model {
        return static::getModel()::where('type', 'global_training')->findOrFail($value);
    }

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            // Hidden field to ensure type is always set
                            Forms\Components\Hidden::make('type')
                            ->default('global_training'),
                            // Hidden field to store mentor_id (logged in user)
                            Forms\Components\Hidden::make('mentor_id')
                            ->default(auth()->id()),
                            Forms\Components\Section::make('Training Information')
                            ->description('Basic details about the training program')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                    ->label('Training Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter the training title')
                                    ->columnSpanFull(),
                                    TextInput::make('identifier')
                                    ->label('Training ID')
                                    ->required()
                                    ->readOnly(true)
                                    ->unique(Training::class, ignoreRecord: true)
                                    ->maxLength(50)
                                    ->default(fn($record) => $record?->identifier ?? strtoupper('TRN-' . Str::random(8)))
                                    ->disabled(fn($record) => filled($record?->identifier))
                                    ->dehydrated(true)
                                    ->helperText('Auto-generated unique identifier'),
                                    Forms\Components\Select::make('status')
                                    ->label('Training Status')
                                    ->options([
                                        'new' => 'New',
                                        'ongoing' => 'Ongoing',
                                        'repeat' => 'Repeat',
                                    ])
                                    ->required()
                                    ->default('new'),
                                ]),
                                Forms\Components\Textarea::make('description')
                                ->label('Training Description')
                                ->rows(3)
                                ->placeholder('Provide a detailed description of the training program')
                                ->columnSpanFull(),
                            ]),
                            Forms\Components\Section::make('Training Leadership')
                            ->description('Select who will lead this training')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('lead_type')
                                    ->label('Training Lead Type')
                                    ->options([
                                        'national' => 'National',
                                        'county' => 'County',
                                        'partner' => 'Partner Led',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        // Clear dependent fields when lead type changes
                                        if ($state === 'national') {
                                            $set('lead_county_id', null);
                                            $set('lead_partner_id', null);
                                        } elseif ($state === 'county') {
                                            $set('lead_division_id', null);
                                            $set('lead_partner_id', null);
                                        } elseif ($state === 'partner') {
                                            $set('lead_division_id', null);
                                            $set('lead_county_id', null);
                                        }
                                    }),
                                    Forms\Components\Select::make('lead_division_id')
                                    ->label('Division')
                                    ->relationship('division', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->placeholder('Select the division leading this training'),
                                ]),
                                Forms\Components\Grid::make(1)
                                ->schema([
                                    Forms\Components\Select::make('lead_county_id')
                                    ->label('County')
                                    ->relationship('county', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'county')
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'county')
                                    ->placeholder('Select the county leading this training'),
                                    Forms\Components\Select::make('lead_partner_id')
                                    ->label('Partner Organization')
                                    ->relationship('partner', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'partner')
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'partner')
                                    ->placeholder('Select the partner organization leading this training'),
                                ]),
                            ]),
                            Forms\Components\Section::make('Schedule & Logistics')
                            ->description('Training dates, location, and capacity')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M j, Y'),
                                    Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M j, Y')
                                    ->after('start_date'),
                                    Forms\Components\TextInput::make('max_participants')
                                    ->label('Maximum Participants')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(30)
                                    ->suffix('people'),
                                ]),
                                Forms\Components\Select::make('locations')
                                ->label('Training Locations')
                                ->multiple()
                                ->relationship('locations', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Location Name'),
                                    Forms\Components\Select::make('type')
                                    ->options([
                                        'training_center' => 'Training Center',
                                        'hospital' => 'Hospital',
                                        'conference_hall' => 'Conference Hall',
                                        'hotel' => 'Hotel',
                                        'university' => 'University',
                                        'other' => 'Other',
                                    ])
                                    ->default('training_center'),
                                    Forms\Components\Textarea::make('address')
                                    ->rows(2)
                                    ->label('Address'),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    return \App\Models\Location::create($data)->id;
                                })
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state) {
                                        $locations = \App\Models\Location::whereIn('id', $state)->get();
                                        $locationNames = $locations->pluck('name')->implode(', ');
                                        $set('location', $locationNames);
                                    }
                                })
                                ->placeholder('Select or create training locations')
                                ->helperText('Select multiple existing locations or create new ones. Hold Ctrl/Cmd to select multiple.')
                                ->columnSpanFull(),
                                Forms\Components\TagsInput::make('location_tags')
                                ->label('Or Add Locations as Tags')
                                ->placeholder('Type location names and press Enter')
                                ->helperText('Alternative way to add locations. These will be created automatically if they don\'t exist.')
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    if ($state) {
                                        // Create locations that don't exist and get their IDs
                                        $locationIds = [];
                                        $existingLocationIds = $get('locations') ?? [];

                                        foreach ($state as $locationName) {
                                            $location = \App\Models\Location::firstOrCreate(
                                                    ['name' => $locationName],
                                                    ['type' => 'other']
                                            );
                                            $locationIds[] = $location->id;
                                        }

                                        // Merge with existing selected locations
                                        $allLocationIds = array_unique(array_merge($existingLocationIds, $locationIds));
                                        $set('locations', $allLocationIds);

                                        // Update the location text field
                                        $allLocations = \App\Models\Location::whereIn('id', $allLocationIds)->get();
                                        $set('location', $allLocations->pluck('name')->implode(', '));
                                    }
                                })
                                ->columnSpanFull(),
                                Forms\Components\TextInput::make('location')
                                ->label('Combined Locations (Auto-filled)')
                                ->disabled()
                                ->dehydrated(true)
                                ->placeholder('Will be auto-filled when you select locations above')
                                ->helperText('This field combines all selected locations for display purposes')
                                ->columnSpanFull(),
                            ]),
                            Forms\Components\Section::make('Content & Programs')
                            ->description('Select the programs, modules, and methodologies for this training')
                            ->schema([
                                Forms\Components\Select::make('programs')
                                ->label('Training Programs')
                                ->multiple()
                                ->relationship('programs', 'name')
                                ->preload()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    // Clear modules when programs change
                                    $set('modules', []);
                                })
                                ->helperText('Select the main programs this training will cover')
                                ->columnSpanFull(),
                                Forms\Components\Select::make('modules')
                                ->label('Training Modules')
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
                                ->helperText('Specific modules within the selected programs')
                                ->columnSpanFull(),
                                Forms\Components\Select::make('methodologies')
                                ->label('Training Methodologies')
                                ->multiple()
                                ->relationship('methodologies', 'name')
                                ->preload()
                                ->searchable()
                                ->helperText('Select the training methods that will be used')
                                ->columnSpanFull(),
                            ]),
                            // NEW: Optional Assessment Framework
                            Section::make('Training Options')
                            ->description('Configure optional features for this training')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('assess_participants')
                                    ->label('Will Participants Be Assessed?')
                                    ->helperText('Enable to configure assessment categories and evaluation criteria')
                                    ->live()
                                    ->default(false),
                                    Toggle::make('provide_materials')
                                    ->label('Will Materials Be Provided?')
                                    ->helperText('Enable to plan and track training materials and costs')
                                    ->live()
                                    ->default(false),
                                ]),
                            ]),
                            // NEW: Assessment Framework (Conditional)
                            Section::make('Assessment Framework')
                            ->description('Configure how participants will be evaluated')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->visible(fn(Get $get): bool => $get('assess_participants') === true)
                            ->collapsible()
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
                                ->visible(fn(Get $get) => !empty($get('assessment_category_settings')))
                                ->columnSpanFull(),
                                Forms\Components\Hidden::make('total_weight_check'),
                                Repeater::make('assessment_category_settings')
                                ->label('Assessment Category Configuration')
                                ->schema([
                                    Grid::make(5)->schema([
                                        Forms\Components\Select::make('assessment_category_id')
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
                                    if (!isset($state['assessment_category_id']))
                                        return 'New Category Setting';

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
                            // NEW: Training Materials (Conditional)
                            Section::make('Training Materials')
                            ->description('Plan and track materials for this training')
                            ->icon('heroicon-o-cube')
                            ->visible(fn(Get $get): bool => $get('provide_materials') === true)
                            ->collapsible()
                            ->schema([
                                Repeater::make('training_materials')
                                ->relationship('trainingMaterials')
                                ->schema([
                                    Grid::make(4)->schema([
                                        Forms\Components\Select::make('inventory_item_id')
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
                                        Forms\Components\Textarea::make('usage_notes')
                                        ->label('Usage Notes')
                                        ->placeholder('How will this be used?')
                                        ->rows(2),
                                    ]),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add Material')
                                ->collapsible()
                                ->itemLabel(fn(array $state): ?string =>
                                        InventoryItem::find($state['inventory_item_id'])?->name ?? 'New Material'
                                )
                                ->columnSpanFull(),
                            ]),
                            Forms\Components\Section::make('Additional Information')
                            ->description('Any additional notes or information about the training')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->rows(3)
                                ->placeholder('Add any additional information, special instructions, or notes about this training')
                                ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(),
        ]);
    }

    protected static function mutateFormDataBeforeCreate(array $data): array {
        $data['type'] = 'global_training';
        $data['mentor_id'] = auth()->id();

        // Validate assessment weights if assessments are enabled
        if (($data['assess_participants'] ?? false) && isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }

        // Handle location tags
        if (!empty($data['location_tags'])) {
            $locationIds = [];
            foreach ($data['location_tags'] as $locationName) {
                $location = \App\Models\Location::firstOrCreate(
                        ['name' => $locationName],
                        ['type' => 'other']
                );
                $locationIds[] = $location->id;
            }
            $data['locations'] = array_unique(array_merge($data['locations'] ?? [], $locationIds));
        }

        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array {
        $data['type'] = 'global_training';
        // Only set mentor_id if it's not already set (preserve existing mentor on edit)
        if (empty($data['mentor_id'])) {
            $data['mentor_id'] = auth()->id();
        }

        // Validate assessment weights if assessments are enabled
        if (($data['assess_participants'] ?? false) && isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }

        // Handle location tags
        if (!empty($data['location_tags'])) {
            $locationIds = [];
            foreach ($data['location_tags'] as $locationName) {
                $location = \App\Models\Location::firstOrCreate(
                        ['name' => $locationName],
                        ['type' => 'other']
                );
                $locationIds[] = $location->id;
            }
            $data['locations'] = array_unique(array_merge($data['locations'] ?? [], $locationIds));
        }

        return $data;
    }

    protected function afterCreate(): void {
        // Handle assessment category settings after create
        if ($this->data['assess_participants'] ?? false) {
            $this->handleAssessmentCategories();
        }
    }

    protected function afterSave(): void {
        // Handle assessment category settings after save
        if ($this->data['assess_participants'] ?? false) {
            $this->handleAssessmentCategories();
        }
    }

    private function handleAssessmentCategories(): void {
        $settings = $this->data['assessment_category_settings'] ?? [];

        if (!empty($settings)) {
            $attachData = [];

            foreach ($settings as $setting) {
                if (isset($setting['assessment_category_id'])) {
                    $attachData[$setting['assessment_category_id']] = [
                        'weight_percentage' => $setting['weight_percentage'] ?? 25.0,
                        'pass_threshold' => $setting['pass_threshold'] ?? 70.0,
                        'is_required' => $setting['is_required'] ?? true,
                        'order_sequence' => $setting['order_sequence'] ?? 1,
                        'is_active' => $setting['is_active'] ?? true,
                    ];
                }
            }

            $this->record->assessmentCategories()->sync($attachData);
        }
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(static::getEloquentQuery()->with(['locations', 'programs', 'county', 'division', 'partner', 'mentor', 'assessmentCategories', 'trainingMaterials']))
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
                            ->description(
                                    fn(Training $record): string =>
                                    $record->description ? Str::limit($record->description, 60) : ''
                            ),
                            BadgeColumn::make('lead_type')
                            ->label('Lead Type')
                            ->colors([
                                'primary' => 'national',
                                'success' => 'county',
                                'warning' => 'partner',
                            ])
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'national' => 'National',
                                        'county' => 'County',
                                        'partner' => 'Partner Led',
                                        default => ucfirst($state),
                                    }),
                            TextColumn::make('lead_organization')
                            ->label('Lead Organization')
                            ->getStateUsing(function (Training $record): string {
                                return match ($record->lead_type) {
                                    'national' => $record->division?->name ?? 'Ministry of Health',
                                    'county' => $record->county?->name ?? 'Not specified',
                                    'partner' => $record->partner?->name ?? 'Not specified',
                                    default => 'Not specified',
                                };
                            })
                            ->searchable(['division.name', 'county.name', 'partner.name']),
                            TextColumn::make('programs.name')
                            ->label('Programs')
                            ->badge()
                            ->separator(', ')
                            ->limit(30)
                            ->color('info'),
                            TextColumn::make('mentor.full_name')
                            ->label('Created By')
                            ->searchable(['first_name', 'last_name'])
                            ->description(
                                    fn(Training $record): string =>
                                    $record->mentor?->facility?->name ?? ''
                            ),
                            TextColumn::make('locations.name')
                            ->label('Locations')
                            ->badge()
                            ->separator(', ')
                            ->limit(50)
                            ->tooltip(function (Training $record): ?string {
                                if ($record->locations && $record->locations->isNotEmpty()) {
                                    return $record->locations->pluck('name')->implode(', ');
                                }
                                return $record->location ?: 'No location specified';
                            })
                            ->placeholder(fn(Training $record): string => $record->location ?: 'No location specified')
                            ->formatStateUsing(function (Training $record): string {
                                if ($record->locations && $record->locations->isNotEmpty()) {
                                    return $record->locations->pluck('name')->implode(', ');
                                }
                                return $record->location ?: 'No location specified';
                            }),
                            TextColumn::make('start_date')
                            ->date('M j, Y')
                            ->sortable()
                            ->description(
                                    fn(Training $record): string =>
                                    $record->end_date ? 'to ' . $record->end_date->format('M j, Y') : ''
                            ),
                            BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'new',
                                'warning' => 'repeat',
                                'success' => 'ongoing',
                                'primary' => 'completed',
                                'danger' => 'cancelled',
                            ])
                            ->icons([
                                'heroicon-o-pencil' => 'new',
                                'heroicon-o-clock' => 'repeat',
                                'heroicon-o-play' => 'ongoing',
                                'heroicon-o-check-circle' => 'completed',
                                'heroicon-o-x-circle' => 'cancelled',
                            ]),
                            TextColumn::make('participants_count')
                            ->label('Participants')
                            ->counts('participants')
                            ->sortable()
                            ->badge()
                            ->color('success'),
                            // NEW: Assessment indicators
                            TextColumn::make('assessment_categories_count')
                            ->label('Categories')
                            ->counts('assessmentCategories')
                            ->badge()
                            ->color('warning')
                            ->tooltip('Assessment categories configured'),
                            // NEW: Materials indicator
                            TextColumn::make('training_materials_count')
                            ->label('Materials')
                            ->counts('trainingMaterials')
                            ->badge()
                            ->color('info')
                            ->tooltip('Training materials planned'),
                        ])
                        ->filters([
                            SelectFilter::make('lead_type')
                            ->label('Lead Type')
                            ->options([
                                'national' => 'National',
                                'county' => 'County',
                                'partner' => 'Partner Led',
                            ])
                            ->multiple(),
                            SelectFilter::make('status')
                            ->options([
                                'new' => 'New',
                                'repeat' => 'Repeat',
                                'ongoing' => 'Ongoing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->multiple(),
                            SelectFilter::make('programs')
                            ->relationship('programs', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('division')
                            ->relationship('division', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('county')
                            ->relationship('county', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('partner')
                            ->relationship('partner', 'name')
                            ->multiple()
                            ->preload(),
                            SelectFilter::make('mentor')
                            ->relationship('mentor', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn(User $record): string => $record->full_name)
                            ->multiple()
                            ->preload(),
                            // NEW: Assessment filter
                            Filter::make('has_assessments')
                            ->label('Has Assessments')
                            ->query(fn(Builder $query): Builder => $query->has('assessmentCategories'))
                            ->toggle(),
                            // NEW: Materials filter
                            Filter::make('has_materials')
                            ->label('Has Materials')
                            ->query(fn(Builder $query): Builder => $query->has('trainingMaterials'))
                            ->toggle(),
                            Filter::make('date_range')
                            ->form([
                                DatePicker::make('start_date')
                                ->label('From Date'),
                                DatePicker::make('end_date')
                                ->label('To Date'),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                return $query
                                                ->when(
                                                        $data['start_date'],
                                                        fn(Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                                                )
                                                ->when(
                                                        $data['end_date'],
                                                        fn(Builder $query, $date): Builder => $query->whereDate('end_date', '<=', $date),
                                                );
                            })
                            ->indicateUsing(function (array $data): array {
                                $indicators = [];
                                if ($data['start_date'] ?? null) {
                                    $indicators['start_date'] = 'From ' . Carbon::parse($data['start_date'])->toFormattedDateString();
                                }
                                if ($data['end_date'] ?? null) {
                                    $indicators['end_date'] = 'To ' . Carbon::parse($data['end_date'])->toFormattedDateString();
                                }
                                return $indicators;
                            }),
                        ])
                        ->actions([
                            ActionGroup::make([
                                Tables\Actions\ViewAction::make()
                                ->color('info'),
                                Tables\Actions\EditAction::make()
                                ->color('warning'),
                                Action::make('manage_participants')
                                ->label('Participants')
                                ->icon('heroicon-o-users')
                                ->color('success')
                                ->url(
                                        fn(Training $record): string =>
                                        static::getUrl('participants', ['record' => $record])
                                ),
                                Action::make('manage_assessments')
                                ->label('Assessments')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->color('primary')
                                ->url(
                                        fn(Training $record): string =>
                                        static::getUrl('assessments', ['record' => $record])
                                )
                                ->visible(fn(Training $record): bool => $record->assessmentCategories()->exists()),
                                Action::make('duplicate')
                                ->label('Duplicate')
                                ->icon('heroicon-o-document-duplicate')
                                ->color('gray')
                                ->action(function (Training $record) {
                                    $newTraining = $record->replicate();
                                    $newTraining->title = $record->title . ' (Copy)';
                                    $newTraining->identifier = 'GT-' . strtoupper(Str::random(6));
                                    $newTraining->status = 'new';
                                    $newTraining->type = 'global_training';
                                    $newTraining->mentor_id = auth()->id(); // Set current user as mentor
                                    $newTraining->save();

                                    // Copy relationships
                                    $newTraining->programs()->attach($record->programs->pluck('id'));
                                    $newTraining->modules()->attach($record->modules->pluck('id'));
                                    $newTraining->methodologies()->attach($record->methodologies->pluck('id'));
                                    $newTraining->locations()->attach($record->locations->pluck('id'));

                                    // Copy assessment categories with pivot data
                                    if ($record->assessmentCategories()->exists()) {
                                        $categoryData = [];
                                        foreach ($record->assessmentCategories as $category) {
                                            $categoryData[$category->id] = [
                                                'weight_percentage' => $category->pivot->weight_percentage,
                                                'pass_threshold' => $category->pivot->pass_threshold,
                                                'is_required' => $category->pivot->is_required,
                                                'order_sequence' => $category->pivot->order_sequence,
                                                'is_active' => $category->pivot->is_active,
                                            ];
                                        }
                                        $newTraining->assessmentCategories()->sync($categoryData);
                                    }

                                    return redirect()->to(static::getUrl('edit', ['record' => $newTraining]));
                                }),
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
                                Tables\Actions\BulkAction::make('mark_completed')
                                ->label('Mark as Completed')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(function ($records) {
                                    $records->each(fn(Training $record) => $record->update(['status' => 'completed']));
                                })
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->defaultSort('start_date', 'desc')
                        ->poll('30s')
                        ->striped()
                        ->emptyStateHeading('No MOH Trainings Found')
                        ->emptyStateDescription('Create your first MOH training to get started.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create MOH Training')
                            ->icon('heroicon-o-plus'),
        ]);
    }

    public static function getRelations(): array {
        return [
                // Add relation managers here if needed
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListGlobalTrainings::route('/'),
            'create' => Pages\CreateGlobalTraining::route('/create'),
            'view' => Pages\ViewGlobalTraining::route('/{record}'),
            'edit' => Pages\EditGlobalTraining::route('/{record}/edit'),
            'participants' => Pages\ManageGlobalTrainingParticipants::route('/{record}/participants'),
            'assessments' => Pages\ManageGlobalTrainingAssessments::route('/{record}/assessments'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = static::getModel()::where('type', 'global_training')
                ->where('status', 'completed')
                ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array {
        return ['title', 'identifier', 'description', 'location'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array {
        return [
            'ID' => $record->identifier,
            'Status' => ucfirst($record->status),
            'Location' => $record->location,
            'Start Date' => $record->start_date?->format('M j, Y'),
        ];
    }
}
