<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'program_module_id'   => $m->program_module_id,
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
     * POST /api/v1/classes/{class}/modules
     */
    public function store(Request $request, MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);
        abort_if($class->status === 'completed' || $class->status === 'cancelled', 422, 'Cannot add modules to a completed or cancelled class.');

        $request->validate(['program_module_id' => 'required|integer|exists:program_modules,id']);

        $alreadyAdded = $class->classModules()->where('program_module_id', $request->program_module_id)->exists();
        abort_if($alreadyAdded, 409, 'This module is already added to the class.');

        $programModule = ProgramModule::findOrFail($request->program_module_id);
        $nextOrder = ($class->classModules()->max('order_sequence') ?? 0) + 1;

        $module = ClassModule::create([
            'mentorship_class_id' => $class->id,
            'program_module_id'   => $programModule->id,
            'order_sequence'      => $nextOrder,
            'status'              => $class->status === 'active' ? 'in_progress' : 'not_started',
        ]);

        // If class is active, start the module immediately
        if ($class->status === 'active') {
            $module->start();
            $module->refresh();
        }

        return response()->json([
            'data' => [
                'id'                  => $module->id,
                'program_module_id'   => $module->program_module_id,
                'name'                => $programModule->name,
                'status'              => $module->status,
                'order_sequence'      => $module->order_sequence,
                'session_count'       => $module->sessions()->count(),
                'requires_assessment' => (bool) $module->requires_assessment,
                'started_at'          => $module->started_at?->toIso8601String(),
                'completed_at'        => $module->completed_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/modules/{module}
     */
    public function destroy(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);
        abort_if($module->status !== 'not_started', 422, 'Cannot remove a module that has been started.');

        $module->delete();

        return response()->json(['message' => 'Module removed.']);
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
            ->with('facilitator:id,name')
            ->orderBy('session_number')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'session_number'   => $s->session_number,
                'title'            => $s->title,
                'description'      => $s->description,
                'status'           => $s->status,
                'scheduled_date'   => $s->scheduled_date?->toDateString(),
                'scheduled_time'   => $s->scheduled_time,
                'actual_date'      => $s->actual_date?->toDateString(),
                'actual_time'      => $s->actual_time,
                'duration_minutes' => $s->duration_minutes,
                'location'         => $s->location,
                'notes'            => $s->notes,
                'attendance_taken' => (bool) $s->attendance_taken,
                'facilitator'      => $s->facilitator?->name,
            ]);

        return response()->json(['data' => $sessions]);
    }
}
