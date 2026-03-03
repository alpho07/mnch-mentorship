<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AssessmentSectionResource;
use App\Models\AssessmentSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AssessmentSectionController extends Controller {

    /**
     * GET /api/v1/sections
     *
     * Returns a lightweight list of all active sections (no questions).
     * Used for progress dashboards.
     */
    public function index(): JsonResponse {
        $sections = Cache::remember('api.sections.index', now()->addHours(6), function () {
            return AssessmentSection::active()
                            ->ordered()
                            ->withCount(['questions' => fn($q) => $q->where('is_active', true)])
                            ->get();
        });

        return response()->json([
                    'data' => AssessmentSectionResource::collection($sections),
        ]);
    }

    /**
     * GET /api/v1/sections/{section}
     *
     * Returns a single section with its questions.
     */
    public function show(AssessmentSection $section): JsonResponse {
        $section->load(['questions' => fn($q) => $q->where('is_active', true)->orderBy('order')]);

        return response()->json([
                    'data' => new AssessmentSectionResource($section),
        ]);
    }

    /**
     * GET /api/v1/sections/schema/full
     *
     * THE CRITICAL ENDPOINT — returns everything the mobile app needs
     * to render the full dynamic assessment form:
     *
     * - All active sections (ordered)
     * - All active questions per section (ordered)
     * - question_type, options, display_conditions, scoring_map,
     *   is_required, validation_rules, skip_logic, etc.
     *
     * The mobile app calls this ONCE at startup and caches locally.
     * Cache is busted when any section/question is updated via Filament admin.
     */
    public function fullSchema(): JsonResponse {
        $schema = Cache::remember('api.sections.full_schema', now()->addHours(12), function () {
            $sections = AssessmentSection::active()
                    ->ordered()
                    ->with(['questions' => fn($q) => $q
                        ->where('is_active', true)
                        ->orderBy('order')
                        ->select([
                            'id',
                            'assessment_section_id',
                            'question_code',
                            'question_text',
                            'help_text',
                            'question_type',
                            'options',
                            'is_required',
                            'display_conditions',
                            'requires_explanation_on',
                            'explanation_label',
                            'skip_logic',
                            'scoring_map',
                            'is_scored',
                            'order',
                            'group',
                        ])
                    ])
                    ->get([
                        'id',
                        'code',
                        'name',
                        'description',
                        'icon',
                        'color',
                        'order',
                        'section_type',
                        'is_scored',
            ]);

            return $sections->map(fn($section) => [
                        'id' => $section->id,
                        'code' => $section->code,
                        'name' => $section->name,
                        'description' => $section->description,
                        'icon' => $section->icon,
                        'color' => $section->color ?? '#059669',
                        'order' => $section->order,
                        'section_type' => $section->section_type,
                        'is_scored' => $section->is_scored,
                        'questions' => $section->questions->map(fn($q) => [
                            'id' => $q->id,
                            'question_code' => $q->question_code,
                            'question_text' => $q->question_text,
                            'help_text' => $q->help_text,
                            'question_type' => $q->question_type,
                            'options' => $q->options,
                            'is_required' => (bool) $q->is_required,
                            'display_conditions' => $q->display_conditions,
                            'requires_explanation_on' => $q->requires_explanation_on,
                            'explanation_label' => $q->explanation_label,
                            'skip_logic' => $q->skip_logic,
                            'scoring_map' => $q->scoring_map,
                            'is_scored' => (bool) $q->is_scored,
                            'order' => $q->order,
                            'group' => $q->group,
                                ])->values(),
                            ])->values();
        });

        return response()->json([
                    'data' => $schema,
                    'generated' => now()->toIso8601String(),
                    'cache_ttl' => '12h',
        ]);
    }
}
