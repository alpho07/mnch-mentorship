<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentQuestionResponse;

class DynamicScoringService {

    /**
     * Recalculate score for a specific section
     */
    public static function recalculateSectionScore(int $assessmentId, int $sectionId): void {
        $section = AssessmentSection::findOrFail($sectionId);

        if (!$section->is_scored) {
            return; // Section not scored, skip
        }

        // Get all scored questions in this section
        $questions = $section->questions()
                ->active()
                ->scored()
                ->get();

        $totalQuestions = $questions->count();

        if ($totalQuestions === 0) {
            return; // No questions to score
        }

        // Get all responses for this section
        $responses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                ->whereIn('assessment_question_id', $questions->pluck('id'))
                ->get();

        // Calculate scores
        $totalScore = $responses->whereNotNull('score')->sum('score');
        $maxScore = $totalQuestions; // Assuming each question has max score of 1
        $answeredQuestions = $responses->whereNotNull('response_value')->count();
        $skippedQuestions = $totalQuestions - $answeredQuestions;

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        // Update or create section score
        AssessmentSectionScore::updateOrCreate(
                [
                    'assessment_id' => $assessmentId,
                    'assessment_section_id' => $sectionId,
                ],
                [
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'total_questions' => $totalQuestions,
                    'answered_questions' => $answeredQuestions,
                    'skipped_questions' => $skippedQuestions,
                ]
        );

        // Update overall assessment score
        self::recalculateOverallScore($assessmentId);
    }

    /**
     * Recalculate overall assessment score
     */
    public static function recalculateOverallScore(int $assessmentId): void {
        $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)
                ->get();

        if ($sectionScores->isEmpty()) {
            return;
        }

        $totalScore = $sectionScores->sum('total_score');
        $maxScore = $sectionScores->sum('max_score');
        $overallPercentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        $overallGrade = self::calculateGrade($overallPercentage);

        // Update assessment
        Assessment::where('id', $assessmentId)->update([
            'overall_score' => $totalScore,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
        ]);
    }

    /**
     * Get complete scoring summary for an assessment
     */
    public function getAssessmentSummary(int $assessmentId): array {
        $assessment = Assessment::findOrFail($assessmentId);
        $sections = AssessmentSection::active()->scored()->ordered()->get();

        $summary = [];

        foreach ($sections as $section) {
            $sectionScore = AssessmentSectionScore::where('assessment_id', $assessmentId)
                    ->where('assessment_section_id', $section->id)
                    ->first();

            $progress = $section->getProgressForAssessment($assessmentId);

            $summary[$section->code] = [
                'section' => $section,
                'score' => $sectionScore,
                'progress' => $progress,
                'questions' => $section->getActiveQuestionsCount(),
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

    /**
     * Get statistics for an assessment
     */
    public function getAssessmentStats(int $assessmentId): array {
        $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)->get();

        if ($sectionScores->isEmpty()) {
            return [
                'overall_percentage' => 0,
                'overall_grade' => null,
                'green_count' => 0,
                'yellow_count' => 0,
                'red_count' => 0,
                'total_sections' => 0,
            ];
        }

        return [
            'overall_percentage' => $sectionScores->avg('percentage'),
            'overall_grade' => $this->calculateGrade($sectionScores->avg('percentage')),
            'green_count' => $sectionScores->where('grade', 'green')->count(),
            'yellow_count' => $sectionScores->where('grade', 'yellow')->count(),
            'red_count' => $sectionScores->where('grade', 'red')->count(),
            'total_sections' => $sectionScores->count(),
            'total_questions' => $sectionScores->sum('total_questions'),
            'answered_questions' => $sectionScores->sum('answered_questions'),
            'skipped_questions' => $sectionScores->sum('skipped_questions'),
        ];
    }

    /**
     * Calculate grade from percentage
     */
    protected static function calculateGrade(float $percentage): string {
        if ($percentage >= 80) {
            return 'green';
        } elseif ($percentage >= 50) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    /**
     * Check if section is complete
     */
    public function isSectionComplete(int $assessmentId, int $sectionId): bool {
        $section = AssessmentSection::findOrFail($sectionId);
        $progress = $section->getProgressForAssessment($assessmentId);

        return $progress['percentage'] === 100.0;
    }

    /**
     * Get all responses for a section
     */
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
}
