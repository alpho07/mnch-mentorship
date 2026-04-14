<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use Illuminate\Http\JsonResponse;

class ParticipantController extends Controller
{
    use AuthorizesClassAccess;
    /**
     * GET /api/v1/classes/{class}/participants
     */
    public function index(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $participants = $class->participants()
            ->with(['user', 'moduleProgress'])
            ->get()
            ->map(fn(ClassParticipant $p) => [
                'id'             => $p->id,
                'user_id'        => $p->user_id,
                'name'           => $p->user?->name,
                'email'          => $p->user?->email,
                'status'         => $p->status,
                'enrolled_at'    => $p->enrolled_at?->toIso8601String(),
                'completion_pct' => $this->calcCompletionPct($p->moduleProgress),
            ]);

        return response()->json(['data' => $participants]);
    }

    /**
     * GET /api/v1/participants/{participant}/progress
     */
    public function progress(ClassParticipant $participant): JsonResponse
    {
        $this->authorizeParticipantAccess($participant);

        $progress = $participant->moduleProgress()
            ->with('classModule.programModule')
            ->get()
            ->map(fn(MenteeModuleProgress $mp) => [
                'module_id'             => $mp->class_module_id,
                'module_name'           => $mp->classModule?->programModule?->name,
                'status'                => $mp->status,
                'attendance_percentage' => $mp->attendance_percentage,
                'assessment_score'      => $mp->assessment_score,
            ]);

        return response()->json([
            'data' => [
                'participant_id' => $participant->id,
                'user_id'        => $participant->user_id,
                'name'           => $participant->user?->name,
                'progress'       => $progress,
                'completion_pct' => $this->calcCompletionPct($participant->moduleProgress),
            ],
        ]);
    }

    private function calcCompletionPct($progressCollection): float
    {
        $total = $progressCollection->count();
        if ($total === 0) return 0.0;
        $completed = $progressCollection->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 1);
    }
}
