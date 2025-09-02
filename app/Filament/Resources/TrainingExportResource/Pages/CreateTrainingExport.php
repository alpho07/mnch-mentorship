<?php

// Enhanced CreateTrainingExport.php with Excel support and all required fields

namespace App\Filament\Resources\TrainingExportResource\Pages;

use App\Filament\Resources\TrainingExportResource;
use App\Models\Training;
use App\Models\User;
use App\Models\TrainingParticipant;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CreateTrainingExport extends CreateRecord {

    protected static string $resource = TrainingExportResource::class;
    protected static bool $canCreateAnother = false;

    public function getTitle(): string {
        return 'Configure Training Export';
    }

    protected function getFormActions(): array {
        return [
            Actions\Action::make('export')
                ->label('Generate & Download Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->size('lg')
                ->action(function () {
                    return $this->generateExport();
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn(): string => static::getResource()::getUrl('index')),
        ];
    }

    protected function generateExport() {
        try {
            $data = $this->form->getState();

            // Basic validation
            if (empty($data['export_type'])) {
                throw new \Exception('Please select an export type');
            }

            if ($data['export_type'] === 'training_participants' && empty($data['selected_trainings'])) {
                throw new \Exception('Please select at least one training');
            }

            if ($data['export_type'] === 'participant_trainings' && empty($data['selected_participants'])) {
                throw new \Exception('Please select at least one participant');
            }

            // Generate timestamp for filename
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $exportType = Str::slug($data['export_type']);

            // Determine file format
            $fileFormat = $data['file_format'] ?? 'xlsx';

            if ($fileFormat === 'xlsx') {
                // Use proper .xlsx extension
                $filename = "training_export_{$exportType}_{$timestamp}.xlsx";
                $this->generateExcelWithSheetJS($data, $filename);
            } else {
                // For CSV
                $filename = "training_export_{$exportType}_{$timestamp}.csv";
                $csvContent = $this->createCsvContent($data);
                $this->downloadCsvViaJavascript($csvContent, $filename);
            }

            Notification::make()
                ->title('Export Complete')
                ->body("Your file has been downloaded as {$filename}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function generateExcelWithSheetJS(array $data, string $filename): void {
        // Create workbook structure
        $workbook = [
            'filename' => $filename,
            'worksheets' => []
        ];

        // Add summary sheet
        if ($data['include_summary_sheet'] ?? true) {
            $workbook['worksheets'][] = $this->createSummaryWorksheet($data);
        }

        // Add data worksheets based on export type
        switch ($data['export_type']) {
            case 'training_participants':
                $trainings = Training::with([
                    'facility', 'county', 'partner', 'division', 'programs',
                    'participants.user.facility.subcounty.county',
                    'participants.user.department',
                    'participants.user.cadre',
                    'participants.assessmentResults',
                    'assessmentCategories'
                ])->whereIn('id', $data['selected_trainings'])->get();

                foreach ($trainings as $training) {
                    $workbook['worksheets'][] = $this->createTrainingWorksheet($training, $data);
                }
                break;

            case 'participant_trainings':
                $participants = User::with([
                    'facility.subcounty.county', 'department', 'cadre',
                    'trainingParticipations.training.programs'
                ])->whereIn('id', $data['selected_participants'])->get();

                foreach ($participants as $participant) {
                    $workbook['worksheets'][] = $this->createParticipantHistoryWorksheet($participant, $data);
                }
                break;

            case 'training_summary':
                $workbook['worksheets'][] = $this->createTrainingSummaryWorksheet($data);
                break;
        }

        // Use SheetJS to create proper Excel file
        $this->downloadExcelWithSheetJS($workbook);
    }

    protected function downloadExcelWithSheetJS(array $workbook): void {
        // Convert workbook data to JSON for JavaScript
        $workbookJson = json_encode($workbook);
        
        $this->js("
            (function() {
                // Load SheetJS if not already loaded
                if (typeof XLSX === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                    script.onload = function() {
                        generateExcel();
                    };
                    document.head.appendChild(script);
                } else {
                    generateExcel();
                }
                
                function generateExcel() {
                    const workbookData = {$workbookJson};
                    
                    // Create new workbook
                    const wb = XLSX.utils.book_new();
                    
                    // Add each worksheet
                    workbookData.worksheets.forEach(function(worksheet) {
                        // Convert data array to worksheet
                        const ws = XLSX.utils.aoa_to_sheet(worksheet.data);
                        
                        // Set column widths
                        const colWidths = [];
                        if (worksheet.data.length > 0) {
                            for (let i = 0; i < worksheet.data[0].length; i++) {
                                colWidths.push({ width: 20 });
                            }
                            ws['!cols'] = colWidths;
                        }
                        
                        // Add worksheet to workbook
                        XLSX.utils.book_append_sheet(wb, ws, worksheet.name);
                    });
                    
                    // Write and download
                    XLSX.writeFile(wb, workbookData.filename);
                }
            })();
        ");
    }

    protected function createSummaryWorksheet(array $data): array {
        $summary = [];

        // Title row
        $summary[] = ['Training Data Export Summary', '', '', ''];
        $summary[] = ['Generated: ' . Carbon::now()->format('F j, Y \a\t g:i A'), '', '', ''];
        $summary[] = ['Export Type: ' . ucwords(str_replace('_', ' ', $data['export_type'])), '', '', ''];
        $summary[] = ['', '', '', '']; // Empty row

        // Statistics
        if ($data['export_type'] === 'training_participants' && !empty($data['selected_trainings'])) {
            $trainings = Training::with('participants')->whereIn('id', $data['selected_trainings'])->get();

            $summary[] = ['OVERVIEW', '', '', ''];
            $summary[] = ['Metric', 'Value', 'Details', ''];
            $summary[] = ['Total Trainings', $trainings->count(), '', ''];
            $summary[] = ['MOH Global Trainings', $trainings->where('type', 'global_training')->count(), '', ''];
            $summary[] = ['Facility Mentorships', $trainings->where('type', 'facility_mentorship')->count(), '', ''];

            $totalParticipants = $trainings->sum(fn($t) => $t->participants->count());
            $completedParticipants = $trainings->sum(fn($t) => $t->participants->where('completion_status', 'completed')->count());

            $summary[] = ['Total Participants', $totalParticipants, '', ''];
            $summary[] = ['Completed Participants', $completedParticipants,
                $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) . '%' : '0%', ''];
        }

        return [
            'name' => 'Summary Dashboard',
            'data' => $summary
        ];
    }

    protected function createTrainingWorksheet(Training $training, array $data): array {
        $worksheetData = [];

        // Training header
        $worksheetData[] = ['TRAINING: ' . $training->title, '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['Type: ' . ($training->type === 'global_training' ? 'MOH Global Training' : 'Facility Mentorship'), '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['Programs: ' . $training->programs->pluck('name')->implode(', '), '', '', '', '', '', '', '', '', '', '', '', ''];

        if ($training->start_date) {
            $dateRange = $training->start_date->format('M j, Y');
            if ($training->end_date) {
                $dateRange .= ' to ' . $training->end_date->format('M j, Y');
            }
            $worksheetData[] = ['Dates: ' . $dateRange, '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        $worksheetData[] = ['', '', '', '', '', '', '', '', '', '', '', '', '']; // Empty row

        // Headers - Complete field list
        $headers = $this->getCompleteHeaders($data);
        $worksheetData[] = $headers;

        // Get participants with filters
        $participants = $this->getFilteredTrainingParticipants($training, $data);

        // Data rows
        foreach ($participants as $participant) {
            $worksheetData[] = $this->formatCompleteParticipantRow($participant, $training, $data, count($headers));
        }

        return [
            'name' => $this->sanitizeSheetName($training->title),
            'data' => $worksheetData
        ];
    }

    protected function createParticipantHistoryWorksheet(User $participant, array $data): array {
        $worksheetData = [];

        // Participant header
        $worksheetData[] = ['PARTICIPANT: ' . $participant->full_name, '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['Facility: ' . ($participant->facility?->name ?? 'Not specified'), '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['County: ' . ($participant->facility?->subcounty?->county?->name ?? 'Not specified'), '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['', '', '', '', '', '', '', '', '', '', '', '', '']; // Empty row

        // Headers
        $headers = [
            'Training Title', 'Training Type', 'Programs', 'Start Date', 'End Date',
            'Training Location', 'Registration Date', 'Attendance Status',
            'Completion Status', 'Outcome', 'Training Level', 'Trained By',
            'Provider', 'Month', 'Year', 'TOT'
        ];
        $worksheetData[] = $headers;

        // Training history
        $trainings = $participant->trainingParticipations()->with([
            'training.programs', 'training.locations', 'training.facility', 'training.county',
            'assessmentResults'
        ])->get();

        foreach ($trainings as $participation) {
            $training = $participation->training;
            $outcome = $this->calculateParticipantOutcome($participation);
            $isTot = $this->isTrainerOfTrainers($participation, $training);

            $row = [
                $training->title,
                $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
                $training->programs->pluck('name')->implode('; '),
                $training->start_date?->format('Y-m-d') ?? '',
                $training->end_date?->format('Y-m-d') ?? '',
                $this->getTrainingLocation($training),
                $participation->registration_date?->format('Y-m-d') ?? '',
                ucfirst($participation->attendance_status),
                ucfirst($participation->completion_status),
                $outcome,
                $this->determineTrainingLevel($training),
                $training->mentor?->full_name ?? 'Not specified',
                $this->getTrainingProvider($training),
                $training->start_date?->format('F') ?? '',
                $training->start_date?->format('Y') ?? '',
                $isTot ? 'YES' : 'NO'
            ];

            $worksheetData[] = $row;
        }

        return [
            'name' => $this->sanitizeSheetName($participant->full_name),
            'data' => $worksheetData
        ];
    }

    protected function createTrainingSummaryWorksheet(array $data): array {
        $worksheetData = [];

        $worksheetData[] = ['TRAINING SUMMARY REPORT', '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['Generated: ' . Carbon::now()->format('F j, Y g:i A'), '', '', '', '', '', '', '', '', '', '', '', ''];
        $worksheetData[] = ['', '', '', '', '', '', '', '', '', '', '', '', '']; // Empty row

        // Headers
        $headers = [
            'Training Name', 'Training Type', 'Lead Organization', 'Programs',
            'Start Date', 'End Date', 'Training Location', 'Max Participants',
            'Total Enrolled', 'Completed', 'Completion Rate', 'Pass Rate', 'Training Level'
        ];
        $worksheetData[] = $headers;

        $trainings = Training::with(['participants', 'programs', 'county', 'partner', 'division'])
            ->whereIn('id', $data['selected_trainings'])
            ->get();

        foreach ($trainings as $training) {
            $totalParticipants = $training->participants->count();
            $completedParticipants = $training->participants->where('completion_status', 'completed')->count();
            $passedParticipants = $this->countPassedParticipants($training);

            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;
            $passRate = $completedParticipants > 0 ? round(($passedParticipants / $completedParticipants) * 100, 1) : 0;

            $row = [
                $training->title,
                $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
                $this->getLeadOrganization($training),
                $training->programs->pluck('name')->implode('; '),
                $training->start_date?->format('Y-m-d') ?? '',
                $training->end_date?->format('Y-m-d') ?? '',
                $this->getTrainingLocation($training),
                $training->max_participants ?? '',
                $totalParticipants,
                $completedParticipants,
                $completionRate . '%',
                $passRate . '%',
                $this->determineTrainingLevel($training)
            ];

            $worksheetData[] = $row;
        }

        return [
            'name' => 'Training Summary',
            'data' => $worksheetData
        ];
    }

    protected function getCompleteHeaders(array $data): array {
        // Default required fields (always included)
        $defaultFields = [
            'mentee_name' => "Participant/Mentee's Name",
            'county' => 'County',
            'facility_name' => 'Health Facility Name',
            'facility_type' => 'Facility Type (Level of care)',
            'department' => 'Department',
            'cadre' => 'Cadre',
            'mobile_number' => 'Mobile Number',
            'training_level' => 'Training Level',
            'month' => 'Month',
            'provider' => 'Provider',
            'year' => 'Year',
            'tot' => 'Trainer of Trainers-TOT (YES/NO)',
            'trained_by' => 'Trained By',
            'training_location' => 'Training Location',
            'outcome' => 'Outcome (Pass or Fail)'
        ];

        // Additional optional fields
        $optionalFields = [
            'email' => 'Email Address',
            'id_number' => 'ID Number',
            'gender' => 'Gender',
            'subcounty' => 'Subcounty',
            'facility_mfl_code' => 'MFL Code',
            'role' => 'Role/Position',
            'training_title' => 'Training Title',
            'training_type' => 'Training Type',
            'programs' => 'Programs/Modules',
            'registration_date' => 'Registration Date',
            'start_date' => 'Training Start Date',
            'end_date' => 'Training End Date',
            'duration_days' => 'Duration (Days)',
            'attendance_status' => 'Attendance Status',
            'completion_status' => 'Completion Status',
            'completion_date' => 'Completion Date',
            'certificate_issued' => 'Certificate Issued',
            'overall_score' => 'Overall Score (%)',
            'assessment_date' => 'Assessment Date',
            'assessor_name' => 'Assessor Name'
        ];

        // Get selected fields from form
        $participantFields = $data['participant_fields'] ?? array_keys($defaultFields);
        $trainingFields = $data['training_fields'] ?? [];
        $assessmentFields = $data['assessment_fields'] ?? [];
        $includeAssessments = $data['include_assessments'] ?? false;

        // Build headers array
        $headers = [];

        // Always include default fields first
        foreach ($defaultFields as $key => $label) {
            $headers[] = $label;
        }

        // Add selected optional fields
        $allOptionalFields = array_merge($participantFields, $trainingFields);
        if ($includeAssessments) {
            $allOptionalFields = array_merge($allOptionalFields, $assessmentFields);
        }

        foreach ($allOptionalFields as $field) {
            if (isset($optionalFields[$field]) && !in_array($optionalFields[$field], $headers)) {
                $headers[] = $optionalFields[$field];
            }
        }

        return $headers;
    }

    protected function formatCompleteParticipantRow($participant, Training $training, array $data, int $expectedColumns = null): array {
        $user = $participant->user;
        $outcome = $this->calculateParticipantOutcome($participant);
        $isTot = $this->isTrainerOfTrainers($participant, $training);

        // Default required fields (always first 15 columns)
        $row = [
            $user->full_name,                                                    // 1
            $user->facility?->subcounty?->county?->name ?? '',                  // 2
            $user->facility?->name ?? '',                                       // 3
            $user->facility?->facilityType?->name ?? $this->determineFacilityLevel($user->facility), // 4
            $user->department?->name ?? '',                                     // 5
            $user->cadre?->name ?? '',                                          // 6
            $user->phone ?? '',                                                 // 7
            $this->determineTrainingLevel($training),                          // 8
            $training->start_date?->format('F') ?? '',                         // 9
            $this->getTrainingProvider($training),                             // 10
            $training->start_date?->format('Y') ?? '',                         // 11
            $isTot ? 'YES' : 'NO',                                             // 12
            $training->mentor?->full_name ?? 'Not specified',                  // 13
            $this->getTrainingLocation($training),                             // 14
            $outcome                                                           // 15
        ];

        // Add optional fields based on selection
        $participantFields = $data['participant_fields'] ?? [];
        $trainingFields = $data['training_fields'] ?? [];
        $assessmentFields = $data['assessment_fields'] ?? [];
        $includeAssessments = $data['include_assessments'] ?? false;

        // Optional participant fields
        if (in_array('email', $participantFields)) {
            $row[] = $user->email ?? '';
        }
        if (in_array('id_number', $participantFields)) {
            $row[] = $user->id_number ?? '';
        }
        if (in_array('gender', $participantFields)) {
            $row[] = $user->gender ?? '';
        }
        if (in_array('subcounty', $participantFields)) {
            $row[] = $user->facility?->subcounty?->name ?? '';
        }
        if (in_array('facility_mfl_code', $participantFields)) {
            $row[] = $user->facility?->mfl_code ?? '';
        }
        if (in_array('role', $participantFields)) {
            $row[] = $user->role ?? '';
        }

        // Optional training fields
        if (in_array('training_title', $trainingFields)) {
            $row[] = $training->title;
        }
        if (in_array('training_type', $trainingFields)) {
            $row[] = $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship';
        }
        if (in_array('programs', $trainingFields)) {
            $row[] = $training->programs->pluck('name')->implode('; ');
        }
        if (in_array('registration_date', $trainingFields)) {
            $row[] = $participant->registration_date?->format('Y-m-d') ?? '';
        }
        if (in_array('start_date', $trainingFields)) {
            $row[] = $training->start_date?->format('Y-m-d') ?? '';
        }
        if (in_array('end_date', $trainingFields)) {
            $row[] = $training->end_date?->format('Y-m-d') ?? '';
        }
        if (in_array('duration_days', $trainingFields)) {
            $duration = '';
            if ($training->start_date && $training->end_date) {
                $duration = $training->start_date->diffInDays($training->end_date) + 1;
            }
            $row[] = $duration;
        }
        if (in_array('attendance_status', $trainingFields)) {
            $row[] = ucfirst($participant->attendance_status);
        }
        if (in_array('completion_status', $trainingFields)) {
            $row[] = ucfirst($participant->completion_status);
        }
        if (in_array('completion_date', $trainingFields)) {
            $row[] = $participant->completion_date?->format('Y-m-d') ?? '';
        }
        if (in_array('certificate_issued', $trainingFields)) {
            $row[] = $participant->certificate_issued ? 'YES' : 'NO';
        }

        // Optional assessment fields
        if ($includeAssessments) {
            if (in_array('overall_score', $assessmentFields)) {
                $row[] = $this->getOverallScore($participant, $training);
            }
            if (in_array('assessment_date', $assessmentFields)) {
                $row[] = $this->getLatestAssessmentDate($participant);
            }
            if (in_array('assessor_name', $assessmentFields)) {
                $row[] = $this->getAssessorName($participant);
            }
        }

        // Ensure row matches expected column count if provided
        if ($expectedColumns !== null) {
            while (count($row) < $expectedColumns) {
                $row[] = '';
            }
            $row = array_slice($row, 0, $expectedColumns);
        }

        return $row;
    }

    // Helper methods for data extraction
    protected function getFilteredTrainingParticipants(Training $training, array $data) {
        $query = $training->participants()->with([
            'user.facility.subcounty.county',
            'user.department',
            'user.cadre',
            'assessmentResults'
        ]);

        // Apply filters
        if (!empty($data['filter_departments'])) {
            $query->whereHas('user', fn($q) => $q->whereIn('department_id', $data['filter_departments']));
        }
        if (!empty($data['filter_cadres'])) {
            $query->whereHas('user', fn($q) => $q->whereIn('cadre_id', $data['filter_cadres']));
        }
        if (!empty($data['filter_attendance_status'])) {
            $query->whereIn('attendance_status', $data['filter_attendance_status']);
        }

        return $query->get();
    }

    protected function calculateParticipantOutcome($participant): string {
        // Check if training has assessments
        if (!method_exists($participant->training, 'hasAssessments') || !$participant->training->hasAssessments()) {
            // For trainings without formal assessments, base on completion status
            return match ($participant->completion_status) {
                'completed' => 'Pass',
                'dropped' => 'Fail',
                default => 'Pending'
            };
        }

        // Use training's assessment calculation if available
        if (method_exists($participant->training, 'calculateOverallScore')) {
            $calculation = $participant->training->calculateOverallScore($participant);
            return match ($calculation['status']) {
                'PASSED' => 'Pass',
                'FAILED' => 'Fail',
                default => 'Pending'
            };
        }

        // Fallback: check if any assessment results exist
        $hasPassingResults = $participant->assessmentResults()
            ->where('result', 'pass')
            ->exists();

        if ($hasPassingResults) {
            return 'Pass';
        }

        $hasFailingResults = $participant->assessmentResults()
            ->where('result', 'fail')
            ->exists();

        if ($hasFailingResults) {
            return 'Fail';
        }

        return 'Pending';
    }

    protected function isTrainerOfTrainers($participant, Training $training): bool {
        // Check if this is a TOT training based on title or programs
        $title = strtolower($training->title);
        $programs = $training->programs->pluck('name')->map(fn($name) => strtolower($name))->implode(' ');

        $totKeywords = ['trainer of trainers', 'tot', 'master trainer', 'training of trainers'];

        foreach ($totKeywords as $keyword) {
            if (strpos($title, $keyword) !== false || strpos($programs, $keyword) !== false) {
                return true;
            }
        }

        // Check if user has a role indicating trainer status
        $user = $participant->user ?? $participant;
        $userRole = strtolower($user->role ?? '');
        if (strpos($userRole, 'trainer') !== false || strpos($userRole, 'mentor') !== false) {
            return true;
        }

        return false;
    }

    protected function determineTrainingLevel(Training $training): string {
        // Logic to determine training level based on training characteristics
        $title = strtolower($training->title);
        $programs = $training->programs->pluck('name')->map(fn($name) => strtolower($name))->implode(' ');

        // Check for advanced/master level indicators
        $advancedKeywords = ['advanced', 'master', 'expert', 'specialist', 'trainer of trainers', 'tot'];
        foreach ($advancedKeywords as $keyword) {
            if (strpos($title, $keyword) !== false || strpos($programs, $keyword) !== false) {
                return 'Advanced';
            }
        }

        // Check for intermediate level indicators
        $intermediateKeywords = ['intermediate', 'refresher', 'update', 'continuing'];
        foreach ($intermediateKeywords as $keyword) {
            if (strpos($title, $keyword) !== false || strpos($programs, $keyword) !== false) {
                return 'Intermediate';
            }
        }

        // Check for basic level indicators
        $basicKeywords = ['basic', 'introduction', 'fundamental', 'orientation', 'beginner'];
        foreach ($basicKeywords as $keyword) {
            if (strpos($title, $keyword) !== false || strpos($programs, $keyword) !== false) {
                return 'Basic';
            }
        }

        // Default based on training type
        return $training->type === 'global_training' ? 'Basic' : 'Intermediate';
    }

    protected function getTrainingProvider(Training $training): string {
        return match ($training->lead_type) {
            'national' => $training->division?->name ?? 'Ministry of Health',
            'county' => $training->county?->name . ' County',
            'partner' => $training->partner?->name ?? 'Partner Organization',
            default => 'Ministry of Health'
        };
    }

    protected function getTrainingLocation(Training $training): string {
        // Priority: locations > facility > county
        if ($training->locations && $training->locations->isNotEmpty()) {
            return $training->locations->pluck('name')->implode('; ');
        }

        if ($training->facility) {
            return $training->facility->name;
        }

        if ($training->county) {
            return $training->county->name . ' County';
        }

        return 'Various Locations';
    }

    protected function getLeadOrganization(Training $training): string {
        return match ($training->lead_type) {
            'national' => $training->division?->name ?? 'Ministry of Health',
            'county' => $training->county?->name ?? 'County Government',
            'partner' => $training->partner?->name ?? 'Partner Organization',
            default => 'Not specified'
        };
    }

    protected function determineFacilityLevel($facility): string {
        if (!$facility)
            return '';

        $facilityName = strtolower($facility->name);

        // Level 6 - National Referral
        if (strpos($facilityName, 'national') !== false ||
            strpos($facilityName, 'kenyatta') !== false ||
            strpos($facilityName, 'moi') !== false) {
            return 'Level 6 - National Referral';
        }

        // Level 5 - County Referral  
        if (strpos($facilityName, 'county') !== false &&
            (strpos($facilityName, 'hospital') !== false || strpos($facilityName, 'referral') !== false)) {
            return 'Level 5 - County Referral';
        }

        // Level 4 - Sub-County Hospital
        if (strpos($facilityName, 'hospital') !== false ||
            strpos($facilityName, 'sub') !== false) {
            return 'Level 4 - Sub-County Hospital';
        }

        // Level 3 - Health Centre
        if (strpos($facilityName, 'health centre') !== false ||
            strpos($facilityName, 'health center') !== false) {
            return 'Level 3 - Health Centre';
        }

        // Level 2 - Dispensary
        if (strpos($facilityName, 'dispensary') !== false) {
            return 'Level 2 - Dispensary';
        }

        // Default
        return 'Level 3 - Health Centre';
    }

    protected function countPassedParticipants(Training $training): int {
        return $training->participants->filter(function ($participant) use ($training) {
            return $this->calculateParticipantOutcome($participant) === 'Pass';
        })->count();
    }

    protected function getOverallScore($participant, Training $training): string {
        if (method_exists($training, 'calculateOverallScore')) {
            $calculation = $training->calculateOverallScore($participant);
            return $calculation['all_assessed'] ? $calculation['score'] . '%' : '';
        }
        return '';
    }

    protected function getLatestAssessmentDate($participant): string {
        $latest = $participant->assessmentResults()->latest('assessment_date')->first();
        return $latest?->assessment_date?->format('Y-m-d') ?? '';
    }

    protected function getAssessorName($participant): string {
        $latest = $participant->assessmentResults()->with('assessor')->latest('assessment_date')->first();
        return $latest?->assessor?->full_name ?? '';
    }

    protected function sanitizeSheetName(string $name): string {
        // Excel sheet name limitations: max 31 chars, no special chars
        $name = preg_replace('/[^\w\s-]/', '', $name);
        $name = str_replace(['/', '\\', '?', '*', ':', '[', ']'], '', $name);
        return substr($name, 0, 31);
    }

    // CSV Export Methods
    protected function createCsvContent(array $data): string {
        $csv = '';

        // Add header
        $csv .= "Training Export Report\n";
        $csv .= "Generated: " . Carbon::now()->format('F j, Y g:i A') . "\n";
        $csv .= "Export Type: " . ucwords(str_replace('_', ' ', $data['export_type'])) . "\n\n";

        switch ($data['export_type']) {
            case 'training_participants':
                $csv .= $this->createTrainingParticipantsCsvContent($data);
                break;
            case 'participant_trainings':
                $csv .= $this->createParticipantTrainingsCsvContent($data);
                break;
            case 'training_summary':
                $csv .= $this->createTrainingSummaryCsvContent($data);
                break;
        }

        return $csv;
    }

    protected function createTrainingParticipantsCsvContent(array $data): string {
        $csv = '';
        
        // Get headers once to ensure consistency
        $headers = $this->getCompleteHeaders($data);
        
        $trainings = Training::with([
            'facility', 'county', 'partner', 'division', 'programs',
            'participants.user.facility.subcounty.county',
            'participants.user.department',
            'participants.user.cadre',
            'participants.assessmentResults',
            'assessmentCategories'
        ])->whereIn('id', $data['selected_trainings'])->get();

        foreach ($trainings as $trainingIndex => $training) {
            if ($trainingIndex > 0) {
                $csv .= "\n\n";
            }

            // Training info header
            $csv .= "TRAINING: {$training->title}\n";
            $csv .= "Type: " . ($training->type === 'global_training' ? 'MOH Global Training' : 'Facility Mentorship') . "\n";
            $csv .= "Programs: " . $training->programs->pluck('name')->implode(', ') . "\n";

            if ($training->start_date) {
                $dateRange = $training->start_date->format('M j, Y');
                if ($training->end_date) {
                    $dateRange .= ' to ' . $training->end_date->format('M j, Y');
                }
                $csv .= "Dates: {$dateRange}\n";
            }
            $csv .= "\n";

            // Headers - use the SAME headers for all trainings
            $csv .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

            // Data rows - ensure each row has EXACTLY the same number of columns
            $participants = $this->getFilteredTrainingParticipants($training, $data);
            foreach ($participants as $participant) {
                $row = $this->formatCompleteParticipantRow($participant, $training, $data, count($headers));
                $csv .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
            }
        }

        return $csv;
    }

    protected function createParticipantTrainingsCsvContent(array $data): string {
        $csv = '';
        
        // Fixed headers
        $headers = [
            'Training Title', 'Training Type', 'Programs', 'Start Date', 'End Date',
            'Training Location', 'Registration Date', 'Attendance Status',
            'Completion Status', 'Outcome', 'Training Level', 'Trained By',
            'Provider', 'Month', 'Year', 'TOT'
        ];

        $participants = User::with([
            'facility.subcounty.county', 'department', 'cadre',
            'trainingParticipations.training.programs',
            'trainingParticipations.assessmentResults'
        ])->whereIn('id', $data['selected_participants'])->get();

        foreach ($participants as $participantIndex => $participant) {
            if ($participantIndex > 0) {
                $csv .= "\n\n";
            }

            $csv .= "PARTICIPANT: {$participant->full_name}\n";
            $csv .= "Facility: " . ($participant->facility?->name ?? 'Not specified') . "\n";
            $csv .= "County: " . ($participant->facility?->subcounty?->county?->name ?? 'Not specified') . "\n\n";

            // Headers
            $csv .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

            $trainings = $participant->trainingParticipations()->with([
                'training.programs', 'training.locations', 'training.facility',
                'training.county', 'training.mentor', 'assessmentResults'
            ])->get();

            foreach ($trainings as $participation) {
                $training = $participation->training;
                $outcome = $this->calculateParticipantOutcome($participation);
                $isTot = $this->isTrainerOfTrainers($participation, $training);

                // Ensure row has EXACTLY same number of columns as headers
                $row = [
                    $training->title,
                    $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
                    $training->programs->pluck('name')->implode('; '),
                    $training->start_date?->format('Y-m-d') ?? '',
                    $training->end_date?->format('Y-m-d') ?? '',
                    $this->getTrainingLocation($training),
                    $participation->registration_date?->format('Y-m-d') ?? '',
                    ucfirst($participation->attendance_status),
                    ucfirst($participation->completion_status),
                    $outcome,
                    $this->determineTrainingLevel($training),
                    $training->mentor?->full_name ?? 'Not specified',
                    $this->getTrainingProvider($training),
                    $training->start_date?->format('F') ?? '',
                    $training->start_date?->format('Y') ?? '',
                    $isTot ? 'YES' : 'NO'
                ];
                
                // Pad or trim to match header count
                while (count($row) < count($headers)) {
                    $row[] = '';
                }
                $row = array_slice($row, 0, count($headers));

                $csv .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
            }
        }

        return $csv;
    }

    protected function createTrainingSummaryCsvContent(array $data): string {
        $csv = "TRAINING SUMMARY REPORT\n\n";

        // Fixed headers
        $headers = [
            'Training Name', 'Training Type', 'Lead Organization', 'Programs',
            'Start Date', 'End Date', 'Training Location', 'Max Participants',
            'Total Enrolled', 'Completed', 'Completion Rate', 'Pass Rate', 'Training Level'
        ];
        
        $csv .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

        $trainings = Training::with(['participants', 'programs', 'county', 'partner', 'division'])
            ->whereIn('id', $data['selected_trainings'])
            ->get();

        foreach ($trainings as $training) {
            $totalParticipants = $training->participants->count();
            $completedParticipants = $training->participants->where('completion_status', 'completed')->count();
            $passedParticipants = $this->countPassedParticipants($training);

            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;
            $passRate = $completedParticipants > 0 ? round(($passedParticipants / $completedParticipants) * 100, 1) : 0;

            // Ensure row has EXACTLY same number of columns as headers
            $row = [
                $training->title,
                $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
                $this->getLeadOrganization($training),
                $training->programs->pluck('name')->implode('; '),
                $training->start_date?->format('Y-m-d') ?? '',
                $training->end_date?->format('Y-m-d') ?? '',
                $this->getTrainingLocation($training),
                $training->max_participants ?? '',
                $totalParticipants,
                $completedParticipants,
                $completionRate . '%',
                $passRate . '%',
                $this->determineTrainingLevel($training)
            ];
            
            // Pad or trim to match header count
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $row = array_slice($row, 0, count($headers));

            $csv .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
        }

        return $csv;
    }

    protected function downloadCsvViaJavascript(string $csvContent, string $filename): void {
        // Escape the content for JavaScript
        $escapedContent = str_replace(["\r\n", "\r", "\n"], "\\n", addslashes($csvContent));

        // Use JavaScript to create and download the file
        $this->js("
            (function() {
                const csvContent = '{$escapedContent}';
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', '{$filename}');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            })();
        ");
    }

    protected function csvEscape($value): string {
        // Convert to string and escape for CSV
        $value = (string) $value;

        // If value contains comma, quote, or newline, wrap in quotes
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            // Escape quotes by doubling them
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    // Don't create database records
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model {
        return new Training();
    }

    protected function mutateFormDataBeforeCreate(array $data): array {
        return [];
    }
}