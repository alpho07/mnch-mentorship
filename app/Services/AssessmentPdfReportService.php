<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use Barryvdh\DomPDF\Facade\Pdf;

class AssessmentPdfReportService {

    public function generateExecutiveReport(Assessment $assessment) {
        $data = $this->prepareReportData($assessment);

        $pdf = Pdf::loadView('pdf.assessment-executive-report', $data);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        // Set options
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf;
    }

    protected function prepareReportData(Assessment $assessment) {
        // Load all relationships
        $assessment->load([
            'facility.subcounty.county',
            'assessor',
            'sectionScores.section',
            'questionResponses.question.section',
            'humanResourceResponses.cadre',
            'commodityResponses.commodity.category',
            'commodityResponses.department',
            'departmentScores.department',
            'departmentScores.category',
        ]);

        // Facility Information
        $facilityInfo = [
            'name' => $assessment->facility->name,
            'mfl_code' => $assessment->facility->mfl_code,
            'level' => $assessment->facility->level,
            'ownership' => $assessment->facility->ownership,
            'county' => $assessment->facility->subcounty->county->name,
            'subcounty' => $assessment->facility->subcounty->name,
            'contact' => $assessment->facility->phone ?? $assessment->facility->email,
        ];

        // Assessment Details
        $assessmentDetails = [
            'type' => ucfirst($assessment->assessment_type),
            'date' => $assessment->assessment_date->format('F j, Y'),
            'status' => ucfirst($assessment->status),
            'assessor_name' => $assessment->assessor_name,
            'assessor_contact' => $assessment->assessor_contact,
            'completed_at' => $assessment->completed_at?->format('F j, Y'),
        ];

        // Overall Score
        $overallScore = [
            'score' => $assessment->overall_score,
            'percentage' => $assessment->overall_percentage,
            'grade' => $assessment->overall_grade,
            'grade_color' => $this->getGradeColor($assessment->overall_grade),
        ];

        // Section Scores
        $sections = $assessment->sectionScores->map(function ($sectionScore) {
            return [
                'name' => $sectionScore->section->name,
                'total_score' => $sectionScore->total_score,
                'max_score' => $sectionScore->max_score,
                'percentage' => $sectionScore->percentage,
                'total_questions' => $sectionScore->total_questions,
                'answered_questions' => $sectionScore->answered_questions,
                'skipped_questions' => $sectionScore->skipped_questions,
            ];
        });

        // Infrastructure Details
        $infrastructure = $this->getInfrastructureDetails($assessment);

        // Skills Lab Details
        $skillsLab = $this->getSkillsLabDetails($assessment);

        // Human Resources Summary
        $humanResources = $this->getHumanResourcesSummary($assessment);

        // Health Products Summary
        $healthProducts = $this->getHealthProductsSummary($assessment);

        // Information Systems
        $informationSystems = $this->getInformationSystemsDetails($assessment);

        // Quality of Care
        $qualityOfCare = $this->getQualityOfCareDetails($assessment);

        return compact(
                'assessment',
                'facilityInfo',
                'assessmentDetails',
                'overallScore',
                'sections',
                'infrastructure',
                'skillsLab',
                'humanResources',
                'healthProducts',
                'informationSystems',
                'qualityOfCare'
        );
    }

    protected function getInfrastructureDetails(Assessment $assessment) {
        $sectionId = AssessmentSection::where('code', 'infrastructure')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

        return [
            'has_nbu' => $responses->where('question.question_code', 'INFRA_NBU')->first()?->response_value === 'Yes',
            'nbu_beds' => $responses->where('question.question_code', 'INFRA_NBU')->first()?->metadata['nicu_beds'] ?? 0,
            'nbu_cots' => $responses->where('question.question_code', 'INFRA_NBU')->first()?->metadata['general_cots'] ?? 0,
            'nbu_kmc' => $responses->where('question.question_code', 'INFRA_NBU')->first()?->metadata['kmc_beds'] ?? 0,
            'has_paed' => $responses->where('question.question_code', 'INFRA_PAED')->first()?->response_value === 'Yes',
            'paed_beds' => $responses->where('question.question_code', 'INFRA_PAED')->first()?->metadata['general_beds'] ?? 0,
            'paed_picu' => $responses->where('question.question_code', 'INFRA_PAED')->first()?->metadata['picu_beds'] ?? 0,
            'all_responses' => $responses,
        ];
    }

    protected function getSkillsLabDetails(Assessment $assessment) {
        $sectionId = AssessmentSection::where('code', 'skills_lab')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

        $hasSkillsLab = $responses->where('question.question_code', 'SKILLS_MASTER')->first()?->response_value === 'Yes';

        $equipment = $responses->filter(function ($response) use ($hasSkillsLab) {
                    return $hasSkillsLab && $response->response_value === 'Yes' && $response->question->question_code !== 'SKILLS_MASTER';
                })->map(function ($response) {
            return $response->question->question_text;
        });

        return [
            'has_skills_lab' => $hasSkillsLab,
            'equipment_count' => $equipment->count(),
            'equipment_list' => $equipment->values(),
            'all_responses' => $responses,
        ];
    }

