<?php

namespace App\Http\Controllers;

use App\Models\ClassAttendance;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use Illuminate\Support\Facades\Auth;

class MenteeClassProgressController extends Controller {

    public function show(int $classId) {
        $class = MentorshipClass::with([
                    'training' => fn($q) => $q->withTrashed(),
                    'classModules.programModule',
                    'classModules.sessions',
                ])
                ->findOrFail($classId);

        $training = $class->training;
        abort_if(is_null($training), 404, 'Mentorship program not found.');

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

        // Module progress — load classModule so we get attendance_token & attendance_link_active
        $moduleProgress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->with('classModule.programModule')
                ->orderBy('created_at')
                ->get();

        // Which class_module_ids has this user already confirmed attendance for?
        $confirmedModuleIds = ClassAttendance::where('class_id', $class->id)
                ->where('user_id', Auth::id())
                ->whereNotNull('class_module_id')
                ->pluck('class_module_id')
                ->toArray();

        // ── Module stats ───────────────────────────────────────────────────
        $totalCount = $moduleProgress->count();
        $completedCount = $moduleProgress->whereIn('status', ['completed', 'exempted'])->count();
        $exemptedCount = $moduleProgress->where('status', 'exempted')->count();
        $inProgressCount = $moduleProgress->where('status', 'in_progress')->count();
        $notStartedCount = $moduleProgress->where('status', 'not_started')->count();
        $completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;

        // ── Attendance stats ───────────────────────────────────────────────
        $attendanceCount = count($confirmedModuleIds);
        $totalSessions = $class->classModules->where('status', 'in_progress')->count() + $class->classModules->where('status', 'completed')->count();
        $attendanceRate = $totalSessions > 0 ? round(($attendanceCount / $totalSessions) * 100) : 0;

        // ── Assessment stats ───────────────────────────────────────────────
        $avgAssessmentScore = $moduleProgress->whereNotNull('assessment_score')->avg('assessment_score');
        $assessedModules = $moduleProgress->where('assessment_status', 'passed')->count();
        $failedModules = $moduleProgress->where('assessment_status', 'failed')->count();
        $pendingModules = $moduleProgress
                ->where('assessment_status', 'pending')
                ->where('status', 'completed')
                ->count();

        return view('mentee.class-progress', compact(
                        'participant',
                        'class',
                        'training',
                        'moduleProgress',
                        'confirmedModuleIds',
                        'totalCount',
                        'completedCount',
                        'exemptedCount',
                        'inProgressCount',
                        'notStartedCount',
                        'completionRate',
                        'attendanceCount',
                        'totalSessions',
                        'attendanceRate',
                        'avgAssessmentScore',
                        'assessedModules',
                        'failedModules',
                        'pendingModules',
                ));
    }
}
