<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use Illuminate\Http\JsonResponse;

class ClassModuleController extends Controller
{
    use AuthorizesClassAccess;
    /**
     * GET /api/v1/classes/{class}/modules
     */
    public function index(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $modules = $class->classModules()
            ->with(['programModule', 'sessions'])
            ->orderBy('order_sequence')
            ->get()
            ->map(fn(ClassModule $m) => [
                'id'                  => $m->id,
                'name'                => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
                'status'              => $m->status,
                'order_sequence'      => $m->order_sequence,
                'session_count'       => $m->sessions->count(),
                'started_at'          => $m->started_at?->toIso8601String(),
                'completed_at'        => $m->completed_at?->toIso8601String(),
                'requires_assessment' => $m->requires_assessment,
            ]);

        return response()->json(['data' => $modules]);
    }

    /**
     * POST /api/v1/modules/{module}/start
     */
    public function start(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        if (!$module->canStart()) {
            return response()->json([
                'message' => "Module cannot be started. Current status: {$module->status}.",
            ], 422);
        }

        $module->start();
        $module->refresh();

        return response()->json([
            'message' => 'Module started.',
            'data'    => [
                'id'         => $module->id,
                'status'     => $module->status,
                'started_at' => $module->started_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/modules/{module}/complete
     */
    public function complete(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        if (!$module->canComplete()) {
            return response()->json([
                'message' => "Module cannot be completed. Current status: {$module->status}.",
            ], 422);
        }

        $module->complete();
        $module->refresh();

        return response()->json([
            'message' => 'Module completed.',
            'data'    => [
                'id'           => $module->id,
                'status'       => $module->status,
                'completed_at' => $module->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/modules/{module}/sessions
     */
    public function sessions(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        $sessions = $module->sessions()
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'session_number' => $s->session_number,
                'status'         => $s->status,
                'scheduled_date' => $s->scheduled_date?->toDateString(),
                'started_at'     => $s->started_at?->toIso8601String(),
                'completed_at'   => $s->completed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $sessions]);
    }
}
