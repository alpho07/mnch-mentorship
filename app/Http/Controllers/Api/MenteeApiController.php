<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenteeApiController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * GET /api/v1/me/classes
     * Returns all classes the authenticated user is enrolled in.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $participations = ClassParticipant::with([
                'mentorshipClass.training.facility:id,name',
                'mentorshipClass.training.mentor:id,name',
                'mentorshipClass.classModules',
            ])
            ->where('user_id', $userId)
            ->get();

        $data = $participations->map(fn(ClassParticipant $p) => [
            'id'                  => $p->mentorshipClass->id,
            'name'                => $p->mentorshipClass->name,
            'status'              => $p->mentorshipClass->status,
            'training_title'      => $p->mentorshipClass->training?->title,
            'facility'            => $p->mentorshipClass->training?->facility?->name,
            'mentor_name'         => $p->mentorshipClass->training?->mentor?->name,
            'progress_percentage' => $p->mentorshipClass->progress_percentage,
            'module_count'        => $p->mentorshipClass->module_count,
            'participant_id'      => $p->id,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/me/classes/{class}
     * Class detail with module list and attendance flags for the authenticated mentee.
     */
    public function show(Request $request, MentorshipClass $class): JsonResponse
    {
        $userId = $request->user()->id;

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $confirmedModuleIds = ClassAttendance::where('class_id', $class->id)
            ->where('user_id', $userId)
            ->whereNotNull('class_module_id')
            ->pluck('class_module_id')
            ->flip();

        $progress = $participant->moduleProgress()
            ->with('classModule.programModule')
            ->get();

        $modules = $class->classModules()
            ->with('programModule')
            ->orderBy('order_sequence')
            ->get()
            ->map(fn(ClassModule $m) => [
                'id'              => $m->id,
                'name'            => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
                'status'          => $m->status,
                'order_sequence'  => $m->order_sequence,
                'attended'        => isset($confirmedModuleIds[$m->id]),
                'progress_status' => $progress->firstWhere('class_module_id', $m->id)?->status ?? 'not_started',
            ]);

        return response()->json([
            'data' => [
                'id'                  => $class->id,
                'name'                => $class->name,
                'status'              => $class->status,
                'participant_id'      => $participant->id,
                'progress_percentage' => $class->progress_percentage,
                'modules'             => $modules,
            ],
        ]);
    }

    /**
     * POST /api/v1/me/classes/{class}/modules/{module}/attend
     * Mentee self-confirms attendance for a module.
     */
    public function attend(Request $request, MentorshipClass $class, ClassModule $module): JsonResponse
    {
        abort_if($module->mentorship_class_id !== $class->id, 404);

        $result = $this->attendanceService->confirmModuleAttendance($request->user(), $module);

        $status = $result['success'] ? 200 : 422;
        return response()->json(['message' => $result['message']], $status);
    }
}
