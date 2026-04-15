<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\AssessmentScoringService;
use Barryvdh\DomPDF\Facade\Pdf;

class AssessmentReportController extends Controller {

    protected AssessmentScoringService $scoringService;

    public function __construct(AssessmentScoringService $scoringService) {
        $this->scoringService = $scoringService;
    }

    public function show(Assessment $assessment) {
        // Load all necessary relationships
        $assessment->load([
            'facility.facilityLevel',
            'facility.facilityType',
            'facility.subcounty.county',
            'assessmentType',
            'assessor',
            'responses.question.section'
        ]);

        // Get detailed report data
        $reportData = $this->scoringService->getDetailedReport($assessment);
        $sectionScores = $this->scoringService->getSectionScores($assessment);

        return view('assessment-report', [
            'assessment' => $assessment,
            'reportData' => $reportData,
            'sectionScores' => $sectionScores,
        ]);
    }

    public function download(Assessment $assessment) {
        $assessment->load([
            'facility.facilityLevel',
            'facility.facilityType',
            'facility.subcounty.county',
            'assessmentType',
            'assessor',
            'responses.question.section'
        ]);

        $reportData = $this->scoringService->getDetailedReport($assessment);
        $sectionScores = $this->scoringService->getSectionScores($assessment);

        $pdf = Pdf::loadView('assessment-report-pdf', [
            'assessment' => $assessment,
            'reportData' => $reportData,
            'sectionScores' => $sectionScores,
        ]);

        return $pdf->download("assessment-{$assessment->assessment_number}.pdf");
    }
}
