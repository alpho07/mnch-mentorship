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
        $user = Auth::user();

        // Get participant record
        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
                ->where('user_id', $user->id)
                ->with([
                    'mentorshipClass.training',
                    'sessionAttendance.classSession',
                ])
                ->firstOrFail();

        // Get module progress
        $moduleProgress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->with([
                    'classModule.programModule',
                    'classModule.sessions',
                    'assessments',
                ])
                ->orderBy('class_module_id')
                ->get();

        // Separate modules
        $exemptedModules = $moduleProgress->where('is_exempted', true);
        $activeModules = $moduleProgress->where('is_exempted', false)->where('status', '!=', 'completed');
        $completedModules = $moduleProgress->where('is_exempted', false)->where('status', 'completed');

        // Calculate statistics
        $stats = [
            'total_modules' => $moduleProgress->count(),
            'exempted_count' => $exemptedModules->count(),
            'completed_count' => $completedModules->count(),
            'pending_count' => $activeModules->count(),
            'attendance_rate' => $participant->attendance_rate,
            'overall_progress' => $this->calculateOverallProgress($moduleProgress),
        ];

        return view('mentee.class-progress', [
            'class' => $class,
            'participant' => $participant,
            'exemptedModules' => $exemptedModules,
            'activeModules' => $activeModules,
            'completedModules' => $completedModules,
            'stats' => $stats,
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
