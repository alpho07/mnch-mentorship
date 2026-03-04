<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\AssessmentCommodityResponse;
use App\Services\CommodityScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HealthProductsController extends Controller {

    public function __construct(
            private readonly CommodityScoringService $scoringService
    ) {
        
    }

    /**
     * GET /api/v1/assessments/{assessment}/health-products
     *
     * Returns the full commodity schema (departments → categories → commodities)
     * merged with existing responses for this assessment.
     *
     * Response shape:
     * {
     *   "data": [
     *     {
     *       "department_id": 1,
     *       "department_name": "Skills Lab",
     *       "categories": [
     *         {
     *           "category_id": 1,
     *           "category_name": "AIRWAY",
     *           "commodities": [
     *             {
     *               "commodity_id": 5,
     *               "name": "Suction machine",
     *               "available": null   // null = not yet answered
     *             }, ...
     *           ]
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        // Load schema from cache (busted when admin updates commodities)
        $schema = Cache::remember('api.health_products.schema', now()->addHours(6), function () {
            return AssessmentDepartment::where('is_active', true)
                            ->orderBy('order')
                            ->get(['id', 'name', 'order'])
                            ->map(function ($dept) {
                                $categories = CommodityCategory::orderBy('order')
                                        ->get(['id', 'name', 'order'])
                                        ->map(function ($cat) use ($dept) {
                                            $commodities = Commodity::where('commodity_category_id', $cat->id)
                                                    ->where('is_active', true)
                                                    ->whereHas('applicableDepartments', fn($q) =>
                                                            $q->where('assessment_department_id', $dept->id)
                                                    )
                                                    ->orderBy('order')
                                                    ->get(['id', 'name', 'description'])
                                                    ->map(fn($c) => [
                                                        'commodity_id' => $c->id,
                                                        'name' => $c->name,
                                                        'description' => $c->description,
                                                            ])
                                                    ->values();

                                            if ($commodities->isEmpty())
                                                return null;

                                            return [
                                                'category_id' => $cat->id,
                                                'category_name' => $cat->name,
                                                'commodities' => $commodities,
                                            ];
                                        })
                                        ->filter()
                                        ->values();

                                return [
                                    'department_id' => $dept->id,
                                    'department_name' => $dept->name,
                                    'categories' => $categories,
                                ];
                            })
                            ->values();
        });

        // Load saved responses for this assessment
        $responses = AssessmentCommodityResponse::where('assessment_id', $assessment->id)
                ->get()
                ->keyBy(fn($r) => "{$r->assessment_department_id}_{$r->commodity_id}");

        // Merge responses into schema
        $merged = $schema->map(function ($dept) use ($responses) {
            $dept['categories'] = collect($dept['categories'])->map(function ($cat) use ($dept, $responses) {
                        $cat['commodities'] = collect($cat['commodities'])->map(function ($c) use ($dept, $responses) {
                                    $key = "{$dept['department_id']}_{$c['commodity_id']}";
                                    $r = $responses->get($key);
                                    $c['available'] = $r ? (bool) $r->available : null;
                                    return $c;
                                })->values()->all();
                        return $cat;
                    })->values()->all();
            return $dept;
        });

        return response()->json(['data' => $merged]);
    }

    /**
     * POST /api/v1/assessments/{assessment}/health-products
     *
     * Bulk-save commodity availability responses.
     *
     * Request body:
     * {
     *   "responses": [
     *     { "department_id": 1, "commodity_id": 5, "available": true },
     *     { "department_id": 1, "commodity_id": 6, "available": false },
     *     ...
     *   ]
     * }
     */
    public function store(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Completed assessments cannot be modified.'], 403);
        }

        $request->validate([
            'responses' => 'required|array',
            'responses.*.department_id' => 'required|integer|exists:assessment_departments,id',
            'responses.*.commodity_id' => 'required|integer|exists:commodities,id',
            'responses.*.available' => 'required|boolean',
        ]);

        // Group by department to run scoring per department after save
        $byDepartment = collect($request->responses)->groupBy('department_id');

        foreach ($byDepartment as $departmentId => $entries) {
            foreach ($entries as $entry) {
                $available = (bool) $entry['available'];
                AssessmentCommodityResponse::updateOrCreate(
                        [
                            'assessment_id' => $assessment->id,
                            'assessment_department_id' => $departmentId,
                            'commodity_id' => $entry['commodity_id'],
                        ],
                        [
                            'available' => $available,
                            'score' => $available ? 1 : 0,
                        ]
                );
            }

            // Recalculate department score after all entries saved
            $this->scoringService->recalculateDepartmentScore($assessment->id, $departmentId);
        }

        // Mark section progress
        $progress = $assessment->section_progress ?? [];
        $progress['health_products'] = true;
        $assessment->section_progress = $progress;
        $assessment->save();

        return response()->json(['message' => 'Health products responses saved.']);
    }
}
