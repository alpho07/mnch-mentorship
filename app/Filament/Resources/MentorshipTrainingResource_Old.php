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
use App\Models\Division;
use App\Models\County;
use App\Models\Partner;
use App\Models\Location;
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

class MentorshipTrainingResource_Old extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Mentorships';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'mentorships';
    protected static ?string $recordTitleAttribute = 'title';
    static protected string|null $breadcrumb = 'Mentorships';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'facility_mentor','division']);
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->where('type', 'facility_mentorship')
                        ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form {
        return $form->schema([
                    // Hidden fields
                    Forms\Components\Hidden::make('type')->default('facility_mentorship'),
                    Forms\Components\Hidden::make('mentor_id')->default(auth()->id()),
                            Section::make('Mentorship Information')
                            ->description('Basic details about the mentorship program')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('facility_id')
                                    ->label('Facility')
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
                                    ->helperText(fn(Get $get) =>
                                            $get('facility_assessment_status') === 'required' ? '⚠️ Facility assessment required' : ($get('facility_assessment_status') === 'valid' ? '✅ Facility assessment valid' : 'Select facility to check status')
                                    ),
                                    TextInput::make('title')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Newborn Care Mentorship')
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
                                    ->dehydrated(true)
                                    ->helperText('Auto-generated unique identifier'),
                                    Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'new' => 'New',
                                        'ongoing' => 'Ongoing',
                                        'repeat' => 'Repeat',
                                    ])
                                    ->required()
                                    ->default('new'),
                                ]),
                                Textarea::make('description')
                                ->label('Mentorship Description')
                                ->rows(3)
                                ->placeholder('Describe the mentorship objectives and approach')
                                ->columnSpanFull(),
                                Forms\Components\Hidden::make('facility_assessment_status'),
                            ]),
                            Section::make('Mentorship Leadership')
                            ->description('Select who will coordinate this mentorship')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('lead_type')
                                    ->label('Mentorship Coordinator')
                                    ->options([
                                        'national' => 'National',
                                        'county' => 'County',
                                        'partner' => 'Partner Led',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
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
                                    Select::make('lead_division_id')
                                    ->label('Division')
                                    ->relationship('division', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'national')
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'national'),
                                ]),
                                Grid::make(1)->schema([
                                    Select::make('lead_county_id')
                                    ->label('County')
                                    ->relationship('county', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'county')
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'county'),
                                    Select::make('lead_partner_id')
                                    ->label('Partner Organization')
                                    ->relationship('partner', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(Get $get): bool => $get('lead_type') === 'partner')
                                    ->required(fn(Get $get): bool => $get('lead_type') === 'partner'),
                                ]),
                            ]),
                            Section::make('Schedule & Logistics')
                            ->description('Mentorship dates, location, and capacity')
                            ->schema([
                                Grid::make(3)->schema([
                                    DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->minDate(now())
                                    ->displayFormat('M j, Y'),
                                    DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required()
                                    ->native(false)
                                    ->after('start_date')
                                    ->displayFormat('M j, Y'),
                                    TextInput::make('max_participants')
                                    ->label('Maximum Mentees')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->default(15)
                                    ->suffix('mentees')
                                    ->helperText('Recommended: 10-20 mentees'),
                                ]),
                                Select::make('locations')
                                ->label('Mentorship Locations')
                                ->multiple()
                                ->relationship('locations', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Location Name'),
                                    Select::make('type')
                                    ->options([
                                        'Mentorship_center' => 'Mentorship Center',
                                        'hospital' => 'Hospital',
                                        'conference_hall' => 'Conference Hall',
                                        'hotel' => 'Hotel',
                                        'university' => 'University',
                                        'other' => 'Other',
                                    ])
                                    ->default('Mentorship_center'),
                                    Textarea::make('address')
                                    ->rows(2)
                                    ->label('Address'),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    return Location::create($data)->id;
                                })
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state) {
                                        $locations = Location::whereIn('id', $state)->get();
                                        $locationNames = $locations->pluck('name')->implode(', ');
                                        $set('location', $locationNames);
                                    }
                                })
                                ->helperText('Select or create Mentorship locations')
                                ->columnSpanFull(),
                                TextInput::make('location')
                                ->label('Combined Locations (Auto-filled)')
                                ->disabled()
                                ->dehydrated(true)
                                ->placeholder('Will be auto-filled when you select locations above')
                                ->columnSpanFull(),
                            ]),
                            Section::make('Content & Programs')
                            ->description('Select the programs, modules, and methodologies for this mentorship')
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
                                ->helperText('Select the main programs this mentorship will cover')
                                ->columnSpanFull(),
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
                                ->helperText('Specific modules within the selected programs')
                                ->columnSpanFull(),
                                Select::make('methodologies')
                                ->label('Methodologies')
                                ->multiple()
                                ->relationship('methodologies', 'name')
                                ->preload()
                                ->searchable()
                                ->helperText('Select the Mentorship methods that will be used')
                                ->columnSpanFull(),
                            ]),
                            Section::make('Assessment Framework')
                            ->description('Configure how mentees will be evaluated')
                            ->icon('heroicon-o-clipboard-document-check')
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
                                    /* Actions::make([
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
                                      ])->fullWidth(), */
                            ]),
                            Section::make('Materials')
                            ->description('Select inventory items for the mentorship')
                            ->collapsible()
                            ->schema([
                                Repeater::make('Mentorship_materials')
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
                                ->itemLabel(fn(array $state): ?string =>
                                        InventoryItem::find($state['inventory_item_id'])?->name ?? 'New Material'
                                )
                                ->columnSpanFull(),
                            ]),
                            Section::make('Additional Information')
                            ->description('Any additional notes or information about the mentorship')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Textarea::make('notes')
                                ->label('Notes')
                                ->rows(3)
                                ->placeholder('Add any additional information, special instructions, or notes about this mentorship')
                                ->columnSpanFull(),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(static::getEloquentQuery()->with(['locations', 'programs', 'county', 'division', 'partner', 'mentor', 'facility']))
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
                            BadgeColumn::make('lead_type')
                            ->label('Coordinator Type')
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
                            ->label('Coordinator')
                            ->getStateUsing(function (Training $record): string {
                                return match ($record->lead_type) {
                                    'national' => $record->division?->name ?? 'Ministry of Health',
                                    'county' => $record->county?->name ?? 'Not specified',
                                    'partner' => $record->partner?->name ?? 'Not specified',
                                    default => 'Not specified',
                                };
                            }),
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
                            TextColumn::make('locations.name')
                            ->label('Locations')
                            ->badge()
                            ->separator(', ')
                            ->limit(50)
                            ->formatStateUsing(function (Training $record): string {
                                if ($record->locations && $record->locations->isNotEmpty()) {
                                    return $record->locations->pluck('name')->implode(', ');
                                }
                                return $record->location ?: 'No location specified';
                            }),
                            TextColumn::make('start_date')
                            ->date('M j, Y')
                            ->sortable()
                            ->description(fn(Training $record): string =>
                                    $record->end_date ? 'to ' . $record->end_date->format('M j, Y') : ''
                            ),
                            BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'new',
                                'success' => 'ongoing',
                                'primary' => 'Repeat',
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
                            SelectFilter::make('lead_type')
                            ->label('Coordinator Type')
                            ->options([
                                'national' => 'National',
                                'county' => 'County',
                                'partner' => 'Partner Led',
                            ])
                            ->multiple(),
                            SelectFilter::make('status')
                            ->options([
                                'new' => 'New',
                                'ongoing' => 'Ongoing',
                                'repeat' => 'Repeat',
                            ])
                            ->multiple(),
                            SelectFilter::make('facility')
                            ->relationship('facility', 'name')
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
                                Action::make('duplicate')
                                ->label('Duplicate')
                                ->icon('heroicon-o-document-duplicate')
                                ->color('gray')
                                ->action(function (Training $record) {
                                    $newTraining = $record->replicate();
                                    $newTraining->title = $record->title . ' (Copy)';
                                    $newTraining->identifier = 'MT-' . strtoupper(Str::random(6));
                                    $newTraining->status = 'draft';
                                    $newTraining->type = 'facility_mentorship';
                                    $newTraining->mentor_id = auth()->id();
                                    $newTraining->save();

                                    // Copy relationships
                                    $newTraining->programs()->attach($record->programs->pluck('id'));
                                    $newTraining->modules()->attach($record->modules->pluck('id'));
                                    $newTraining->methodologies()->attach($record->methodologies->pluck('id'));
                                    $newTraining->locations()->attach($record->locations->pluck('id'));

                                    // Copy assessment categories with pivot data
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
                        ->emptyStateHeading('No Mentorship Programs Found')
                        ->emptyStateDescription('Create your first facility-based mentorship Mentorship program.')
                        ->emptyStateIcon('heroicon-o-academic-cap')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create Mentorship Program')
                            ->icon('heroicon-o-plus'),
        ]);
    }

    protected static function mutateFormDataBeforeCreate(array $data): array {
        $data['type'] = 'facility_mentorship';
        $data['mentor_id'] = auth()->id();

        // Check facility assessment
        if (isset($data['facility_id'])) {
            $assessment = FacilityAssessment::where('facility_id', $data['facility_id'])
                    ->where('status', 'approved')
                    ->where('next_assessment_due', '>', now())
                    ->latest()
                    ->first();

            if (!$assessment) {
                throw new \Exception('Facility assessment must be completed and approved before creating mentorship Mentorship.');
            }
        }

        // Validate assessment weights
        if (isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }

        // Handle location tags
        if (!empty($data['location_tags'])) {
            $locationIds = [];
            foreach ($data['location_tags'] as $locationName) {
                $location = Location::firstOrCreate(
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
        $data['type'] = 'facility_mentorship';

        // Only set mentor_id if it's not already set (preserve existing mentor on edit)
        if (empty($data['mentor_id'])) {
            $data['mentor_id'] = auth()->id();
        }

        // Handle location tags
        if (!empty($data['location_tags'])) {
            $locationIds = [];
            foreach ($data['location_tags'] as $locationName) {
                $location = Location::firstOrCreate(
                        ['name' => $locationName],
                        ['type' => 'other']
                );
                $locationIds[] = $location->id;
            }
            $data['locations'] = array_unique(array_merge($data['locations'] ?? [], $locationIds));
        }

        return $data;
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
            'view' => Pages\ViewMentorshipTraining::route('/{record}'),
            'edit' => Pages\EditMentorshipTraining::route('/{record}/edit'),
            'mentees' => Pages\ManageMentorshipMentees::route('/{record}/mentees'),
            'assessments' => Pages\ManageMentorshipAssessments::route('/{record}/assessments'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = static::getModel()::where('type', 'facility_mentorship')
                // ->where('status', 'ongoing')
                ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'warning';
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
            'Facility' => $record->facility?->name,
            'Status' => ucfirst($record->status),
            'Start Date' => $record->start_date?->format('M j, Y'),
            'Mentees' => $record->participants()->count(),
        ];
    }
}
