<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\ModuleSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    use AuthorizesClassAccess;

    /**
     * GET /api/v1/modules/{module}/sessions/available-templates
     * Returns template sessions not yet instantiated for this class module.
     */
    public function availableTemplates(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        $usedTemplateIds = ClassSession::where('class_module_id', $module->id)
            ->whereNotNull('module_session_id')
            ->pluck('module_session_id')
            ->toArray();

        $templates = ModuleSession::where('program_module_id', $module->program_module_id)
            ->where('is_active', true)
            ->whereNotIn('id', $usedTemplateIds)
            ->orderBy('order_sequence')
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'name'             => $t->name,
                'description'      => $t->description,
                'duration_minutes' => $t->time_minutes,
                'order_sequence'   => $t->order_sequence,
            ]);

        return response()->json(['data' => $templates]);
    }

    /**
     * POST /api/v1/modules/{module}/sessions
     * Body: { "module_session_id": 5 }
     */
    public function store(Request $request, ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        $request->validate(['module_session_id' => 'required|integer|exists:module_sessions,id']);

        $templateId = (int) $request->module_session_id;

        $template = ModuleSession::where('id', $templateId)
            ->where('program_module_id', $module->program_module_id)
            ->where('is_active', true)
            ->firstOrFail();

        $exists = ClassSession::where('class_module_id', $module->id)
            ->where('module_session_id', $templateId)
            ->exists();
        abort_if($exists, 409, 'This session has already been added to the module.');

        $nextNumber = ($module->sessions()->max('session_number') ?? 0) + 1;

        $session = ClassSession::create([
            'class_module_id'   => $module->id,
            'module_session_id' => $template->id,
            'session_number'    => $nextNumber,
            'title'             => $template->name,
            'description'       => $template->description,
            'duration_minutes'  => $template->time_minutes,
            'status'            => 'scheduled',
            'attendance_taken'  => false,
        ]);

        return response()->json([
            'data' => [
                'id'               => $session->id,
                'session_number'   => $session->session_number,
                'title'            => $session->title,
                'description'      => $session->description,
                'status'           => $session->status,
                'duration_minutes' => $session->duration_minutes,
                'attendance_taken' => false,
                'scheduled_date'   => null,
                'actual_date'      => null,
                'location'         => null,
                'notes'            => null,
                'facilitator'      => null,
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/sessions/{session}
     */
    public function destroy(ClassSession $session): JsonResponse
    {
        $this->authorizeModuleAccess($session->classModule);
        abort_if($session->status !== 'scheduled', 422, 'Only scheduled sessions can be removed.');

        $session->delete();

        return response()->json(['message' => 'Session removed.']);
    }

    /**
     * PUT /api/v1/sessions/{session}
     */
    public function update(Request $request, ClassSession $session): JsonResponse
    {
        $this->authorizeModuleAccess($session->classModule);

        $request->validate([
            'actual_date'      => 'nullable|date',
            'actual_time'      => 'nullable|string|max:10',
            'location'         => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'facilitator_id'   => 'nullable|integer|exists:users,id',
            'attendance_taken'  => 'nullable|boolean',
            'status'           => 'nullable|in:scheduled,in_progress,completed',
        ]);

        $data = $request->only(['actual_date', 'actual_time', 'location', 'notes', 'attendance_taken', 'status']);
        $data['facilitator_id'] = $request->facilitator_id ?? $request->user()->id;

        $session->update($data);
        $session->refresh();

        return response()->json([
            'data' => [
                'id'               => $session->id,
                'title'            => $session->title,
                'actual_date'      => $session->actual_date?->toDateString(),
                'actual_time'      => $session->actual_time,
                'location'         => $session->location,
                'notes'            => $session->notes,
                'attendance_taken'  => $session->attendance_taken,
                'status'           => $session->status,
            ],
        ]);
    }
}
