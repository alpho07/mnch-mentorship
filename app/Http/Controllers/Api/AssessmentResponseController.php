<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkStoreResponsesRequest;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Services\DynamicScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentResponseController extends Controller {

    public function __construct(
            private readonly DynamicScoringService $scoringService
    ) {
        
    }

    /**
     * GET /api/v1/assessments/{assessment}/responses
     *
     * Returns all responses for the assessment, keyed by question_code.
     * Format: { "INFRA_NBU": "Yes", "INFRA_NBU_BEDS": "8", ... }
     *
     * This is how the mobile app re-hydrates an in-progress assessment.
     */
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $responses = $assessment->questionResponses()
                ->with('question:id,question_code,question_type,assessment_section_id')
                ->get();

        // Build a flat map: question_code => response_value
        $flat = $responses->mapWithKeys(fn($r) => [
            $r->question->question_code => $r->response_value,
        ]);

        // Also build metadata map for complex responses (explanation, metadata)
        $detailed = $responses->mapWithKeys(fn($r) => [
            $r->question->question_code => [
                'value' => $r->response_value,
                'explanation' => $r->explanation,
                'metadata' => $r->metadata,
                'score' => $r->score,
                'section_id' => $r->question->assessment_section_id,
            ],
        ]);

        return response()->json([
                    'responses' => $flat,
                    'responses_detailed' => $detailed,
        ]);
    }

    /**
     * GET /api/v1/assessments/{assessment}/responses/{questionCode}
     */
    public function show(Request $request, Assessment $assessment, string $questionCode): JsonResponse {
        $this->authorize('view', $assessment);

        $question = AssessmentQuestion::where('question_code', $questionCode)->firstOrFail();
        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
                ->where('assessment_question_id', $question->id)
                ->first();

        return response()->json([
                    'question_code' => $questionCode,
                    'value' => $response?->response_value,
                    'explanation' => $response?->explanation,
                    'metadata' => $response?->metadata,
                    'score' => $response?->score,
        ]);
    }

    /**
     * POST /api/v1/assessments/{assessment}/responses
     *
     * BULK SAVE — The primary write endpoint for the mobile app.
     *
     * Accepts all responses for ONE section at a time:
     * {
     *   "section_code": "infrastructure",
     *   "responses": {
     *     "INFRA_NBU":       "Yes",
     *     "INFRA_NBU_BEDS":  "8",
     *     "INFRA_NBU_COTS":  "12",
     *     "INFRA_TRAINING":  "Partial",
     *     ...
     *   },
     *   "explanations": {
     *     "INFRA_TRAINING": "Only the main hall is available"
     *   }
     * }
     *
     * After saving:
     * 1. Upserts each response (create or update)
     * 2. Recalculates section score
     * 3. Marks section as done in section_progress
     * 4. Returns updated section score + overall progress
     */
    public function bulkStore(BulkStoreResponsesRequest $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Cannot modify a completed assessment.'], 403);
        }

        $sectionCode = $request->section_code;
        $responses = $request->responses;      // ['QUESTION_CODE' => 'value', ...]
        $explanations = $request->explanations ?? [];

        // Resolve section
        $section = AssessmentSection::where('code', $sectionCode)->firstOrFail();

        // Get all questions for this section (active only)
        $questions = AssessmentQuestion::where('assessment_section_id', $section->id)
                ->where('is_active', true)
                ->pluck('id', 'question_code'); // ['CODE' => id, ...]

        DB::transaction(function () use ($assessment, $questions, $responses, $explanations, $section, $sectionCode) {

            foreach ($responses as $questionCode => $value) {
                $questionId = $questions->get($questionCode);
                if (!$questionId) {
                    continue; // skip unknown question codes
                }

                // Resolve score from scoring_map
                $question = AssessmentQuestion::find($questionId);
                $score = null;
                if ($question?->scoring_map && isset($question->scoring_map[$value])) {
                    $score = $question->scoring_map[$value];
                }

                AssessmentQuestionResponse::updateOrCreate(
                        [
                            'assessment_id' => $assessment->id,
                            'assessment_question_id' => $questionId,
                        ],
                        [
                            'response_value' => $value,
                            'explanation' => $explanations[$questionCode] ?? null,
                            'score' => $score,
                        ]
                );
            }

            // Recalculate section score
            $this->scoringService->recalculateSectionScore($assessment->id, $section->id);

            // Mark section as done in section_progress
            $progress = $assessment->section_progress ?? [];
            $progress[$sectionCode] = true;
            $assessment->update(['section_progress' => $progress]);
        });

        // Return updated section score
        $sectionScore = $assessment->sectionScores()->where('assessment_section_id', $section->id)->first();
        $sectionProgress = $assessment->fresh()->section_progress ?? [];

        return response()->json([
                    'message' => "Section '{$section->name}' saved successfully.",
                    'section_score' => $sectionScore ? [
                'percentage' => $sectionScore->percentage,
                'grade' => $sectionScore->grade,
                'total_questions' => $sectionScore->total_questions,
                'answered_questions' => $sectionScore->answered_questions,
                    ] : null,
                    'section_progress' => $sectionProgress,
                    'all_sections_done' => !in_array(false, $sectionProgress, true),
        ]);
    }
}
