<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentQuestionResponse;

class DynamicScoringService {

    /**
     * Skills Lab question codes that are ONLY scored when SKILLS_MASTER = "No".
     *
     * Context: these two questions ask about fallback space/storage when a
     * dedicated skills lab does NOT exist. When the facility has a skills lab
     * (SKILLS_MASTER = "Yes") these questions are not shown and must not be
     * counted against the section score.
     */
    private const SKILLS_LAB_CONDITIONAL_CODES = [
        'SKILLS_ROOM', // "Is there a room/space used for skills teaching and simulation?"
        'SKILLS_STORAGE', // "Is there a lockable storage area for the equipment…?"
    ];

    /**
     * Recalculate score for a specific section.
     */
    public static function recalculateSectionScore(int $assessmentId, int $sectionId): void {
        $section = AssessmentSection::findOrFail($sectionId);

        if (!$section->is_scored) {
            return;
        }

        // Get all active, scored questions
        $questions = $section->questions()
                ->active()
                ->scored()
                ->get();

        // ── Skills Lab conditional exclusion ──────────────────────────────────
        // When SKILLS_MASTER = "Yes" the two fallback questions are hidden by
        // conditional_logic on the form and should be excluded from scoring.
        if ($section->code === 'skills_lab') {
            $questions = self::filterSkillsLabQuestions($assessmentId, $questions);
        }
        // ─────────────────────────────────────────────────────────────────────

        $totalQuestions = $questions->count();

        if ($totalQuestions === 0) {
            return;
        }

        $responses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                ->whereIn('assessment_question_id', $questions->pluck('id'))
                ->get();

        $totalScore = $responses->whereNotNull('score')->sum('score');
        $maxScore = $totalQuestions;
        $answeredQuestions = $responses->whereNotNull('response_value')->count();
        $skippedQuestions = $totalQuestions - $answeredQuestions;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        AssessmentSectionScore::updateOrCreate(
                ['assessment_id' => $assessmentId, 'assessment_section_id' => $sectionId],
                [
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'grade' => self::calculateGrade($percentage),
                    'total_questions' => $totalQuestions,
                    'answered_questions' => $answeredQuestions,
                    'skipped_questions' => $skippedQuestions,
                ]
        );

        self::recalculateOverallScore($assessmentId);
    }

    /**
     * Filter Skills Lab questions based on the SKILLS_MASTER response.
     *
     * - SKILLS_MASTER = "Yes" → exclude SKILLS_ROOM and SKILLS_STORAGE
     *   (these questions are hidden on the form; scoring them would penalise
     *    a facility for not having a fallback space when they have a proper lab)
     *
     * - SKILLS_MASTER = "No" / not answered → include all questions
     *   (the facility needs the fallback space, so we count those questions)
     */
    private static function filterSkillsLabQuestions(int $assessmentId, $questions): mixed {
        // Find SKILLS_MASTER question ID
        $masterQuestion = $questions->first(fn($q) => $q->question_code === 'SKILLS_MASTER');

        if (!$masterQuestion) {
            return $questions; // Can't determine — score everything
        }

        $masterResponse = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                ->where('assessment_question_id', $masterQuestion->id)
                ->value('response_value');

        if ($masterResponse === 'Yes') {
            // Has a skills lab — exclude the two fallback questions
            return $questions->filter(
                            fn($q) => !in_array($q->question_code, self::SKILLS_LAB_CONDITIONAL_CODES)
                    );
        }

        // Skills lab = No or not yet answered — include everything
        return $questions;
    }

    /**
     * Recalculate overall assessment score from all section scores.
     */
    public static function recalculateOverallScore(int $assessmentId): void {
        $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)->get();

        if ($sectionScores->isEmpty()) {
            return;
        }

        $totalScore = $sectionScores->sum('total_score');
        // Average of section percentages — each section weighted equally regardless of question count
        $overallPercentage = round($sectionScores->avg('percentage'), 2);
        $overallGrade = self::calculateGrade($overallPercentage);

        Assessment::where('id', $assessmentId)->update([
            'overall_score' => $totalScore,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
        ]);
    }

    /**
     * Recalculate all scored sections for an assessment.
     * Called on final submission.
     */
    public function recalculateAllSections(int $assessmentId): void {
        $sections = AssessmentSection::active()->scored()->get();

        foreach ($sections as $section) {
            self::recalculateSectionScore($assessmentId, $section->id);
        }

        self::recalculateOverallScore($assessmentId);
    }

    /**
     * Grade thresholds: ≥80% = green, ≥50% = yellow, <50% = red.
     */
    protected static function calculateGrade(float $percentage): string {
        if ($percentage >= 80)
            return 'green';
        if ($percentage >= 50)
            return 'yellow';
        return 'red';
    }

    // ── Legacy / compatibility methods ────────────────────────────────────────

    public function isSectionComplete(int $assessmentId, int $sectionId): bool {
        $section = AssessmentSection::findOrFail($sectionId);
        $progress = $section->getProgressForAssessment($assessmentId);
        return ($progress['percentage'] ?? 0) === 100.0;
    }

    public function getSectionResponses(int $assessmentId, int $sectionId): array {
        $section = AssessmentSection::with('questions')->findOrFail($sectionId);
        $responses = [];
        foreach ($section->questions as $question) {
            $response = $question->getResponseForAssessment($assessmentId);
            $responses[$question->question_code] = [
                'question' => $question,
                'response' => $response,
                'answered' => $response && $response->response_value !== null,
            ];
        }
        return $responses;
    }

    public function getAssessmentSummary(int $assessmentId): array {
        $assessment = Assessment::findOrFail($assessmentId);
        $sections = AssessmentSection::active()->scored()->ordered()->get();
        $summary = [];

        foreach ($sections as $section) {
            $sectionScore = AssessmentSectionScore::where('assessment_id', $assessmentId)
                    ->where('assessment_section_id', $section->id)
                    ->first();
            $summary[$section->code] = [
                'section' => $section,
                'score' => $sectionScore,
            ];
        }

        return [
            'assessment' => $assessment,
            'sections' => $summary,
            'overall' => [
                'score' => $assessment->overall_score,
                'percentage' => $assessment->overall_percentage,
                'grade' => $assessment->overall_grade,
            ],
        ];
    }

    public function getAssessmentStats(int $assessmentId): array {
        $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)->get();
        if ($sectionScores->isEmpty()) {
            return ['overall_percentage' => 0, 'overall_grade' => null, 'total_sections' => 0];
        }
        return [
            'overall_percentage' => $sectionScores->avg('percentage'),
            'overall_grade' => self::calculateGrade($sectionScores->avg('percentage')),
            'green_count' => $sectionScores->where('grade', 'green')->count(),
            'yellow_count' => $sectionScores->where('grade', 'yellow')->count(),
            'red_count' => $sectionScores->where('grade', 'red')->count(),
            'total_sections' => $sectionScores->count(),
            'total_questions' => $sectionScores->sum('total_questions'),
            'answered_questions' => $sectionScores->sum('answered_questions'),
            'skipped_questions' => $sectionScores->sum('skipped_questions'),
        ];
    }
}
