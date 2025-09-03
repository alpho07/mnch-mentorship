<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingExportResource\Pages;
use App\Models\Training;
use App\Models\User;
use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Department;
use App\Models\Cadre;
use App\Models\AssessmentCategory;
use App\Exports\TrainingParticipantsExport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;

class TrainingExportResource extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Export Center';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'training-exports';

    public static function form(Form $form): Form {
        return $form->schema([
                            Tabs::make('Export Configuration')
                            ->tabs([
                                // Tab 1: Export Type & Trainings
                                Tabs\Tab::make('1. Select Trainings')
                                ->icon('heroicon-o-academic-cap')
                                ->schema([
                                    Section::make('Export Type')
                                    ->description('Choose what type of data you want to export')
                                    ->schema([
                                        Select::make('export_type')
                                        ->label('What do you want to export?')
                                        ->options([
                                            'training_participants' => 'Training Participants - Export participants from selected trainings',
                                            'participant_trainings' => 'Participant Training History - Export all trainings for selected participants',
                                            'training_summary' => 'Training Summary Report - Overview of selected trainings',
                                        ])
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            // Clear dependent fields when export type changes
                                            $set('selected_trainings', []);
                                            $set('selected_participants', []);
                                        })
                                        ->helperText('Select the type of export you need'),
                                    ]),
                                    // Training Selection (for training_participants and training_summary)
                                    Section::make('Select Trainings')
                                    ->description('Choose which trainings to include in your export')
                                    ->visible(fn(Get $get) => in_array($get('export_type'), ['training_participants', 'training_summary']))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('training_type_filter')
                                            ->label('Training Type')
                                            ->options([
                                                'all' => 'All Training Types',
                                                'global_training' => 'MOH Trainings',
                                                'facility_mentorship' => 'Facility Mentorships',
                                            ])
                                            ->default('all')
                                            ->live(),
                                            Select::make('training_status_filter')
                                            ->label('Training Status')
                                            ->options([
                                                'all' => 'All Statuses',
                                                'ongoing' => 'Ongoing',
                                                'completed' => 'Completed',
                                                'new' => 'New',
                                            ])
                                            ->default('all')
                                            ->live(),
                                        ]),
                                        CheckboxList::make('selected_trainings')
                                        ->label('Available Trainings')
                                        ->options(function (Get $get) {
                                            $query = Training::with(['facility', 'county', 'partner', 'participants']);

                                            if ($get('training_type_filter') !== 'all') {
                                                $query->where('type', $get('training_type_filter'));
                                            }

                                            if ($get('training_status_filter') !== 'all') {
                                                $query->where('status', $get('training_status_filter'));
                                            }

                                            return $query->get()->mapWithKeys(function ($training) {
                                                        $type = $training->type === 'global_training' ? 'MOH ' : 'Mentorship';
                                                        $location = $training->facility?->name ?? $training->county?->name ?? $training->partner?->name ?? 'Various';
                                                        $participants = $training->participants()->count();
                                                        $dates = $training->start_date ? $training->start_date->format('M Y') : 'TBD';

                                                        return [
                                                            $training->id => "{$training->title} [{$type}] • {$location} • {$participants} participants • {$dates}"
                                                        ];
                                                    });
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->gridDirection('row')
                                        ->required(fn(Get $get) => in_array($get('export_type'), ['training_participants', 'training_summary']))
                                        ->helperText('Select one or more trainings. Each training will be a separate worksheet.'),
                                        Placeholder::make('training_selection_help')
                                        ->content(new HtmlString('
                                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                                <div class="flex items-start">
                                                    <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <div>
                                                        <h4 class="text-sm font-medium text-blue-800">Training Selection Tips:</h4>
                                                        <ul class="mt-1 text-sm text-blue-700 list-disc list-inside space-y-1">
                                                            <li>Each selected training becomes a separate worksheet in your Excel file</li>
                                                            <li>Use filters above to narrow down available trainings</li>
                                                            <li>Participant count shows current enrollment</li>
                                                            <li>You can select multiple trainings for comparison</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        ')),
                                    ]),
                                    // Participant Selection (for participant_trainings)
                                    Section::make('Select Participants')
                                    ->description('Choose which participants to get training history for')
                                    ->visible(fn(Get $get) => $get('export_type') === 'participant_trainings')
                                    ->schema([
                                        CheckboxList::make('selected_participants')
                                        ->label('Available Participants')
                                        ->options(function () {
                                            return User::whereHas('trainingParticipations')
                                                            ->with(['facility', 'department', 'cadre', 'trainingParticipations'])
                                                            ->get()
                                                            ->mapWithKeys(function ($user) {
                                                                $name = $user->full_name;
                                                                $facility = $user->facility?->name ?? 'No facility';
                                                                $trainings = $user->trainingParticipations()->count();

                                                                return [
                                                                    $user->id => "{$name} • {$facility} • {$trainings} trainings"
                                                                ];
                                                            });
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->gridDirection('row')
                                        ->required(fn(Get $get) => $get('export_type') === 'participant_trainings')
                                        ->helperText('Select participants to get their complete training history.'),
                                    ]),
                                ]),
                                // Tab 2: Filters & Criteria
                                Tabs\Tab::make('2. Filters & Criteria')
                                ->icon('heroicon-o-funnel')
                                ->schema([
                                    Section::make('Geographic Filters')
                                    ->description('Filter by location and administrative boundaries')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('filter_counties')
                                            ->label('Counties')
                                            ->multiple()
                                            ->options(County::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Leave empty to include all counties'),
                                            Select::make('filter_facilities')
                                            ->label('Facilities')
                                            ->multiple()
                                            ->options(Facility::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Leave empty to include all facilities'),
                                        ]),
                                    ]),
                                    Section::make('Participant Filters')
                                    ->description('Filter participants by their characteristics')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('filter_departments')
                                            ->label('Departments')
                                            ->multiple()
                                            ->options(Department::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),
                                            Select::make('filter_cadres')
                                            ->label('Cadres')
                                            ->multiple()
                                            ->options(Cadre::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),
                                            Select::make('filter_attendance_status')
                                            ->label('Attendance Status')
                                            ->multiple()
                                            ->options([
                                                'registered' => 'Registered',
                                                'attending' => 'Attending',
                                                'completed' => 'Completed',
                                                'dropped' => 'Dropped',
                                            ]),
                                        ]),
                                    ]),
                                    Section::make('Date Range Filters')
                                    ->description('Filter by training or registration dates')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            DatePicker::make('date_from')
                                            ->label('From Date')
                                            ->native(false)
                                            ->helperText('Training start date from'),
                                            DatePicker::make('date_to')
                                            ->label('To Date')
                                            ->native(false)
                                            ->helperText('Training start date to'),
                                        ]),
                                        Grid::make(2)->schema([
                                            DatePicker::make('registration_from')
                                            ->label('Registration From')
                                            ->native(false)
                                            ->helperText('Participant registration date from'),
                                            DatePicker::make('registration_to')
                                            ->label('Registration To')
                                            ->native(false)
                                            ->helperText('Participant registration date to'),
                                        ]),
                                    ]),
                                ]),
                                // Tab 3: Fields & Columns
                                Tabs\Tab::make('3. Data Fields')
                                ->icon('heroicon-o-table-cells')
                                ->schema([
                                    Section::make('Participant Information')
                                    ->description('Select which participant details to include')
                                    ->schema([
                                        CheckboxList::make('participant_fields')
                                        ->label('Participant Fields')
                                        ->options([
                                                // Default required fields (always needed)
                                                'mentee_name' => "Participant/Mentee's Name ⭐",
                                                'county' => 'County ⭐',
                                                'facility_name' => 'Health Facility Name ⭐',
                                                'facility_type' => 'Facility Type (Level of care) ⭐',
                                                'department' => 'Department ⭐',
                                                'cadre' => 'Cadre ⭐',
                                                'mobile_number' => 'Mobile Number ⭐',
                                                'gender' => 'Gender ⭐',
                                                'subcounty'  => 'Subcounty ⭐',
                                                'facility_mfl_code'  => 'MFL Code ⭐',
                                                'training_level'  => 'Training Level ⭐',
                                                'month'  => 'Month ⭐',
                                                'provider'  => 'Provider ⭐',
                                                'year'  => 'Year ⭐',
                                                'tot'  => 'Trainer of Trainers-TOT (YES/NO) ⭐',
                                                'trained_by'  => 'Trained By ⭐',
                                                'training_location'  => 'Training Location ⭐',
                                                'outcome'  => 'Outcome (Pass or Fail) ⭐',
                                                // Additional optional fields
                                                'email'  => 'Email Address',
                                                'id_number'  => 'ID Number',
                                                'role'  => 'Role/Position',
                                                'registration_date'  => 'Registration Date',
                                                'attendance_status'  => 'Attendance Status',
                                                'completion_status'  => 'Completion Status',
                                                'completion_date'  => 'Completion Date',
                                                'certificate_issued'  => 'Certificate Issued',
                                        ])
                                        ->default([
                                            // Default selected fields (the required ones)
                                            'mentee_name', 'county', 'facility_name', 'facility_type',
                                            'department', 'cadre', 'mobile_number', 'gender','subcounty','facility_mfl_code','training_level',
                                            'month', 'provider', 'year', 'tot', 'trained_by',
                                            'training_location', 'outcome',
                                                // Plus some commonly needed optional ones
                                        ])
                                        ->descriptions([
                                            'mentee_name' => 'Full name of the participant (Required)',
                                            'county' => 'County where participant works (Required)',
                                            'facility_name' => 'Health facility name (Required)',
                                            'facility_type' => 'Level of care (Level 2-6) (Required)',
                                            'department' => 'Department/Unit (Required)',
                                            'cadre' => 'Professional cadre/category (Required)',
                                            'mobile_number' => 'Contact phone number (Required)',
                                            'training_level' => 'Basic/Intermediate/Advanced (Required)',
                                            'month' => 'Training month (Required)',
                                            'provider' => 'Training provider organization (Required)',
                                            'year' => 'Training year (Required)',
                                            'tot' => 'Is this Trainer of Trainers? (Required)',
                                            'trained_by' => 'Name of trainer/facilitator (Required)',
                                            'training_location' => 'Where training was conducted (Required)',
                                            'outcome' => 'Pass/Fail/Pending result (Required)',
                                            'email' => 'Email address if available',
                                            'id_number' => 'National ID number',
                                            'gender' => 'Male/Female/Other',
                                            'subcounty' => 'Subcounty location',
                                            'facility_mfl_code' => 'Master Facility List code',
                                            'role' => 'Job title or position',
                                            'registration_date' => 'When participant registered',
                                            'attendance_status' => 'Registration/Attending/Completed status',
                                            'completion_status' => 'Training completion status',
                                            'completion_date' => 'Date training was completed',
                                            'certificate_issued' => 'Whether certificate was issued',
                                        ])
                                        ->bulkToggleable()
                                        ->columns(2)
                                        ->gridDirection('row')
                                        ->helperText('⭐ Fields marked with star are required by default. Uncheck to exclude from export.'),
                                    ]),
                                    Section::make('Training Information')
                                    ->description('Select which training details to include')
                                    ->schema([
                                        CheckboxList::make('training_fields')
                                        ->label('Training Fields')
                                        ->options([
                                            'training_title' => 'Training Title',
                                            'training_identifier' => 'Training ID',
                                            'training_type' => 'Training Type (MOH/Mentorship)',
                                            'training_status' => 'Training Status',
                                            'lead_type' => 'Lead Type (National/County/Partner)',
                                            'lead_organization' => 'Lead Organization',
                                            'programs' => 'Programs',
                                            'modules' => 'Modules',
                                            'methodologies' => 'Methodologies',
                                            'start_date' => 'Start Date',
                                            'end_date' => 'End Date',
                                            'duration_days' => 'Duration (Days)',
                                            'location' => 'Training Location',
                                            'max_participants' => 'Maximum Participants',
                                            'total_participants' => 'Total Enrolled',
                                        ])
                                        ->default([
                                            'training_title', 'training_type', 'lead_organization',
                                            'programs', 'start_date', 'end_date', 'location'
                                        ])
                                        ->bulkToggleable()
                                        ->columns(2)
                                        ->gridDirection('row'),
                                    ]),
                                    Section::make('Assessment Information')
                                    ->description('Select which assessment details to include')
                                    ->schema([
                                        Toggle::make('include_assessments')
                                        ->label('Include Assessment Results')
                                        ->default(true)
                                        ->live(),
                                        CheckboxList::make('assessment_fields')
                                        ->label('Assessment Fields')
                                        ->options([
                                            'overall_score' => 'Overall Assessment Score',
                                            'overall_status' => 'Overall Assessment Status (PASSED/FAILED)',
                                            'assessment_progress' => 'Assessment Progress (%)',
                                            'individual_categories' => 'Individual Category Results',
                                            'assessment_date' => 'Assessment Date',
                                            'assessor_name' => 'Assessor Name',
                                            'feedback' => 'Assessment Feedback',
                                        ])
                                        ->default([
                                            'overall_score', 'overall_status', 'assessment_progress',
                                            'individual_categories'
                                        ])
                                        ->visible(fn(Get $get) => $get('include_assessments'))
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->gridDirection('row'),
                                    ]),
                                ]),
                                // Tab 4: Export Options
                                Tabs\Tab::make('4. Export Options')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([
                                    Section::make('File Format & Structure')
                                    ->description('Configure how your export file will be organized')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('file_format')
                                            ->label('File Format')
                                            ->options([
                                                'xlsx' => 'Excel (.xlsx) - Recommended',
                                                'csv' => 'CSV (.csv) - Single file',
                                            ])
                                            ->default('xlsx')
                                            ->required(),
                                            Select::make('worksheet_structure')
                                            ->label('Worksheet Structure')
                                            ->options([
                                                'per_training' => 'One worksheet per training',
                                                'combined' => 'All data in one worksheet',
                                                'summary_and_detail' => 'Summary + Detail worksheets',
                                            ])
                                            ->default('per_training')
                                            ->visible(fn(Get $get) => $get('file_format') === 'xlsx')
                                            ->required(),
                                        ]),
                                    ]),
                                    Section::make('Additional Options')
                                    ->schema([
                                        Toggle::make('include_summary_sheet')
                                        ->label('Include Summary Dashboard')
                                        ->default(true)
                                        ->helperText('Add a summary worksheet with statistics and counts'),
                                        Toggle::make('include_charts')
                                        ->label('Include Charts & Graphs')
                                        ->default(false)
                                        ->helperText('Add visual charts (increases file size)'),
                                        Toggle::make('format_for_printing')
                                        ->label('Format for Printing')
                                        ->default(true)
                                        ->helperText('Optimize layout and formatting for printing'),
                                    ]),
                                    Section::make('Preview & Validation')
                                    ->schema([
                                        Placeholder::make('export_preview')
                                        ->content(function (Get $get): HtmlString {
                                            $exportType = $get('export_type');
                                            $selectedTrainings = $get('selected_trainings') ?? [];
                                            $selectedParticipants = $get('selected_participants') ?? [];

                                            if (!$exportType) {
                                                return new HtmlString('<div class="text-gray-500">Select an export type to see preview</div>');
                                            }

                                            $preview = '<div class="bg-green-50 border border-green-200 rounded-lg p-4">';
                                            $preview .= '<h4 class="font-medium text-green-800 mb-2">📊 Export Preview</h4>';

                                            if ($exportType === 'training_participants' && !empty($selectedTrainings)) {
                                                $trainingCount = count($selectedTrainings);
                                                $participantCount = Training::whereIn('id', $selectedTrainings)
                                                                ->withCount('participants')->get()->sum('participants_count');

                                                $preview .= '<ul class="text-sm text-green-700 space-y-1">';
                                                $preview .= "<li>• Export Type: Training Participants</li>";
                                                $preview .= "<li>• Selected Trainings: {$trainingCount}</li>";
                                                $preview .= "<li>• Total Participants: {$participantCount}</li>";
                                                $preview .= "<li>• Worksheets: {$trainingCount} (one per training)</li>";
                                                $preview .= '</ul>';
                                            } elseif ($exportType === 'participant_trainings' && !empty($selectedParticipants)) {
                                                $participantCount = count($selectedParticipants);
                                                $preview .= '<ul class="text-sm text-green-700 space-y-1">';
                                                $preview .= "<li>• Export Type: Participant Training History</li>";
                                                $preview .= "<li>• Selected Participants: {$participantCount}</li>";
                                                $preview .= "<li>• Worksheets: {$participantCount} (one per participant)</li>";
                                                $preview .= '</ul>';
                                            } else {
                                                $preview .= '<p class="text-sm text-green-700">Complete your selections above to see detailed preview</p>';
                                            }

                                            $preview .= '</div>';
                                            return new HtmlString($preview);
                                        })
                                        ->columnSpanFull(),
                                    ]),
                                ]),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        // This resource is primarily for exports, not displaying records
        // The table will be overridden by the custom dashboard view
        return $table
                        ->query(Training::query()->whereNull('id')) // Empty query
                        ->columns([
                                // No columns needed as we're using custom dashboard
                        ])
                        ->emptyStateHeading('Training Export Center')
                        ->emptyStateDescription('Use the "Configure New Export" button to create custom training data exports with flexible filters and field selection.')
                        ->emptyStateIcon('heroicon-o-document-arrow-down')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Configure New Export')
                            ->icon('heroicon-o-plus')
                            ->color('primary'),
                        ])
                        ->paginated(false);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListTrainingExports::route('/'),
            'create' => Pages\CreateTrainingExport::route('/create'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        // Show count of trainings available for export
        $count = Training::whereHas('participants')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'info';
    }

    // No need for edit/view pages as this is export-focused
    public static function canEdit($record): bool {
        return false;
    }

    public static function canView($record): bool {
        return false;
    }

    public static function canDelete($record): bool {
        return false;
    }
}
