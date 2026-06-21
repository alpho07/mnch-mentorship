<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentResource\Pages;
use App\Models\Assessment;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssessmentResource1 extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Assessment_01';

    protected static ?string $navigationGroup = 'MNCH Assessment';

    protected static ?int $navigationSort = 1;
    
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make(static::getFormSteps())
                    ->contained(false)
                    ->skippable(false)
            ]);
    }

    protected static function getFormSteps(): array
    {
        $isCreating = request()->routeIs('filament.*.resources.*.create');
        
        $steps = [
            // ==========================================
            // STEP 1: Facility & Assessor Information
            // ==========================================
            Forms\Components\Wizard\Step::make('Facility & Assessor')
                        ->schema([
                            Forms\Components\Section::make('Facility Information')
                                ->schema([
                                    // County Filter (UI only, not saved)
                                    Forms\Components\Select::make('county_filter')
                                        ->label('County')
                                        ->options(function () {
                                            return \App\Models\County::pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set) {
                                            $set('facility_id', null);
                                        })
                                        ->dehydrated(false),

                                    // Facility Selection
                                    Forms\Components\Select::make('facility_id')
                                        ->label('Facility')
                                        ->options(function (Forms\Get $get) {
                                            $countyId = $get('county_filter');
                                            
                                            $query = Facility::query();
                                            
                                            if ($countyId) {
                                                // Filter facilities by county through subcounty relationship
                                                $query->whereHas('subcounty', function ($q) use ($countyId) {
                                                    $q->where('county_id', $countyId);
                                                });
                                            }
                                            
                                            // Format: "MFL-CODE - Facility Name"
                                            return $query->get()->mapWithKeys(function ($facility) {
                                                $label = ($facility->mfl_code ? "{$facility->mfl_code} - " : "") . $facility->name;
                                                return [$facility->id => $label];
                                            });
                                        })
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                                            if ($state) {
                                                $facility = Facility::with(['subcounty.county'])->find($state);
                                                if ($facility) {
                                                    $set('facility_info', [
                                                        'mfl_code' => $facility->mfl_code ?? 'N/A',
                                                        'level' => $facility->level ?? 'N/A',
                                                        'ownership' => $facility->ownership ?? 'N/A',
                                                        'county' => $facility->subcounty->county->name ?? 'N/A',
                                                        'subcounty' => $facility->subcounty->name ?? 'N/A',
                                                        'contact' => $facility->phone ?? 'N/A',
                                                    ]);
                                                }
                                            }
                                        })
                                        ->rules([
                                            function (Forms\Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    $assessmentType = $get('assessment_type') ?? 'baseline';
                                                    $assessmentDate = $get('assessment_date');
                                                    
                                                    if (!$assessmentDate || !$value) {
                                                        return;
                                                    }
                                                    
                                                    // Check for same facility, type, and year
                                                    $year = \Carbon\Carbon::parse($assessmentDate)->year;
                                                    
                                                    $exists = Assessment::where('facility_id', $value)
                                                        ->where('assessment_type', $assessmentType)
                                                        ->whereYear('assessment_date', $year)
                                                        ->exists();
                                                    
                                                    if ($exists) {
                                                        $fail("A {$assessmentType} assessment already exists for this facility in {$year}. Please check existing assessments or choose a different period.");
                                                    }
                                                };
                                            }
                                        ]),

                                    // Facility Info Display (UI only)
                                    Forms\Components\Placeholder::make('facility_info')
                                        ->label('')
                                        ->content(function (Forms\Get $get) {
                                            $info = $get('facility_info');
                                            
                                            if (!$info) {
                                                return 'Select a facility to see details';
                                            }
                                            
                                            return view('filament.components.facility-info', ['info' => $info]);
                                        })
                                        ->dehydrated(false),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Assessment Details')
                                ->schema([
                                    Forms\Components\Select::make('assessment_type')
                                        ->label('Assessment Type')
                                        ->options([
                                            'baseline' => 'Baseline',
                                            'midline' => 'Midline',
                                            'endline' => 'Endline',
                                        ])
                                        ->default('baseline')
                                        ->required(),

                                    Forms\Components\DatePicker::make('assessment_date')
                                        ->label('Assessment Date')
                                        ->default(now())
                                        ->required(),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Assessor Information')
                                ->description('Auto-populated from logged-in user')
                                ->schema([
                                    Forms\Components\TextInput::make('assessor_name')
                                        ->label('Assessor Name')
                                        ->default(auth()->user()->name ?? '')
                                        ->disabled()
                                        ->dehydrated(),

                                    Forms\Components\TextInput::make('assessor_contact')
                                        ->label('Assessor Contact')
                                        ->default(auth()->user()->email ?? auth()->user()->phone ?? '')
                                        ->disabled()
                                        ->dehydrated(),
                                ])
                                ->columns(2),
                        ]),

                    // ==========================================
                    // STEP 2: Infrastructure
                    // ==========================================
                    Forms\Components\Wizard\Step::make('Infrastructure')
                        ->schema(function ($record) {
                            $sectionId = \App\Models\AssessmentSection::where('code', 'infrastructure')->value('id');
                            
                            return [
                                Forms\Components\Section::make('Infrastructure Assessment')
                                    ->description('Physical infrastructure and bed capacity assessment')
                                    ->schema(
                                        \App\Services\DynamicFormBuilder::buildForSection($sectionId, $record?->id)
                                    )
                                    ->columns(1),
                            ];
                        }),

                    // ==========================================
                    // STEP 3: Skills Lab
                    // ==========================================
                    Forms\Components\Wizard\Step::make('Skills Lab')
                        ->schema(function ($record) {
                            $sectionId = \App\Models\AssessmentSection::where('code', 'skills_lab')->value('id');
                            
                            return [
                                Forms\Components\Section::make('Skills Lab Assessment')
                                    ->description('Skills laboratory assessment with conditional questions')
                                    ->schema(
                                        \App\Services\DynamicFormBuilder::buildForSection($sectionId, $record?->id)
                                    )
                                    ->columns(1),
                            ];
                        }),

                    // ==========================================
                    // STEP 4: Human Resources
                    // ==========================================
                    Forms\Components\Wizard\Step::make('Human Resources')
                        ->schema(function ($record) {
                            $cadres = \App\Models\MainCadre::where('is_active', true)
                                ->orderBy('order')
                                ->get();

                            $fields = [];
                            
                            foreach ($cadres as $cadre) {
                                // Get existing response if available
                                $existingResponse = null;
                                if ($record) {
                                    $existingResponse = $record->humanResourceResponses()
                                        ->where('cadre_id', $cadre->id)
                                        ->first();
                                }

                                $fields[] = Forms\Components\Section::make($cadre->name)
                                    ->schema([
                                        Forms\Components\Hidden::make("hr_{$cadre->id}_cadre_id")
                                            ->default($cadre->id),

                                        Forms\Components\Grid::make(6)
                                            ->schema([
                                                Forms\Components\TextInput::make("hr_{$cadre->id}_total_in_facility")
                                                    ->label('Total in Facility')
                                                    ->numeric()
                                                    ->default($existingResponse?->total_in_facility ?? 0)
                                                    ->minValue(0)
                                                    ->required(),

                                                Forms\Components\TextInput::make("hr_{$cadre->id}_etat_plus")
                                                    ->label('ETAT+')
                                                    ->numeric()
                                                    ->default($existingResponse?->etat_plus ?? 0)
                                                    ->minValue(0),

                                                Forms\Components\TextInput::make("hr_{$cadre->id}_comprehensive_newborn_care")
                                                    ->label('Comprehensive Newborn Care')
                                                    ->numeric()
                                                    ->default($existingResponse?->comprehensive_newborn_care ?? 0)
                                                    ->minValue(0),

                                                Forms\Components\TextInput::make("hr_{$cadre->id}_imnci")
                                                    ->label('IMNCI')
                                                    ->numeric()
                                                    ->default($existingResponse?->imnci ?? 0)
                                                    ->minValue(0),

                                                Forms\Components\TextInput::make("hr_{$cadre->id}_type_1_diabetes")
                                                    ->label('Type 1 Diabetes')
                                                    ->numeric()
                                                    ->default($existingResponse?->type_1_diabetes ?? 0)
                                                    ->minValue(0),

                                                Forms\Components\TextInput::make("hr_{$cadre->id}_essential_newborn_care")
                                                    ->label('Essential Newborn Care')
                                                    ->numeric()
                                                    ->default($existingResponse?->essential_newborn_care ?? 0)
                                                    ->minValue(0),
                                            ])
                                            ->columns(6),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false);
                            }

                            return $fields;
                        }),

                    // ==========================================
                    // STEP 5: Health Products
                    // ==========================================
                    Forms\Components\Wizard\Step::make('Health Products')
                        ->schema(function ($record) {
                            $departments = \App\Models\AssessmentDepartment::where('is_active', true)
                                ->orderBy('order')
                                ->get();
                            $categories = \App\Models\CommodityCategory::orderBy('order')->get();

                            if ($departments->isEmpty() || $categories->isEmpty()) {
                                return [
                                    Forms\Components\Placeholder::make('no_data')
                                        ->label('')
                                        ->content('No departments or categories configured. Please run database seeder.')
                                        ->columnSpanFull(),
                                ];
                            }

                            return [
                                Forms\Components\Tabs::make('Departments')
                                    ->tabs(
                                        $departments->map(function ($department) use ($categories, $record) {
                                            return Forms\Components\Tabs\Tab::make($department->name)
                                                ->schema(
                                                    static::buildHealthProductsForDepartment($department, $categories, $record)
                                                );
                                        })->toArray()
                                    )
                                    ->columnSpanFull()
                                    ->contained(false),
                            ];
                        }),

                    // ==========================================
                    // STEP 6: Information Systems
                    // ==========================================
                    Forms\Components\Wizard\Step::make('Information Systems')
                        ->schema(function ($record) {
                            $sectionId = \App\Models\AssessmentSection::where('code', 'information_systems')->value('id');
                            
                            return [
                                Forms\Components\Section::make('Information Systems Questions')
                                    ->description('Data management and reporting systems')
                                    ->schema(
                                        \App\Services\DynamicFormBuilder::buildForSection($sectionId, $record?->id)
                                    )
                                    ->columns(1),
                            ];
                        }),

                    // ==========================================
                    // STEP 7: Quality of Care
                    // ==========================================
                    Forms\Components\Wizard\Step::make('Quality of Care')
                        ->schema(function ($record) {
                            $sectionId = \App\Models\AssessmentSection::where('code', 'quality_of_care')->value('id');
                            
                            return [
                                Forms\Components\Section::make('Quality of Care Questions')
                                    ->description('Clinical quality and audit practices with proportion measurements')
                                    ->schema(
                                        \App\Services\DynamicFormBuilder::buildForSection($sectionId, $record?->id)
                                    )
                                    ->columns(1),
                            ];
                        }),

                ];
        
        // Only return Step 1 if creating
           return $isCreating ? [$steps[0]] : $steps;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('facility.mfl_code')
                    ->label('MFL Code')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('facility.subcounty.county.name')
                    ->label('County')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('assessment_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'baseline',
                        'warning' => 'midline',
                        'success' => 'endline',
                    ]),

                Tables\Columns\TextColumn::make('assessment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('assessor_name')
                    ->label('Assessor')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'info' => 'reviewed',
                        'primary' => 'approved',
                    ]),

                Tables\Columns\TextColumn::make('overall_percentage')
                    ->label('Score %')
                    ->sortable()
                    ->suffix('%')
                    ->color(fn ($state) => match(true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\BadgeColumn::make('overall_grade')
                    ->label('Grade')
                    ->colors([
                        'success' => 'green',
                        'warning' => 'yellow',
                        'danger' => 'red',
                    ]),

                Tables\Columns\TextColumn::make('completion_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->color(fn ($state) => $state === 100 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('facility')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('county')
                    ->label('County')
                    ->options(fn () => \App\Models\County::pluck('name', 'id'))
                    ->query(function ($query, $state) {
                        if ($state['value']) {
                            $query->whereHas('facility.subcounty', function ($q) use ($state) {
                                $q->where('county_id', $state['value']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('assessment_type')
                    ->options([
                        'baseline' => 'Baseline',
                        'midline' => 'Midline',
                        'endline' => 'Endline',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'reviewed' => 'Reviewed',
                        'approved' => 'Approved',
                    ]),

                Tables\Filters\Filter::make('assessment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('assessment_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('assessment_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('view_summary')
                    ->label('Summary')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record])),

                Tables\Actions\Action::make('download_report')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->action(fn ($record) => static::downloadPdfReport($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessments1::route('/'),
            //'create' => Pages\CreateAssessment::route('/create'),
           // 'edit' => Pages\EditAssessment::route('/{record}/edit'),
          //  'view' => Pages\ViewAssessmentSummary::route('/{record}'),
        ];
    }

    /**
     * Build health products form for a department
     */
    protected static function buildHealthProductsForDepartment($department, $categories, $record): array
    {
        $schema = [];

        foreach ($categories as $category) {
            // Get commodities for this category that are applicable to this department
            $commodities = \App\Models\Commodity::where('commodity_category_id', $category->id)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($query) use ($department) {
                    $query->where('assessment_department_id', $department->id);
                })
                ->orderBy('order')
                ->get();

            if ($commodities->isEmpty()) {
                continue;
            }

            // Create section for this category
            $categoryFields = [];

            foreach ($commodities as $commodity) {
                $fieldName = "commodity_{$department->id}_{$commodity->id}";
                
                // Get existing response (only if record exists)
                $existingResponse = null;
                if ($record) {
                    $existingResponse = \App\Models\AssessmentCommodityResponse::where('assessment_id', $record->id)
                        ->where('commodity_id', $commodity->id)
                        ->where('assessment_department_id', $department->id)
                        ->first();
                }

                $categoryFields[] = Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Placeholder::make("{$fieldName}_label")
                            ->label($commodity->name)
                            ->content($commodity->description ?? '')
                            ->columnSpan(1),
                        
                        Forms\Components\ToggleButtons::make($fieldName)
                            ->label('')
                            ->options([
                                1 => 'Available',
                                0 => 'Not Available',
                            ])
                            ->colors([
                                1 => 'success',
                                0 => 'danger',
                            ])
                            ->icons([
                                1 => 'heroicon-o-check-circle',
                                0 => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->default($existingResponse ? ($existingResponse->available ? 1 : 0) : null)
                            ->columnSpan(1),
                    ]);
            }

            $schema[] = Forms\Components\Section::make($category->name)
                ->description("{$commodities->count()} items")
                ->schema($categoryFields)
                ->collapsible()
                ->collapsed(false);
        }

        return $schema;
    }

    /**
     * Download PDF report (placeholder - implement later)
     */
    protected static function downloadPdfReport($record)
    {
        // TODO: Implement PDF generation
        // For now, show notification
        \Filament\Notifications\Notification::make()
            ->title('PDF generation coming soon')
            ->info()
            ->send();
    }
}