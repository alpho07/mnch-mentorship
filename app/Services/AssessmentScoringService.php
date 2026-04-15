<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\AssessmentSection;

class AssessmentScoringService {

    public function calculateScore(Assessment $assessment): array {
        $sections = AssessmentSection::where('assessment_type_id', $assessment->assessment_type_id)
                ->with(['questions' => function ($query) {
                        $query->where('is_active', true)
                                ->where('include_in_scoring', true);
                    }])
                ->get();

        $totalScore = 0;
        $maxScore = 0;
        $sectionScores = [];

        foreach ($sections as $section) {
            $sectionScore = 0;
            $sectionMaxScore = 0;

            foreach ($section->questions as $question) {
                // Get all responses for this question
                $responses = AssessmentResponse::where('assessment_id', $assessment->id)
                        ->where('question_id', $question->id)
                        ->get();

                if ($responses->isEmpty()) {
                    continue;
                }

                $scoringMap = $question->scoring_map;

                if (!$scoringMap) {
                    continue;
                }

                // Calculate score based on question type
                if ($question->response_type === 'matrix') {
                    // For matrix questions, calculate average score across locations
                    $locationScores = 0;
                    $locationCount = 0;

                    foreach ($responses as $response) {
                        $responseValue = $response->response_value;

                        if (isset($scoringMap[$responseValue]) && $scoringMap[$responseValue] !== null) {
                            $locationScores += $scoringMap[$responseValue];
                            $locationCount++;
                        }
                    }

                    if ($locationCount > 0) {
                        $questionScore = $locationScores / $locationCount;
                        $sectionScore += $questionScore;
                        $sectionMaxScore += 1;

                        // Update individual response scores
                        foreach ($responses as $response) {
                            $responseValue = $response->response_value;
                            if (isset($scoringMap[$responseValue]) && $scoringMap[$responseValue] !== null) {
                                $response->update(['score' => $scoringMap[$responseValue]]);
                            }
                        }
                    }
                } else {
                    // For regular questions
                    $response = $responses->first();
                    $responseValue = $response->response_value;

                    if (isset($scoringMap[$responseValue]) && $scoringMap[$responseValue] !== null) {
                        $score = $scoringMap[$responseValue];
                        $sectionScore += $score;
                        $sectionMaxScore += 1;

                        // Update response score
                        $response->update(['score' => $score]);
                    }
                }
            }

            $sectionPercentage = $sectionMaxScore > 0 ? ($sectionScore / $sectionMaxScore) * 100 : 0;

            $sectionScores[$section->name] = [
                'score' => $sectionScore,
                'max_score' => $sectionMaxScore,
                'percentage' => round($sectionPercentage, 2),
            ];

            $totalScore += $sectionScore;
            $maxScore += $sectionMaxScore;
        }

        $overallPercentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;

        $grade = $this->determineGrade($overallPercentage);

        // Update assessment with scores
        $assessment->update([
            'total_score' => round($totalScore, 2),
            'max_score' => round($maxScore, 2),
            'percentage' => round($overallPercentage, 2),
            'grade' => $grade,
            'metadata' => array_merge($assessment->metadata ?? [], [
                'section_scores' => $sectionScores,
                'last_scored_at' => now()->toDateTimeString(),
            ]),
        ]);

        return [
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => round($overallPercentage, 2),
            'grade' => $grade,
            'section_scores' => $sectionScores,
        ];
    }

    protected function determineGrade(float $percentage): string {
        return match (true) {
            $percentage >= 90 => 'Excellent',
            $percentage >= 75 => 'Good',
            $percentage >= 60 => 'Satisfactory',
            $percentage >= 50 => 'Needs Improvement',
            default => 'Poor',
        };
    }

    public function getSectionScores(Assessment $assessment): array {
        return $assessment->metadata['section_scores'] ?? [];
    }

    public function getDetailedReport(Assessment $assessment): array {
        $sections = AssessmentSection::where('assessment_type_id', $assessment->assessment_type_id)
                ->with(['questions' => function ($query) {
                        $query->where('is_active', true)->orderBy('order');
                    }])
                ->orderBy('order')
                ->get();

        $report = [];

        foreach ($sections as $section) {
            $sectionData = [
                'section_name' => $section->name,
                'section_code' => $section->code,
                'questions' => [],
            ];

            foreach ($section->questions as $question) {
                $responses = AssessmentResponse::where('assessment_id', $assessment->id)
                        ->where('question_id', $question->id)
                        ->get();

                $questionData = [
                    'question_code' => $question->question_code,
                    'question_text' => $question->question_text,
                    'response_type' => $question->response_type,
                    'responses' => [],
                ];

                foreach ($responses as $response) {
                    $questionData['responses'][] = [
                        'location' => $response->location,
                        'value' => $response->response_value,
                        'explanation' => $response->explanation,
                        'score' => $response->score,
                    ];
                }

                $sectionData['questions'][] = $questionData;
            }

            $report[] = $sectionData;
        }

        return $report;
    }
}