    protected function getHumanResourcesSummary(Assessment $assessment) {
        $responses = $assessment->humanResourceResponses()->with('cadre')->get();

        $summary = [
            'total_staff' => $responses->sum('total_in_facility'),
            'total_etat_plus' => $responses->sum('etat_plus'),
            'total_comprehensive_nb' => $responses->sum('comprehensive_newborn_care'),
            'total_imnci' => $responses->sum('imnci'),
            'total_diabetes' => $responses->sum('type_1_diabetes'),
            'total_essential_nb' => $responses->sum('essential_newborn_care'),
            'by_cadre' => $responses->map(function ($response) {
                return [
                    'cadre' => $response->cadre->name,
                    'total' => $response->total_in_facility,
                    'etat_plus' => $response->etat_plus,
                    'comprehensive_nb' => $response->comprehensive_newborn_care,
                    'imnci' => $response->imnci,
                    'diabetes' => $response->type_1_diabetes,
                    'essential_nb' => $response->essential_newborn_care,
                ];
            }),
        ];

        return $summary;
    }

    protected function getHealthProductsSummary(Assessment $assessment) {
        $commodityResponses = $assessment->commodityResponses()
                ->with(['commodity.category', 'department'])
                ->get()
                ->groupBy('department.name');

        $departmentScores = $assessment->departmentScores()
                ->with(['department', 'category'])
                ->get()
                ->groupBy('department.name');

        $summary = [];

        foreach ($commodityResponses as $departmentName => $responses) {
            $scores = $departmentScores->get($departmentName, collect());

            $totalAvailable = $responses->where('available', true)->count();
            $totalApplicable = $responses->count();
            $percentage = $totalApplicable > 0 ? ($totalAvailable / $totalApplicable) * 100 : 0;

            // Group commodities by category
            $byCategory = $responses->groupBy('commodity.category.name');

            $summary[$departmentName] = [
                'available' => $totalAvailable,
                'total' => $totalApplicable,
                'percentage' => round($percentage, 1),
                'grade' => $this->calculateGrade($percentage),
                'commodities' => $responses->map(function ($response) {
                    return [
                        'name' => $response->commodity->name,
                        'category' => $response->commodity->category->name,
                        'available' => $response->available,
                    ];
                }),
                'by_category' => $byCategory->map(function ($items, $categoryName) {
                    $available = $items->where('available', true)->count();
                    $total = $items->count();
                    return [
                        'name' => $categoryName,
                        'available' => $available,
                        'total' => $total,
                        'percentage' => $total > 0 ? round(($available / $total) * 100, 1) : 0,
                    ];
                }),
            ];
        }

        return $summary;
    }

    protected function getInformationSystemsDetails(Assessment $assessment) {
        $sectionId = AssessmentSection::where('code', 'information_systems')->value('id');

        return $assessment->questionResponses()
                        ->whereHas('question', function ($q) use ($sectionId) {
                            $q->where('assessment_section_id', $sectionId);
                        })
                        ->with('question')
                        ->get();
    }

    protected function getQualityOfCareDetails(Assessment $assessment) {
        $sectionId = AssessmentSection::where('code', 'quality_of_care')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

        // Separate by question type
        $yesNoQuestions = $responses->filter(function ($response) {
            return $response->question->question_type === 'yes_no';
        });

        $selectQuestions = $responses->filter(function ($response) {
            return $response->question->question_type === 'select';
        });

        $numberQuestions = $responses->filter(function ($response) {
            return $response->question->question_type === 'number';
        });

        // Group number questions by category
        $newbornStats = $numberQuestions->filter(function ($response) {
            $code = $response->question->question_code;
            return str_contains($code, 'NEWBORN') || str_contains($code, 'PRETERM') ||
                    str_contains($code, 'ASPHYXIA') || str_contains($code, 'CPAP') ||
                    str_contains($code, 'APNOEA') || str_contains($code, 'CAFFEINE') ||
                    str_contains($code, 'HYPOTHERMIA') || str_contains($code, 'O2_SAT') ||
                    str_contains($code, 'RBS') || str_contains($code, 'HEAD_TO_TOE');
        });

        $paedStats = $numberQuestions->filter(function ($response) {
            $code = $response->question->question_code;
            return str_contains($code, 'PAED');
        });

        return [
            'yes_no' => $yesNoQuestions,
            'select' => $selectQuestions,
            'newborn_stats' => $newbornStats,
            'paed_stats' => $paedStats,
            'all_responses' => $responses,
        ];
    }

    protected function calculateGrade(float $percentage): string {
        if ($percentage >= 80)
            return 'green';
        if ($percentage >= 50)
            return 'yellow';
        return 'red';
    }

    protected function getGradeColor(string $grade): string {
        return match ($grade) {
            'green' => '#10b981',
            'yellow' => '#f59e0b',
            'red' => '#ef4444',
            default => '#6b7280',
        };
    }
}
