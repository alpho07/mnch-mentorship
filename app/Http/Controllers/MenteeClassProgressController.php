<?php

namespace App\Http\Controllers;

use App\Models\MentorshipClass;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenteeClassProgressController extends Controller {

    /**
     * Show mentee's progress in a class
     */
    public function show(MentorshipClass $class) {
        $user = auth()->user();

        // Get participant record
        $participant = $class->participants()
                ->where('user_id', $user->id)
                ->firstOrFail();

        // Get all module progress for this participant
        $moduleProgress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->with([
                    'classModule.programModule',
                    'classModule.moduleAssessments',
                    'assessmentResults.moduleAssessment'
                ])
                ->get();

        // Separate by status
        $exemptedModules = $moduleProgress->where('status', 'exempted');
        $completedModules = $moduleProgress->where('status', 'completed');
        $activeModules = $moduleProgress->whereIn('status', ['not_started', 'in_progress']);

        // Calculate statistics
        $totalModules = $moduleProgress->count();
        $completedCount = $completedModules->count();
        $exemptedCount = $exemptedModules->count();
        $progressPercentage = $totalModules > 0 ? round((($completedCount + $exemptedCount) / $totalModules) * 100, 2) : 0;

        // Calculate attendance rate (average of all modules)
        $attendanceRate = $moduleProgress
                        ->where('attendance_percentage', '!=', null)
                        ->avg('attendance_percentage') ?? 0;

        return view('mentee.class-progress', [
            'class' => $class,
            'participant' => $participant,
            'exemptedModules' => $exemptedModules,
            'completedModules' => $completedModules,
            'activeModules' => $activeModules,
            'totalModules' => $totalModules,
            'completedCount' => $completedCount,
            'exemptedCount' => $exemptedCount,
            'progressPercentage' => $progressPercentage,
            'attendanceRate' => round($attendanceRate, 2),
        ]);
    }

    /**
     * Calculate overall progress percentage
     */
    private function calculateOverallProgress($moduleProgress): float {
        $totalModules = $moduleProgress->count();
        if ($totalModules === 0)
            return 0;

        $completedOrExempted = $moduleProgress->filter(fn($m) => $m->is_completed)->count();

        return round(($completedOrExempted / $totalModules) * 100, 1);
    }
}
